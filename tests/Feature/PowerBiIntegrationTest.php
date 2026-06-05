<?php

namespace Tests\Feature;

use App\Services\PowerBiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PowerBiIntegrationTest extends TestCase
{
    protected PowerBiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PowerBiService::class);
    }

    public function test_power_bi_credentials_are_configured(): void
    {
        $this->assertTrue(
            $this->service->hasCredentials(),
            'Power BI credentials should be configured via .env'
        );
    }

    public function test_access_token_request_format_is_correct(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'fake_token_abc123',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);

        $token = $this->service->getAccessToken();

        $this->assertEquals('fake_token_abc123', $token);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'login.microsoftonline.com')
                && $request->method() === 'POST';
        });
    }

    public function test_campaigns_are_fetched_from_execute_queries_endpoint(): void
    {
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
                                        'REPORT - Campaign Tracker[Campaign]' => 'Prod_CloudSuite_Ent',
                                        'REPORT - Campaign Tracker[Full Campaign Name]' => 'CARIB_JAM_Prod_CloudSuite_Ent_May2025',
                                        'REPORT - Campaign Tracker[Status]' => 'In Progress',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $campaigns = $this->service->getCampaigns();

        $this->assertCount(1, $campaigns);
        $this->assertEquals('Prod_CloudSuite_Ent', $campaigns[0]['REPORT - Campaign Tracker[Campaign]']);
    }

    public function test_emails_are_fetched_for_a_campaign(): void
    {
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
                                        '(raw email) Campaign%20Outcomes%20AllLiberty[Campaign Name]' => 'CARIB_TRI_Prod_CloudBundle_Ent_Sep2025',
                                        '(raw email) Campaign%20Outcomes%20AllLiberty[Subject]' => 'Take your business to the next level with Cloud',
                                        '(raw email) Campaign%20Outcomes%20AllLiberty[Total Delivered]' => 520,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $emails = $this->service->getCampaignEmails('CARIB_TRI_Prod_CloudBundle_Ent_Sep2025');

        $this->assertCount(1, $emails);
        $this->assertEquals('CARIB_TRI_Prod_CloudBundle_Ent_Sep2025', $emails[0]['(raw email) Campaign%20Outcomes%20AllLiberty[Campaign Name]']);
    }

    public function test_email_campaign_names_are_fetched(): void
    {
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
                                        '(raw email) Campaign%20Outcomes%20AllLiberty[Campaign Name]' => 'CARIB_BAH_Newsletter_Apr2025',
                                    ],
                                    [
                                        '(raw email) Campaign%20Outcomes%20AllLiberty[Campaign Name]' => 'CARIB_JAM_Update_Feb2025',
                                    ],
                                    [
                                        '(raw email) Campaign%20Outcomes%20AllLiberty[Campaign Name]' => 'CARIB_BAH_Newsletter_Apr2025',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $names = $this->service->getEmailCampaignNames();

        $this->assertCount(2, $names);
        $this->assertContains('CARIB_BAH_Newsletter_Apr2025', $names);
        $this->assertContains('CARIB_JAM_Update_Feb2025', $names);
    }
}
