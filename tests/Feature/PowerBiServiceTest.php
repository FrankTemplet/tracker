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

test('getMembersByStatus returns hard bounces for hard-bounces metric', function () {
    $service = new PowerBiService;
    config([
        'powerbi.client_id' => null,
        'powerbi.client_secret' => null,
        'powerbi.tenant_id' => null,
    ]);

    $members = $service->getMembersByStatus('701Pl00000hB2yb', 'hard-bounces');

    expect($members)->not->toBeEmpty()
        ->and(collect($members)->every(fn ($member) => str_contains($member['email'], '@')))->toBeTrue();
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

test('getEngagementCampaigns returns every campaign seen in the engagement table', function () {
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
    $campaigns = $service->getEngagementCampaigns();

    expect($campaigns)->toHaveCount(2)
        ->and($campaigns[0]['campaign_id'])->toBe('701Pl00000hB2yb')
        ->and($campaigns[0]['campaign_name'])->toBe('Test Campaign 1')
        ->and($campaigns[0]['business_unit'])->toBe('CaribRegional')
        ->and($campaigns[1]['campaign_id'])->toBe('701Pl00000hB3xc');
});

test('getUniqueCampaigns builds the catalogue from the email metrics report', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/executeQueries' => Http::sequence()
            // First call: the catalogue itself, from '(raw) Email Campaign Metrics'.
            ->push([
                'results' => [[
                    'tables' => [[
                        'rows' => [
                            [
                                '(raw) Email Campaign Metrics[Campaign ID]' => '701Pl00000hB2ybAAB',
                                '(raw) Email Campaign Metrics[Campaign Name]' => 'CARIB_Emailed_Campaign',
                                '[first_send]' => '6/2/2025 9:00:00 AM',
                            ],
                            [
                                '(raw) Email Campaign Metrics[Campaign ID]' => '701Pl00000zzzzzAAB',
                                '(raw) Email Campaign Metrics[Campaign Name]' => 'CARIB_Not_In_Engagement',
                                '[first_send]' => '7/9/2025 4:00:00 PM',
                            ],
                        ],
                    ]],
                ]],
            ])
            // Second call: the engagement universe it borrows metadata from.
            ->push([
                'results' => [[
                    'tables' => [[
                        'rows' => [
                            [
                                '(raw) Engagement[Campaign ID]' => '701Pl00000hB2ybAAB',
                                '(raw) Engagement[Campaign Name]' => 'CARIB_Emailed_Campaign',
                                '(raw) Engagement[Reporting Business Unit]' => 'CaribRegional',
                                '(raw) Engagement[Start Date]' => '5/5/2025',
                            ],
                            [
                                '(raw) Engagement[Campaign ID]' => '701Pl00000neverAAB',
                                '(raw) Engagement[Campaign Name]' => 'CARIB_Never_Emailed',
                                '(raw) Engagement[Reporting Business Unit]' => 'CaribENT',
                                '(raw) Engagement[Start Date]' => '5/6/2025',
                            ],
                        ],
                    ]],
                ]],
            ]),
    ]);

    $service = new PowerBiService;
    $campaigns = $service->getUniqueCampaigns();

    // Only emailed campaigns make the catalogue: the engagement-only campaign
    // is dropped, so nothing in the selector can land on an empty state.
    expect($campaigns)->toHaveCount(2)
        ->and(array_column($campaigns, 'campaign_name'))
        ->not->toContain('CARIB_Never_Emailed');

    // Metadata is borrowed from engagement when the campaign exists there.
    expect($campaigns[0]['campaign_id'])->toBe('701Pl00000hB2ybAAB')
        ->and($campaigns[0]['business_unit'])->toBe('CaribRegional')
        ->and($campaigns[0]['start_date'])->toBe('5/5/2025');

    // And falls back to the earliest send when it does not.
    expect($campaigns[1]['campaign_id'])->toBe('701Pl00000zzzzzAAB')
        ->and($campaigns[1]['business_unit'])->toBe('')
        ->and($campaigns[1]['start_date'])->toBe('7/9/2025 4:00:00 PM');
});

