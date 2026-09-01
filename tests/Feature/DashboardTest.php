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
        ->shouldReceive('hasCredentials')->andReturn(false)
        ->shouldReceive('getUniqueCampaigns')->andReturn([]);

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
            // No campaign_id in the query, so the page opens one on its own
            ->where('selectedCampaignId', '701Pl00000hB2yb')
        );
});

test('campaigns are split between dashboard, events and webinars pages', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->andReturn([
            [
                'campaign_id' => '701CARIB0000001',
                'campaign_name' => 'CARIB_JAM_2025_Test',
                'business_unit' => 'CARIB',
                'start_date' => '2025-05-01',
            ],
            [
                'campaign_id' => '701CARIB0000002',
                'campaign_name' => 'CARIB_EVENT_2025_Launch',
                'business_unit' => 'CARIB',
                'start_date' => '2025-05-01',
            ],
            [
                'campaign_id' => '701CARIB0000003',
                'campaign_name' => 'CARIB_Webinar_2025_Cloud',
                'business_unit' => 'CARIB',
                'start_date' => '2025-05-01',
            ],
        ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $params = ['region' => 'carib', 'year' => '2025'];

    $this->get(route('dashboard', $params))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('campaigns', 1)
            ->where('campaigns.0.name', 'CARIB_JAM_2025_Test')
        );

    $this->get(route('events', $params))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events')
            ->has('campaigns', 1)
            ->where('campaigns.0.name', 'CARIB_EVENT_2025_Launch')
        );

    $this->get(route('webinars', $params))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('webinars')
            ->has('campaigns', 1)
            ->where('campaigns.0.name', 'CARIB_Webinar_2025_Cloud')
        );
});

test('networks users see networks and latam campaigns but not carib', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->andReturn([
            [
                'campaign_id' => '701CARIB0000001',
                'campaign_name' => 'CARIB_JAM_2025_Test',
                'business_unit' => 'CARIB',
                'start_date' => '2025-05-01',
            ],
            [
                'campaign_id' => '701LATAM0000001',
                'campaign_name' => 'LATAM_MX_2025_Test',
                'business_unit' => 'LATAM',
                'start_date' => '2025-05-01',
            ],
            [
                'campaign_id' => '701NETW00000001',
                'campaign_name' => 'NETWORKS_2025_Test',
                'business_unit' => 'Networks',
                'start_date' => '2025-05-01',
            ],
        ]);

    $user = User::factory()->networks()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', ['region' => 'latam', 'year' => '2025']));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('campaigns', 1)
            ->where('campaigns.0.name', 'LATAM_MX_2025_Test')
            ->where('availableRegions', ['networks', 'latam'])
        );
});

test('carib users cannot filter by a region they are not assigned to', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->andReturn([
            [
                'campaign_id' => '701LATAM0000001',
                'campaign_name' => 'LATAM_MX_2025_Test',
                'business_unit' => 'LATAM',
                'start_date' => '2025-05-01',
            ],
        ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    // Region "latam" is not allowed for carib users, so it is dropped and the
    // page falls back to the user's own region — never to latam data
    $response = $this->get(route('dashboard', ['region' => 'latam', 'year' => '2025']));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('selectedRegion', 'carib')
            ->where('campaigns', [])
            ->where('selectedCampaignId', null)
            ->where('availableRegions', ['carib'])
        );
});

test('dashboard ignores years outside 2025 and 2026', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')->andReturn([]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', ['region' => 'carib', 'year' => '2024']));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('selectedRegion', 'carib')
            // 2024 is rejected and replaced by an allowed year rather than
            // leaving the page with nothing to show
            ->where('selectedYear', '2026')
            ->where('campaigns', [])
        );
});

test('an out of range year is left alone when the empty state is requested', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')->never();

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', ['region' => 'carib', 'year' => '2024', 'clear' => 1]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('selectedYear', null)
            ->where('campaigns', [])
        );
});

test('campaigns outside the user region cannot be selected on the dashboard', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->andReturn([
            [
                'campaign_id' => '701CARIB0000001',
                'campaign_name' => 'CARIB_JAM_2025_Test',
                'business_unit' => 'CARIB',
                'start_date' => '2025-05-01',
            ],
            [
                'campaign_id' => '701LATAM0000001',
                'campaign_name' => 'LATAM_MX_2025_Test',
                'business_unit' => 'LATAM',
                'start_date' => '2025-05-01',
            ],
        ])
        ->shouldReceive('getCampaignMetrics')
        ->with('701CARIB0000001')
        ->andReturn(null);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', [
        'region' => 'carib',
        'year' => '2025',
        'campaign_id' => '701LATAM0000001',
    ]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            // The forbidden id is discarded; what replaces it is the user's own
            // campaign, never the latam one they asked for
            ->where('selectedCampaignId', '701CARIB0000001')
            ->where('analytics', null)
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

test('a first visit opens on a campaign instead of an empty page', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->andReturn([
            [
                'campaign_id' => '701CARIB0000001',
                'campaign_name' => 'CARIB_JAM_2026_Old',
                'business_unit' => 'CARIB',
                'start_date' => '2026-01-15',
            ],
            [
                'campaign_id' => '701CARIB0000002',
                'campaign_name' => 'CARIB_JAM_2026_Newest',
                'business_unit' => 'CARIB',
                'start_date' => '2026-07-20',
            ],
        ])
        ->shouldReceive('getCampaignMetrics')
        ->with('701CARIB0000002')
        ->andReturn(null);

    $user = User::factory()->create();
    $this->actingAs($user);

    // No query string at all — the landing case the portal used to show blank
    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('selectedRegion', 'carib')
            ->where('selectedYear', '2026')
            // The most recent campaign by start date, not the first in the list
            ->where('selectedCampaignId', '701CARIB0000002')
        );
});

test('the default year falls back to one that has campaigns', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->andReturn([
            [
                'campaign_id' => '701CARIB0000001',
                'campaign_name' => 'CARIB_JAM_2025_Only',
                'business_unit' => 'CARIB',
                'start_date' => '2025-05-01',
            ],
        ])
        ->shouldReceive('getCampaignMetrics')
        ->with('701CARIB0000001')
        ->andReturn(null);

    $user = User::factory()->create();
    $this->actingAs($user);

    // Nothing exists for the current year, so the page walks back a year rather
    // than landing the user on an empty one
    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('selectedYear', '2025')
            ->where('selectedCampaignId', '701CARIB0000001')
        );
});

test('clearing the filters leaves the page empty on purpose', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')->never()
        ->shouldReceive('getCampaignMetrics')->never();

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', ['clear' => 1]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('selectedRegion', null)
            ->where('selectedYear', null)
            ->where('selectedCampaignId', null)
            ->where('campaigns', [])
        );
});

test('events and webinars also open on a campaign of their own', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')->andReturn(true)
        ->shouldReceive('getUniqueCampaigns')
        ->andReturn([
            [
                'campaign_id' => '701CARIB0000002',
                'campaign_name' => 'CARIB_EVENT_2026_Launch',
                'business_unit' => 'CARIB',
                'start_date' => '2026-03-01',
            ],
            [
                'campaign_id' => '701CARIB0000003',
                'campaign_name' => 'CARIB_Webinar_2026_Cloud',
                'business_unit' => 'CARIB',
                'start_date' => '2026-04-01',
            ],
        ])
        ->shouldReceive('getCampaignMetrics')
        ->andReturn(null);

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('events'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events')
            ->where('selectedCampaignId', '701CARIB0000002')
        );

    $this->get(route('webinars'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('webinars')
            ->where('selectedCampaignId', '701CARIB0000003')
        );
});
