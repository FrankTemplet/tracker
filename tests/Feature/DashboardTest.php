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
        ->shouldReceive('hasCredentials')
        ->andReturn(true)
        ->shouldReceive('getCampaigns')
        ->once()
        ->andReturn([
            ['id' => 'campaign-1', 'name' => 'Test Campaign'],
        ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('campaigns', 1)
            ->has('emails')
            ->has('lastUpdated')
            ->where('isDemoMode', false)
        );
});

test('dashboard shows error message when Power BI service fails', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')
        ->andReturn(true)
        ->shouldReceive('getCampaigns')
        ->once()
        ->andThrow(new Exception('Power BI API error'));

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('campaigns', [])
            ->has('error')
        );
});

test('dashboard loads emails when campaign is selected', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')
        ->andReturn(true)
        ->shouldReceive('getCampaigns')
        ->once()
        ->andReturn([
            ['id' => 'campaign-1', 'name' => 'Test Campaign'],
        ])
        ->shouldReceive('getCampaignEmails')
        ->once()
        ->with('campaign-1')
        ->andReturn([
            [
                'id' => 'email-1',
                'campaign_id' => 'campaign-1',
                'subject' => 'Test Email',
                'from' => 'test@example.com',
                'to' => 'user@example.com',
                'sent_at' => '2026-05-14 12:00:00',
            ],
        ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', ['campaign_id' => 'campaign-1']));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('campaigns', 1)
            ->has('emails', 1)
            ->where('selectedCampaignId', 'campaign-1')
        );
});

test('dashboard loads analytics when email is selected', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('hasCredentials')
        ->andReturn(true)
        ->shouldReceive('getCampaigns')
        ->once()
        ->andReturn([
            ['id' => 'campaign-1', 'name' => 'Test Campaign'],
        ])
        ->shouldReceive('getCampaignEmails')
        ->once()
        ->with('campaign-1')
        ->andReturn([
            [
                'id' => 'email-1',
                'campaign_id' => 'campaign-1',
                'subject' => 'Test Email',
            ],
        ])
        ->shouldReceive('getEmailAnalytics')
        ->once()
        ->with('email-1')
        ->andReturn([
            'bounces' => 5,
            'bounce_rate' => 2.5,
            'opens' => 150,
            'open_rate' => 75.0,
            'clicks' => 80,
            'click_rate' => 40.0,
        ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', [
        'campaign_id' => 'campaign-1',
        'email_id' => 'email-1',
    ]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('analytics')
            ->where('selectedCampaignId', 'campaign-1')
            ->where('selectedEmailId', 'email-1')
        );
});
