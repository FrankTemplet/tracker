<?php

use App\Models\User;
use App\Services\PowerBiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('campaigns endpoint returns list of campaigns for authenticated user', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/Campaigns/rows' => Http::response([
            'value' => [
                ['id' => 'campaign-1', 'name' => 'Summer Sale'],
                ['id' => 'campaign-2', 'name' => 'Newsletter May'],
            ],
        ]),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaigns'));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['id' => 'campaign-1', 'name' => 'Summer Sale'],
                ['id' => 'campaign-2', 'name' => 'Newsletter May'],
            ],
        ]);
});

test('campaigns endpoint requires authentication', function () {
    $response = $this->getJson(route('powerbi.campaigns'));

    $response->assertUnauthorized();
});

test('campaigns endpoint returns error on service failure', function () {
    $this->mock(PowerBiService::class)
        ->shouldReceive('getCampaigns')
        ->once()
        ->andThrow(new Exception('Power BI API error'));

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaigns'));

    $response->assertStatus(500)
        ->assertJson([
            'success' => false,
            'message' => 'Failed to fetch campaigns. Please try again later.',
        ]);
});

test('campaign emails endpoint returns filtered emails', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/SentEmails/rows' => Http::response([
            'value' => [
                [
                    'id' => 'email-1',
                    'campaign_id' => 'campaign-1',
                    'subject' => 'Welcome Email',
                ],
                [
                    'id' => 'email-2',
                    'campaign_id' => 'campaign-1',
                    'subject' => 'Follow-up Email',
                ],
            ],
        ]),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('powerbi.campaign.emails', ['campaignId' => 'campaign-1']));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['id' => 'email-1', 'subject' => 'Welcome Email'],
                ['id' => 'email-2', 'subject' => 'Follow-up Email'],
            ],
        ]);
});

test('campaign emails endpoint requires authentication', function () {
    $response = $this->getJson(route('powerbi.campaign.emails', ['campaignId' => 'campaign-1']));

    $response->assertUnauthorized();
});

test('email analytics endpoint returns analytics data', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/EmailAnalytics/rows' => Http::response([
            'value' => [
                [
                    'email_id' => 'email-1',
                    'bounces' => 5,
                    'bounce_rate' => 2.5,
                    'opens' => 150,
                    'open_rate' => 75.0,
                    'clicks' => 80,
                    'click_rate' => 40.0,
                ],
            ],
        ]),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('powerbi.email.analytics', ['emailId' => 'email-1']));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'bounces' => 5,
                'opens' => 150,
                'clicks' => 80,
            ],
        ]);
});

test('email analytics endpoint requires authentication', function () {
    $response = $this->getJson(route('powerbi.email.analytics', ['emailId' => 'email-1']));

    $response->assertUnauthorized();
});

test('embed token endpoint returns token', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
        'api.powerbi.com/*/GenerateToken' => Http::response([
            'token' => 'embed-token-123',
        ]),
    ]);

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
