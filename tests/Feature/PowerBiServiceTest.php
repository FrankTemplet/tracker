<?php

use App\Services\PowerBiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();
});

test('getAccessToken returns cached token when available', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fake-access-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
    ]);

    $service = new PowerBiService;

    // First call should hit the API
    $token1 = $service->getAccessToken();
    expect($token1)->toBe('fake-access-token-123');

    // Second call should use cached token (no additional HTTP request)
    Http::assertSentCount(1);
    $token2 = $service->getAccessToken();
    expect($token2)->toBe('fake-access-token-123');
    Http::assertSentCount(1);
});

test('getAccessToken throws exception on failed authentication', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'Invalid client credentials',
        ], 401),
    ]);

    $service = new PowerBiService;

    expect(fn () => $service->getAccessToken())
        ->toThrow(Exception::class, 'Failed to obtain Power BI access token');
});

test('getAllEngagements returns list of engagement records', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/executeQueries' => Http::response([
            'results' => [
                [
                    'tables' => [
                        [
                            'rows' => [
                                [
                                    '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
                                    '(raw) Engagement[Campaign Name]' => 'Test Campaign',
                                    '(raw) Engagement[Member Status]' => 'Opened',
                                    '(raw) Engagement[First Name]' => 'John',
                                    '(raw) Engagement[Last Name]' => 'Doe',
                                ],
                                [
                                    '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
                                    '(raw) Engagement[Campaign Name]' => 'Test Campaign',
                                    '(raw) Engagement[Member Status]' => 'Clicked',
                                    '(raw) Engagement[First Name]' => 'Jane',
                                    '(raw) Engagement[Last Name]' => 'Smith',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new PowerBiService;
    $engagements = $service->getAllEngagements();

    expect($engagements)->toHaveCount(2)
        ->and($engagements[0]['(raw) Engagement[Campaign ID]'])->toBe('701Pl00000hB2yb')
        ->and($engagements[0]['(raw) Engagement[Member Status]'])->toBe('Opened')
        ->and($engagements[1]['(raw) Engagement[Member Status]'])->toBe('Clicked');
});

test('getAllEngagements throws exception on API failure', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/executeQueries' => Http::response(['error' => 'Unauthorized'], 403),
    ]);

    $service = new PowerBiService;

    expect(fn () => $service->getAllEngagements())
        ->toThrow(Exception::class, 'Failed to fetch engagements');
});

test('getEngagementsByCampaign returns filtered engagements', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/executeQueries' => Http::response([
            'results' => [
                [
                    'tables' => [
                        [
                            'rows' => [
                                [
                                    '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
                                    '(raw) Engagement[Member Status]' => 'Opened',
                                    '(raw) Engagement[First Name]' => 'John',
                                ],
                                [
                                    '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
                                    '(raw) Engagement[Member Status]' => 'Clicked',
                                    '(raw) Engagement[First Name]' => 'Jane',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new PowerBiService;
    $engagements = $service->getEngagementsByCampaign('701Pl00000hB2yb');

    expect($engagements)->toHaveCount(2)
        ->and($engagements[0]['(raw) Engagement[Campaign ID]'])->toBe('701Pl00000hB2yb')
        ->and($engagements[1]['(raw) Engagement[Campaign ID]'])->toBe('701Pl00000hB2yb');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), '/executeQueries')
            && isset($body['queries'][0]['query'])
            && str_contains($body['queries'][0]['query'], '701Pl00000hB2yb');
    });
});

test('getMembersByStatus returns filtered members', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/executeQueries' => Http::response([
            'results' => [
                [
                    'tables' => [
                        [
                            'rows' => [
                                [
                                    '(raw) Engagement[Member ID]' => '00vPl00000UmUCI',
                                    '(raw) Engagement[First Name]' => 'John',
                                    '(raw) Engagement[Last Name]' => 'Doe',
                                    '(raw) Engagement[Email]' => 'john@example.com',
                                    '(raw) Engagement[Company]' => 'Test Corp',
                                    '(raw) Engagement[Member Status Update Date]' => '5/19/2025',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new PowerBiService;
    $members = $service->getMembersByStatus('701Pl00000hB2yb', 'Opened');

    expect($members)->toHaveCount(1)
        ->and($members[0]['member_id'])->toBe('00vPl00000UmUCI')
        ->and($members[0]['first_name'])->toBe('John')
        ->and($members[0]['email'])->toBe('john@example.com');
});

test('getUniqueCampaigns returns unique campaigns list', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/executeQueries' => Http::response([
            'results' => [
                [
                    'tables' => [
                        [
                            'rows' => [
                                [
                                    '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
                                    '(raw) Engagement[Campaign Name]' => 'Test Campaign 1',
                                    '(raw) Engagement[Reporting Business Unit]' => 'CaribRegional',
                                    '(raw) Engagement[Start Date]' => '5/5/2025',
                                ],
                                [
                                    '(raw) Engagement[Campaign ID]' => '701Pl00000hB3xc',
                                    '(raw) Engagement[Campaign Name]' => 'Test Campaign 2',
                                    '(raw) Engagement[Reporting Business Unit]' => 'North America',
                                    '(raw) Engagement[Start Date]' => '5/10/2025',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new PowerBiService;
    $campaigns = $service->getUniqueCampaigns();

    expect($campaigns)->toHaveCount(2)
        ->and($campaigns[0]['campaign_id'])->toBe('701Pl00000hB2yb')
        ->and($campaigns[0]['campaign_name'])->toBe('Test Campaign 1')
        ->and($campaigns[0]['business_unit'])->toBe('CaribRegional')
        ->and($campaigns[1]['campaign_id'])->toBe('701Pl00000hB3xc');
});

test('hasCredentials returns true when all credentials are configured', function () {
    config([
        'powerbi.client_id' => 'test-client-id',
        'powerbi.client_secret' => 'test-secret',
        'powerbi.tenant_id' => 'test-tenant',
    ]);

    $service = new PowerBiService;

    expect($service->hasCredentials())->toBeTrue();
});

test('hasCredentials returns false when credentials are missing', function () {
    config([
        'powerbi.client_id' => null,
        'powerbi.client_secret' => null,
        'powerbi.tenant_id' => null,
    ]);

    $service = new PowerBiService;

    expect($service->hasCredentials())->toBeFalse();
});
