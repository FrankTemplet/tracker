<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PowerBiControllerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaigns_endpoint_returns_transformed_unique_campaigns(): void
    {
        $this->actingAs(User::factory()->create());

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'fake_token_abc123',
            ]),
            'api.powerbi.com/*' => Http::response([
                'results' => [
                    [
                        'tables' => [
                            [
                                'rows' => [
                                    [
                                        '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
                                        '(raw) Engagement[Campaign Name]' => 'CARIB_JAM_Prod_CloudSuite_Ent_May2025',
                                        '(raw) Engagement[Reporting Business Unit]' => 'CaribRegional',
                                        '(raw) Engagement[Start Date]' => '5/5/2025',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/powerbi/campaigns');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'name', 'business_unit', 'created_at'],
            ],
        ]);
        $response->assertJsonPath('data.0.id', '701Pl00000hB2yb');
        $response->assertJsonPath('data.0.name', 'CARIB_JAM_Prod_CloudSuite_Ent_May2025');
    }

    public function test_campaign_metrics_endpoint_returns_aggregated_metrics(): void
    {
        $this->actingAs(User::factory()->create());

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'fake_token_abc123',
            ]),
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
                                        '(raw) Email Campaign Metrics[Total Delivered]' => 150,
                                        '(raw) Email Campaign Metrics[Unique Opens]' => 100,
                                        '(raw) Email Campaign Metrics[Open Rate]' => 66.67,
                                        '(raw) Email Campaign Metrics[Unique Clicks]' => 25,
                                        '(raw) Email Campaign Metrics[Unique Click Through Rate]' => 16.67,
                                        '(raw) Email Campaign Metrics[Click To Open Ratio]' => 25,
                                        '(raw) Email Campaign Metrics[Total Click Through Rate]' => 12,
                                        '(raw) Email Campaign Metrics[Total Opens]' => 130,
                                        '(raw) Email Campaign Metrics[Total Hard Bounces]' => 5,
                                        '(raw) Email Campaign Metrics[Delivery Rate]' => 96.77,
                                        '(raw) Email Campaign Metrics[Segment]' => 'Enterprise',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/powerbi/campaigns/701Pl00000hB2yb/metrics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
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
        $response->assertJsonPath('data.summary.delivered', 150);
        $response->assertJsonPath('data.summary.unique_opens', 100);
        $response->assertJsonPath('data.summary.unique_clicks', 25);
        $response->assertJsonPath('data.summary.hard_bounces', 5);
    }

    public function test_campaign_members_endpoint_returns_member_list(): void
    {
        $this->actingAs(User::factory()->create());

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'fake_token_abc123',
            ]),
            'api.powerbi.com/*' => Http::response([
                'results' => [
                    [
                        'tables' => [
                            [
                                'rows' => [
                                    [
                                        '(raw) Engagement[Member ID]' => '00vPl00000UmUCI',
                                        '(raw) Engagement[First Name]' => 'Shanequa',
                                        '(raw) Engagement[Last Name]' => 'Hall',
                                        '(raw) Engagement[Email]' => 'shanequa@example.com',
                                        '(raw) Engagement[Company]' => 'Drink Pure',
                                        '(raw) Engagement[Member Status Update Date]' => '5/19/2025',
                                    ],
                                    [
                                        '(raw) Engagement[Member ID]' => '00vPl00000UmUDJ',
                                        '(raw) Engagement[First Name]' => 'John',
                                        '(raw) Engagement[Last Name]' => 'Smith',
                                        '(raw) Engagement[Email]' => 'john@example.com',
                                        '(raw) Engagement[Company]' => 'Tech Corp',
                                        '(raw) Engagement[Member Status Update Date]' => '5/20/2025',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/powerbi/campaigns/701Pl00000hB2yb/members/Opened');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['member_id', 'first_name', 'last_name', 'email', 'company', 'status_update_date'],
            ],
        ]);
        $response->assertJsonPath('data.0.first_name', 'Shanequa');
        $response->assertJsonPath('data.0.email', 'shanequa@example.com');
        $response->assertJsonPath('data.1.first_name', 'John');
    }
}
