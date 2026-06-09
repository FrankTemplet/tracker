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
        ->shouldReceive('getEngagementsByCampaign')
        ->once()
        ->with($campaignId)
        ->andReturn([
            ['(raw) Engagement[Member Status]' => 'Opened'],
            ['(raw) Engagement[Member Status]' => 'Opened'],
            ['(raw) Engagement[Member Status]' => 'Clicked'],
            ['(raw) Engagement[Member Status]' => 'Bounced'],
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
            ->has('analytics')
            ->where('selectedCampaignId', $campaignId)
        );
});
