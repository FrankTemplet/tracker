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

    public function test_unique_campaigns_are_fetched_from_execute_queries_endpoint(): void
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

        $campaigns = $this->service->getUniqueCampaigns();

        $this->assertCount(1, $campaigns);
        $this->assertEquals('701Pl00000hB2yb', $campaigns[0]['campaign_id']);
        $this->assertEquals('CARIB_JAM_Prod_CloudSuite_Ent_May2025', $campaigns[0]['campaign_name']);
    }

    public function test_engagements_are_fetched_for_a_campaign(): void
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
                                        '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
                                        '(raw) Engagement[Campaign Name]' => 'CARIB_TRI_Prod_CloudBundle_Ent_Sep2025',
                                        '(raw) Engagement[Member Status]' => 'Opened',
                                        '(raw) Engagement[First Name]' => 'John',
                                        '(raw) Engagement[Last Name]' => 'Doe',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $engagements = $this->service->getEngagementsByCampaign('701Pl00000hB2yb');

        $this->assertCount(1, $engagements);
        $this->assertEquals('701Pl00000hB2yb', $engagements[0]['(raw) Engagement[Campaign ID]']);
        $this->assertEquals('Opened', $engagements[0]['(raw) Engagement[Member Status]']);
    }

    public function test_members_by_status_are_fetched(): void
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

        $members = $this->service->getMembersByStatus('701Pl00000hB2yb', 'Opened');

        $this->assertCount(2, $members);
        $this->assertEquals('00vPl00000UmUCI', $members[0]['member_id']);
        $this->assertEquals('Shanequa', $members[0]['first_name']);
        $this->assertEquals('shanequa@example.com', $members[0]['email']);
        $this->assertEquals('John', $members[1]['first_name']);
    }
}
