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
                                    ['(raw) Engagement[Member Status]' => 'Sent'],
                                    ['(raw) Engagement[Member Status]' => 'Sent'],
                                    ['(raw) Engagement[Member Status]' => 'Sent'],
                                    ['(raw) Engagement[Member Status]' => 'Sent'],
                                    ['(raw) Engagement[Member Status]' => 'Sent'],
                                    ['(raw) Engagement[Member Status]' => 'Opened'],
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

        $response = $this->getJson('/api/powerbi/campaigns/701Pl00000hB2yb/metrics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['sent', 'delivered', 'opened', 'clicked', 'bounced', 'open_rate', 'click_rate', 'bounce_rate'],
        ]);
        $response->assertJsonPath('data.sent', 9);  // total record count = sent
        $response->assertJsonPath('data.opened', 3);  // 2 Opened + 1 Clicked (implicit open)
        $response->assertJsonPath('data.clicked', 1);
        $response->assertJsonPath('data.bounced', 1);
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
