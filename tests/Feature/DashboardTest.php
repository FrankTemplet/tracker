<?php

use App\Models\User;
use App\Services\PowerBiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(false);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('campaigns')
            ->has('lastUpdated')
        );
});

test('dashboard shows error message when Power BI service fails', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->once()
        ->andThrow(new Exception('Power BI API error'));

    $user = User::factory()->create();
    $this->actingAs($user);

    // Must pass region AND year to trigger getUniqueCampaigns
    $response = $this->get(route('dashboard', ['region' => 'carib', 'year' => '2025']));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('campaigns', [])
            ->has('error')
        );
});

test('dashboard loads campaigns when region and year are selected', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->once()
        ->andReturn([
            [
                'campaign_id' => '701Pl00000hB2yb',
                'campaign_name' => 'CARIB_JAM_2025_Test',
                'business_unit' => 'CARIB',
                'start_date' => '2025-05-01',
            ],
        ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', ['region' => 'carib', 'year' => '2025']));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('campaigns', 1)
            ->where('selectedCampaignId', null)
        );
});

test('dashboard loads analytics when campaign is selected', function () {
    $campaignId = '701Pl00000hB2yb';

    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->once()
        ->andReturn([
            [
                'campaign_id' => $campaignId,
                'campaign_name' => 'CARIB_JAM_2025_Test',
                'business_unit' => 'CARIB',
                'start_date' => '2025-05-01',
            ],
        ])
        ->shouldReceive('getCampaignMetrics')
        ->once()
        ->with($campaignId)
        ->andReturn([
            'campaign_id' => $campaignId,
            'campaign_name' => 'CARIB_JAM_2025_Test',
            'segment' => 'Small - Medium',
            'summary' => [
                'delivered' => 150,
                'unique_opens' => 100,
                'open_rate' => 66.67,
                'unique_clicks' => 25,
                'click_rate' => 16.67,
                'unique_click_through_rate' => 16.67,
                'click_to_open_rate' => 25.0,
                'total_click_through_rate' => 14.0,
                'total_opens' => 130,
                'hard_bounces' => 5,
                'delivery_rate' => 96.77,
                'segment' => 'Small - Medium',
            ],
            'emails' => [],
        ])
        ->shouldReceive('getMembersByStatus')
        ->once()
        ->with($campaignId, 'unique-opens')
        ->andReturn([
            [
                'member_id' => '00vPl00000UmUCI',
                'first_name' => 'Shanequa',
                'last_name' => 'Hall',
                'email' => 'elloquentshanae@gmail.com',
                'company' => 'Drink Pure',
                'status_update_date' => '5/19/2025',
            ],
        ])
        ->shouldReceive('getMembersByStatus')
        ->once()
        ->with($campaignId, 'registered-appointment')
        ->andReturn([
            [
                'member_id' => '00vPl00000UmUFL',
                'first_name' => 'David',
                'last_name' => 'Johnson',
                'email' => 'david.j@example.com',
                'company' => 'Cloud Services',
                'status_update_date' => '5/19/2025',
            ],
        ])
        ->shouldReceive('getEngagementsByCampaign')
        ->once()
        ->with($campaignId)
        ->andReturn([
            [
                '(raw) Engagement[Primary Campaign Purpose]' => 'Test Purpose',
                '(raw) Engagement[Category]' => 'Test Category',
                '(raw) Engagement[Sub-Category]' => 'Test Sub-Category',
            ],
        ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', [
        'region' => 'carib',
        'year' => '2025',
        'campaign_id' => $campaignId,
    ]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('selectedCampaignId', $campaignId)
            ->has('analytics')
            ->where('analytics.campaign_id', $campaignId)
            ->where('analytics.summary.delivered', 150)
        );
});
