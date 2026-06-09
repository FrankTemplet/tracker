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
                                ['(raw) Engagement[Member Status]' => 'Sent'],
                                ['(raw) Engagement[Member Status]' => 'Opened'],
                                ['(raw) Engagement[Member Status]' => 'Clicked'],
                                ['(raw) Engagement[Member Status]' => 'Bounced'],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaign.metrics', ['campaignId' => '701Pl00000hB2yb']));

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'data' => ['sent', 'delivered', 'opened', 'clicked', 'bounced', 'open_rate', 'click_rate', 'bounce_rate'],
        ]);
});

test('campaign metrics endpoint requires authentication', function () {
    $response = $this->getJson(route('powerbi.campaign.metrics', ['campaignId' => '701Pl00000hB2yb']));

    $response->assertUnauthorized();
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

    $response = $this->actingAs($this->user)->getJson(route('powerbi.embed.token', ['reportId' => 'report-123']));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'token' => 'embed-token-123',
            ],
        ]);
});

test('embed token endpoint requires authentication', function () {
    $response = $this->getJson(route('powerbi.embed.token', ['reportId' => 'report-123']));

    $response->assertUnauthorized();
});

test('power bi endpoints are rate limited', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*' => Http::response(['value' => []]),
    ]);

    // Make 61 requests (limit is 60 per minute)
    for ($i = 0; $i < 61; $i++) {
        $response = $this->actingAs($this->user)->getJson(route('powerbi.campaigns'));

        if ($i < 60) {
            $response->assertOk();
        } else {
            $response->assertStatus(429); // Too Many Requests
        }
    }
});
