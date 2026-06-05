<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PowerBiControllerIntegrationTest extends TestCase
{
    public function test_campaigns_endpoint_returns_transformed_data(): void
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
                                        'REPORT - Campaign Tracker[Campaign Name]' => 'Prod_CloudSuite_Ent',
                                        'REPORT - Campaign Tracker[Date]' => '2025-05-14T00:00:00',
                                        'REPORT - Campaign Tracker[Status]' => 'In Progress',
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
                '*' => ['id', 'name', 'created_at'],
            ],
        ]);
        $response->assertJsonPath('data.0.name', 'Prod_CloudSuite_Ent');
    }

    public function test_campaign_emails_endpoint_returns_transformed_emails(): void
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
                                        'REPORT - Campaign Tracker[Campaign Name]' => 'Test_Campaign',
                                        'REPORT - Campaign Tracker[Subject]' => 'Welcome Email',
                                        'REPORT - Campaign Tracker[Scheduled Date]' => '2025-05-15T10:00:00',
                                        'REPORT - Campaign Tracker[Total Delivered]' => 100,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/powerbi/campaigns/Test_Campaign/emails');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'campaign_id', 'subject', 'from', 'to', 'sent_at'],
            ],
        ]);
        $response->assertJsonPath('data.0.subject', 'Welcome Email');
        $response->assertJsonPath('data.0.campaign_id', 'Test_Campaign');
    }

    public function test_email_analytics_endpoint(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->getJson('/api/powerbi/emails/test_email_id/analytics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['bounces', 'bounce_rate', 'opens', 'open_rate', 'clicks', 'click_rate'],
        ]);
    }
}
