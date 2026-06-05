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

test('getCampaigns returns list of campaigns', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fake-token',
        ]),
        'api.powerbi.com/*/Campaigns/rows' => Http::response([
            'value' => [
                ['id' => 'campaign-1', 'name' => 'Summer Sale', 'created_at' => '2026-05-01'],
                ['id' => 'campaign-2', 'name' => 'Newsletter May', 'created_at' => '2026-05-10'],
            ],
        ], 200),
    ]);

    $service = new PowerBiService;
    $campaigns = $service->getCampaigns();

    expect($campaigns)->toHaveCount(2)
        ->and($campaigns[0])->toHaveKeys(['id', 'name', 'created_at'])
        ->and($campaigns[0]['name'])->toBe('Summer Sale');
});

test('getCampaigns throws exception on API failure', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/Campaigns/rows' => Http::response(['error' => 'Unauthorized'], 403),
    ]);

    $service = new PowerBiService;

    expect(fn () => $service->getCampaigns())
        ->toThrow(Exception::class, 'Failed to fetch campaigns');
});

test('getCampaignEmails returns filtered emails for a campaign', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/SentEmails/rows' => Http::response([
            'value' => [
                [
                    'id' => 'email-1',
                    'campaign_id' => 'campaign-1',
                    'subject' => 'Welcome Email',
                    'from' => 'noreply@example.com',
                    'to' => 'user@example.com',
                    'sent_at' => '2026-05-01 10:00:00',
                ],
                [
                    'id' => 'email-2',
                    'campaign_id' => 'campaign-2',
                    'subject' => 'Newsletter',
                    'from' => 'noreply@example.com',
                    'to' => 'user2@example.com',
                    'sent_at' => '2026-05-02 11:00:00',
                ],
                [
                    'id' => 'email-3',
                    'campaign_id' => 'campaign-1',
                    'subject' => 'Follow-up Email',
                    'from' => 'noreply@example.com',
                    'to' => 'user3@example.com',
                    'sent_at' => '2026-05-03 12:00:00',
                ],
            ],
        ]),
    ]);

    $service = new PowerBiService;
    $emails = $service->getCampaignEmails('campaign-1');

    expect($emails)->toHaveCount(2)
        ->and($emails[0]['campaign_id'])->toBe('campaign-1')
        ->and($emails[1]['campaign_id'])->toBe('campaign-1')
        ->and($emails[0]['subject'])->toBe('Welcome Email')
        ->and($emails[1]['subject'])->toBe('Follow-up Email');
});

test('getEmailAnalytics returns analytics for specific email', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/EmailAnalytics/rows' => Http::response([
            'value' => [
                [
                    'email_id' => 'email-1',
                    'bounces' => 5,
                    'bounce_rate' => 2.5,
                    'opens' => 150,
                    'open_rate' => 75.0,
                    'clicks' => 80,
                    'click_rate' => 40.0,
                ],
                [
                    'email_id' => 'email-2',
                    'bounces' => 3,
                    'bounce_rate' => 1.5,
                    'opens' => 200,
                    'open_rate' => 80.0,
                    'clicks' => 100,
                    'click_rate' => 50.0,
                ],
            ],
        ]),
    ]);

    $service = new PowerBiService;
    $analytics = $service->getEmailAnalytics('email-1');

    expect($analytics)->toHaveKeys(['bounces', 'bounce_rate', 'opens', 'open_rate', 'clicks', 'click_rate'])
        ->and($analytics['bounces'])->toBe(5)
        ->and($analytics['bounce_rate'])->toBe(2.5)
        ->and($analytics['opens'])->toBe(150)
        ->and($analytics['open_rate'])->toEqual(75.0)
        ->and($analytics['clicks'])->toBe(80)
        ->and($analytics['click_rate'])->toEqual(40.0);
});

test('getEmailAnalytics returns zero values when email not found', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/EmailAnalytics/rows' => Http::response([
            'value' => [
                ['email_id' => 'email-1', 'bounces' => 5, 'opens' => 150],
            ],
        ]),
    ]);

    $service = new PowerBiService;
    $analytics = $service->getEmailAnalytics('non-existent-email');

    expect($analytics)->toBe([
        'bounces' => 0,
        'bounce_rate' => 0.0,
        'opens' => 0,
        'open_rate' => 0.0,
        'clicks' => 0,
        'click_rate' => 0.0,
    ]);
});

test('getDatasetTables returns available tables', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/tables' => Http::response([
            'value' => [
                ['name' => 'Campaigns'],
                ['name' => 'SentEmails'],
                ['name' => 'EmailAnalytics'],
            ],
        ]),
    ]);

    $service = new PowerBiService;
    $tables = $service->getDatasetTables();

    expect($tables)->toHaveCount(3)
        ->and($tables[0]['name'])->toBe('Campaigns')
        ->and($tables[1]['name'])->toBe('SentEmails')
        ->and($tables[2]['name'])->toBe('EmailAnalytics');
});

test('triggerRefresh successfully initiates dataset refresh', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/refreshes' => Http::response([], 202),
    ]);

    $service = new PowerBiService;
    $service->triggerRefresh();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/refreshes');
    });
});

test('getEmbedToken returns embed token for report', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/GenerateToken' => Http::response([
            'token' => 'embed-token-123',
            'tokenId' => 'token-id-456',
            'expiration' => '2026-05-14T12:00:00Z',
        ]),
    ]);

    $service = new PowerBiService;
    $embedToken = $service->getEmbedToken('report-123');

    expect($embedToken)->toBe('embed-token-123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/reports/report-123/GenerateToken')
            && $request['accessLevel'] === 'View';
    });
});