test('campaign sources drop _CR_ campaigns without touching lookalike names', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/executeQueries' => Http::sequence()
            ->push([
                'results' => [[
                    'tables' => [[
                        'rows' => [
                            [
                                '(raw) Email Campaign Metrics[Campaign ID]' => '701Pl00000keepAAB',
                                '(raw) Email Campaign Metrics[Campaign Name]' => 'LATAM_CrossSell_LCPR_2025',
                                '[first_send]' => '6/2/2025 9:00:00 AM',
                            ],
                            [
                                '(raw) Email Campaign Metrics[Campaign ID]' => '701Pl00000dropAAB',
                                '(raw) Email Campaign Metrics[Campaign Name]' => 'LATAM_CR_CM_SME_Mobile5G',
                                '[first_send]' => '6/3/2025 9:00:00 AM',
                            ],
                        ],
                    ]],
                ]],
            ])
            ->push([
                'results' => [[
                    'tables' => [[
                        'rows' => [
                            [
                                '(raw) Engagement[Campaign ID]' => '701Pl00000keepAAB',
                                '(raw) Engagement[Campaign Name]' => 'LATAM_CrossSell_LCPR_2025',
                                '(raw) Engagement[Reporting Business Unit]' => 'LatAmENT',
                                '(raw) Engagement[Start Date]' => '5/5/2025',
                            ],
                            [
                                '(raw) Engagement[Campaign ID]' => '701Pl00000dropAAB',
                                '(raw) Engagement[Campaign Name]' => 'LATAM_CR_CM_SME_Mobile5G',
                                '(raw) Engagement[Reporting Business Unit]' => 'LatAmENT',
                                '(raw) Engagement[Start Date]' => '5/6/2025',
                            ],
                        ],
                    ]],
                ]],
            ]),
    ]);

    $service = new PowerBiService;

    // "LCPR" and "CrossSell" both contain the letters, but only the delimited
    // "_CR_" token is excluded.
    expect(array_column($service->getUniqueCampaigns(), 'campaign_name'))
        ->toBe(['LATAM_CrossSell_LCPR_2025']);
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

test('getCampaignMetrics returns analytics from Email Campaign Metrics table', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*' => Http::response([
            'results' => [
                [
                    'tables' => [
                        [
                            'rows' => [
                                [
                                    '(raw) Email Campaign Metrics[RowID]' => 1,
                                    '(raw) Email Campaign Metrics[Name]' => 'Test Email 1',
                                    '(raw) Email Campaign Metrics[Subject]' => 'Test Subject',
                                    '(raw) Email Campaign Metrics[Scheduled Date]' => '5/5/2025 10:00:00 AM',
                                    '(raw) Email Campaign Metrics[Campaign ID]' => '701Pl00000hB2yb',
                                    '(raw) Email Campaign Metrics[Campaign Name]' => 'Test Campaign',
                                    '(raw) Email Campaign Metrics[Total Delivered]' => 767,
                                    '(raw) Email Campaign Metrics[Unique Opens]' => 200,
                                    '(raw) Email Campaign Metrics[Open Rate]' => 26.08,
                                    '(raw) Email Campaign Metrics[Unique Clicks]' => 43,
                                    '(raw) Email Campaign Metrics[Unique Click Through Rate]' => 5.61,
                                    '(raw) Email Campaign Metrics[Click To Open Ratio]' => 21.5,
                                    '(raw) Email Campaign Metrics[Total Click Through Rate]' => 8.2,
                                    '(raw) Email Campaign Metrics[Total Opens]' => 250,
                                    '(raw) Email Campaign Metrics[Total Hard Bounces]' => 25,
                                    '(raw) Email Campaign Metrics[Delivery Rate]' => 96.84,
                                    '(raw) Email Campaign Metrics[Segment]' => 'Small - Medium',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new PowerBiService;
    $metrics = $service->getCampaignMetrics('701Pl00000hB2yb');

    expect($metrics)->toBeArray()
        ->and($metrics['campaign_id'])->toBe('701Pl00000hB2yb')
        ->and($metrics['summary']['delivered'])->toBe(767)
        ->and($metrics['summary']['unique_opens'])->toBe(200)
        ->and($metrics['summary']['unique_clicks'])->toBe(43)
        ->and($metrics['summary']['hard_bounces'])->toBe(25)
        ->and($metrics['summary']['open_rate'])->toBe(26.08)
        ->and($metrics['summary']['click_rate'])->toBe(5.61)
        ->and($metrics['summary']['click_to_open_rate'])->toBe(21.5)
        ->and($metrics['summary']['segment'])->toBe('Small - Medium')
        ->and($metrics['emails'])->toHaveCount(1);
});

test('getCampaignMetrics returns null when campaign has no metrics', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*' => Http::response([
            'results' => [
                [
                    'tables' => [
                        [
                            'rows' => [],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new PowerBiService;
    $metrics = $service->getCampaignMetrics('nonexistent');

    expect($metrics)->toBeNull();
});

test('getCampaignMetrics filters Email Campaign Metrics with the 15-character campaign ID', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*' => Http::response([
            'results' => [
                [
                    'tables' => [
                        [
                            'rows' => [],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new PowerBiService;

    // Campaign IDs reach the service in their 18-character form, while the DAX
    // side truncates to 15. Comparing the two forms directly never matches.
    $service->getCampaignMetrics('701Pl00000dZrSrIAK');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'executeQueries')) {
            return false;
        }

        $query = $request->data()['queries'][0]['query'];

        return str_contains($query, '"701Pl00000dZrSr"')
            && ! str_contains($query, '"701Pl00000dZrSrIAK"');
    });
});
