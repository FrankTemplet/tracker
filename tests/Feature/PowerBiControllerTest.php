<?php

use App\Models\User;
use App\Services\PowerBiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);


/**
 * Fake the two queries the campaign catalogue now issues.
 *
 * getUniqueCampaigns() reads '(raw) Email Campaign Metrics' for the list itself
 * and '(raw) Engagement' for each campaign's business unit and start date, so a
 * single canned payload no longer covers it.
 *
 * @param  array<int, array{id: string, name: string, bu?: string, start?: string}>  $campaigns
 */
function fakeCampaignCatalogue(array $campaigns, ?callable $fallback = null): void
{
    $catalogue = array_map(fn (array $c) => [
        '(raw) Email Campaign Metrics[Campaign ID]' => $c['id'],
        '(raw) Email Campaign Metrics[Campaign Name]' => $c['name'],
        '[first_send]' => $c['start'] ?? '5/5/2025 9:00:00 AM',
    ], $campaigns);

    $universe = array_map(fn (array $c) => [
        '(raw) Engagement[Campaign ID]' => $c['id'],
        '(raw) Engagement[Campaign Name]' => $c['name'],
        '(raw) Engagement[Reporting Business Unit]' => $c['bu'] ?? 'CaribRegional',
        '(raw) Engagement[Start Date]' => $c['start'] ?? '5/5/2025',
    ], $campaigns);

    $wrap = fn (array $rows) => Http::response(['results' => [['tables' => [['rows' => $rows]]]]]);

    Http::fake(function ($request) use ($catalogue, $universe, $wrap, $fallback) {
        if (str_contains($request->url(), 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-token']);
        }

        $body = $request->body();

        if (str_contains($body, 'SUMMARIZECOLUMNS')) {
            return str_contains($body, 'Email Campaign Metrics') ? $wrap($catalogue) : $wrap($universe);
        }

        return $fallback ? $fallback($request) : $wrap([]);
    });
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('campaigns endpoint returns campaigns from the user region only', function () {
    fakeCampaignCatalogue([
        ['id' => '701Pl00000hB2yb', 'name' => 'CARIB_Test_Campaign_1'],
        ['id' => '701Pl00000hB3xc', 'name' => 'CARIB_Test_Campaign_2'],
        ['id' => '701Pl00000hB4yd', 'name' => 'LATAM_Test_Campaign_3', 'bu' => 'LATAM'],
    ]);

    // User has region "carib" by default, so the LATAM campaign is excluded
    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaigns'));

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'business_unit', 'created_at'],
            ],
        ]);
});

test('campaigns endpoint returns networks and latam campaigns for networks users', function () {
    fakeCampaignCatalogue([
        ['id' => '701Pl00000hB2yb', 'name' => 'CARIB_Test_Campaign_1'],
        ['id' => '701Pl00000hB3xc', 'name' => 'NETWORKS_Test_Campaign_2', 'bu' => 'Networks'],
        ['id' => '701Pl00000hB4yd', 'name' => 'LATAM_Test_Campaign_3', 'bu' => 'LATAM'],
    ]);

    $networksUser = User::factory()->networks()->create();

    $response = $this->actingAs($networksUser)->getJson(route('powerbi.campaigns'));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'NETWORKS_Test_Campaign_2')
        ->assertJsonPath('data.1.name', 'LATAM_Test_Campaign_3');
});

test('campaigns endpoint requires authentication', function () {
    $response = $this->getJson(route('powerbi.campaigns'));

    $response->assertUnauthorized();
});

test('campaigns endpoint returns error on service failure', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('getUniqueCampaigns')
        ->once()
        ->andThrow(new Exception('Power BI API error'));

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaigns'));

    $response->assertStatus(500)
        ->assertJson([
            'success' => false,
            'message' => 'Failed to fetch campaigns. Please try again later.',
        ]);
});

