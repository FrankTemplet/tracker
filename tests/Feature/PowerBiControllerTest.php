<?php

use App\Models\User;
use App\Services\PowerBiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('campaigns endpoint returns list of unique campaigns for authenticated user', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*' => Http::response([
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
        ]),
    ]);

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
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*' => Http::response([
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