test('campaign metrics endpoint returns aggregated metrics', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-token']);
        }

        // The catalogue query and the engagement-metadata query both use
        // SUMMARIZECOLUMNS, so they are told apart by the table they read.
        if (str_contains($request->body(), 'SUMMARIZECOLUMNS')
            && str_contains($request->body(), 'Email Campaign Metrics')) {
            return Http::response(['results' => [['tables' => [['rows' => [[
                '(raw) Email Campaign Metrics[Campaign ID]' => '701Pl00000hB2yb',
                '(raw) Email Campaign Metrics[Campaign Name]' => 'CARIB_Test_Campaign_2025',
                '[first_send]' => '5/5/2025 9:00:00 AM',
            ]]]]]]]);
        }

        // Campaign lookup used for the region authorization check
        if (str_contains($request->body(), 'SUMMARIZECOLUMNS')) {
            return Http::response([
                'results' => [
                    [
                        'tables' => [
                            [
                                'rows' => [
                                    [
                                        '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
                                        '(raw) Engagement[Campaign Name]' => 'CARIB_Test_Campaign_2025',
                                        '(raw) Engagement[Reporting Business Unit]' => 'CaribRegional',
                                        '(raw) Engagement[Start Date]' => '5/5/2025',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        return Http::response([
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
                                    '(raw) Email Campaign Metrics[Total Delivered]' => 200,
                                    '(raw) Email Campaign Metrics[Unique Opens]' => 100,
                                    '(raw) Email Campaign Metrics[Open Rate]' => 50,
                                    '(raw) Email Campaign Metrics[Unique Clicks]' => 50,
                                    '(raw) Email Campaign Metrics[Unique Click Through Rate]' => 25,
                                    '(raw) Email Campaign Metrics[Click To Open Ratio]' => 50,
                                    '(raw) Email Campaign Metrics[Total Click Through Rate]' => 20,
                                    '(raw) Email Campaign Metrics[Total Opens]' => 150,
                                    '(raw) Email Campaign Metrics[Total Hard Bounces]' => 10,
                                    '(raw) Email Campaign Metrics[Delivery Rate]' => 95.24,
                                    '(raw) Email Campaign Metrics[Segment]' => 'Enterprise',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    });

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaign.metrics', [
        'campaignId' => '701Pl00000hB2yb',
    ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'data' => [
                'campaign_id',
                'campaign_name',
                'segment',
                'summary' => [
                    'delivered',
                    'unique_opens',
                    'open_rate',
                    'unique_clicks',
                    'click_rate',
                    'unique_click_through_rate',
                    'click_to_open_rate',
                    'total_click_through_rate',
                    'total_opens',
                    'hard_bounces',
                    'delivery_rate',
                    'segment',
                ],
                'emails',
            ],
        ]);
});

test('campaign metrics endpoint requires authentication', function () {
    $response = $this->getJson(route('powerbi.campaign.metrics', [
        'campaignId' => '701Pl00000hB2yb',
    ]));

    $response->assertUnauthorized();
});

test('campaign members endpoint supports hard-bounces metric', function () {
    config([
        'powerbi.client_id' => null,
        'powerbi.client_secret' => null,
        'powerbi.tenant_id' => null,
    ]);

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaign.members', [
        'campaignId' => '701Pl00000hB2yb',
        'status' => 'hard-bounces',
    ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'data' => [
                '*' => ['member_id', 'first_name', 'last_name', 'email', 'company', 'status_update_date'],
            ],
        ]);
});

test('campaign members endpoint returns member list by status', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-token']);
        }

        // The catalogue query and the engagement-metadata query both use
        // SUMMARIZECOLUMNS, so they are told apart by the table they read.
        if (str_contains($request->body(), 'SUMMARIZECOLUMNS')
            && str_contains($request->body(), 'Email Campaign Metrics')) {
            return Http::response(['results' => [['tables' => [['rows' => [[
                '(raw) Email Campaign Metrics[Campaign ID]' => '701Pl00000hB2yb',
                '(raw) Email Campaign Metrics[Campaign Name]' => 'CARIB_Test_Campaign_2025',
                '[first_send]' => '5/5/2025 9:00:00 AM',
            ]]]]]]]);
        }

        // Campaign lookup used for the region authorization check
        if (str_contains($request->body(), 'SUMMARIZECOLUMNS')) {
            return Http::response([
                'results' => [
                    [
                        'tables' => [
                            [
                                'rows' => [
                                    [
                                        '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
                                        '(raw) Engagement[Campaign Name]' => 'CARIB_Test_Campaign_2025',
                                        '(raw) Engagement[Reporting Business Unit]' => 'CaribRegional',
                                        '(raw) Engagement[Start Date]' => '5/5/2025',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        return Http::response([
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
        ]);
    });

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaign.members', [
        'campaignId' => '701Pl00000hB2yb',
        'status' => 'Opened',
    ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'data' => [
                '*' => ['member_id', 'first_name', 'last_name', 'email', 'company', 'status_update_date'],
            ],
        ]);
});

test('campaign metrics endpoint denies campaigns outside the user region', function () {
    config([
        'powerbi.client_id' => null,
        'powerbi.client_secret' => null,
        'powerbi.tenant_id' => null,
    ]);

    // Fake data campaign 701Pl00000hB3xc is not prefixed with "carib"
    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaign.metrics', [
        'campaignId' => '701Pl00000hB3xc',
    ]));

    $response->assertForbidden()
        ->assertJson([
            'success' => false,
        ]);
});

test('campaign members endpoint denies campaigns outside the user region', function () {
    config([
        'powerbi.client_id' => null,
        'powerbi.client_secret' => null,
        'powerbi.tenant_id' => null,
    ]);

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaign.members', [
        'campaignId' => '701Pl00000hB3xc',
        'status' => 'Opened',
    ]));

    $response->assertForbidden()
        ->assertJson([
            'success' => false,
        ]);
});

test('campaign members endpoint requires authentication', function () {
    $response = $this->getJson(route('powerbi.campaign.members', [
        'campaignId' => '701Pl00000hB2yb',
        'status' => 'Opened',
    ]));

    $response->assertUnauthorized();
});

test('embed token endpoint returns token', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('getEmbedToken')
        ->once()
        ->with('report-123')
        ->andReturn('embed-token-123');

    $response = $this->actingAs($this->user)->getJson(route('powerbi.embed.token', [
        'reportId' => 'report-123',
    ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'token' => 'embed-token-123',
            ],
        ]);
});

test('embed token endpoint requires authentication', function () {
    $response = $this->getJson(route('powerbi.embed.token', [
        'reportId' => 'report-123',
    ]));

    $response->assertUnauthorized();
});

test('power bi endpoints are rate limited', function () {
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

    for ($i = 0; $i < 60; $i++) {
        $response = $this->actingAs($this->user)->getJson(route('powerbi.campaigns'));
        $response->assertOk();
    }

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaigns'));
    $response->assertStatus(429);
});
