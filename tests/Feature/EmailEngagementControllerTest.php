<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->create();

    config([
        'powerbi.client_id' => 'pbi-client',
        'powerbi.client_secret' => 'pbi-secret',
        'powerbi.tenant_id' => 'pbi-tenant',
        'dataverse.client_id' => 'dv-client',
        'dataverse.client_secret' => 'dv-secret',
        'dataverse.tenant_id' => 'dv-tenant',
        'dataverse.url' => 'https://org9e047986.api.crm.dynamics.com',
        'dataverse.api_version' => 'v9.2',
        'dataverse.token_url' => 'https://login.microsoftonline.com/dv-tenant/oauth2/v2.0/token',
    ]);
});

/**
 * Fake the campaign list used to authorize the request.
 */
function fakeCampaigns(array $campaigns): array
{
    $rows = array_map(fn (array $c) => [
        '(raw) Engagement[Campaign ID]' => $c['id'],
        '(raw) Engagement[Campaign Name]' => $c['name'],
        '(raw) Engagement[Reporting Business Unit]' => 'CaribRegional',
        '(raw) Engagement[Start Date]' => '4/30/2026',
    ], $campaigns);

    return ['results' => [['tables' => [['rows' => $rows]]]]];
}

function fakeDataverseLog(array $overrides = []): array
{
    return array_merge([
        'cr21a_emailengagementlogid' => 'd446cfe1-2076-f111-ab0f-7ced8d3c08f7',
        'cr21a_recipientemail' => 'Evana.martinez@gov.ky',
        'cr21a_emailname' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026_Register2',
        'cr21a_emailsubject' => 'See you soon!',
        'cr21a_campaignid' => '701Pl00001TVgOQ',
        'cr21a_campaignname' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026',
        'cr21a_listemailid' => '0XBPl000000upnp',
        'cr21a_prospectid' => '126726858',
        'cr21a_datesent' => '2026-04-30T13:57:00Z',
        'cr21a_delivered' => 1,
        'cr21a_opencount' => 2,
        'cr21a_clickcount' => 1,
        'cr21a_hardbounced' => 0,
        'cr21a_softbounced' => 0,
    ], $overrides);
}

test('returns engagement logs for a carib campaign', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token']),
        'api.powerbi.com/*' => Http::response(fakeCampaigns([
            ['id' => '701Pl00001TVgOQ', 'name' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026'],
        ])),
        'org9e047986.api.crm.dynamics.com/*' => Http::response([
            '@odata.count' => 1,
            'value' => [fakeDataverseLog()],
        ]),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('dataverse.campaign.email-logs', [
        'campaignId' => '701Pl00001TVgOQ',
        'email_name' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026_Register2',
    ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'total' => 1,
            'next_cursor' => null,
        ])
        ->assertJsonPath('data.0.recipient_email', 'Evana.martinez@gov.ky')
        ->assertJsonPath('data.0.opens', 2)
        ->assertJsonPath('data.0.clicks', 1);
});

test('passes the cursor through and returns the next one', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token']),
        'api.powerbi.com/*' => Http::response(fakeCampaigns([
            ['id' => '701Pl00001TVgOQ', 'name' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026'],
        ])),
        'org9e047986.api.crm.dynamics.com/*' => Http::response([
            'value' => [fakeDataverseLog()],
            '@odata.nextLink' => 'https://org9e047986.api.crm.dynamics.com/api/data/v9.2/cr21a_emailengagementlogs?$skiptoken=page3',
        ]),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('dataverse.campaign.email-logs', [
        'campaignId' => '701Pl00001TVgOQ',
        'email_name' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026_Register2',
        'cursor' => 'page2',
    ]));

    $response->assertOk()->assertJsonPath('next_cursor', 'page3');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'cr21a_emailengagementlogs')
            && str_contains(urldecode($request->url()), '$skiptoken=page2');
    });
});

test('rejects a campaign outside the user regions', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token']),
        'api.powerbi.com/*' => Http::response(fakeCampaigns([
            ['id' => '701Pl00001LATAM', 'name' => 'LATAM_MEX_Event_Something_Apr2026'],
        ])),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('dataverse.campaign.email-logs', [
        'campaignId' => '701Pl00001LATAM',
        'email_name' => 'LATAM_MEX_Event_Something_Apr2026_Register1',
    ]));

    $response->assertForbidden();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'dynamics.com'));
});

test('rejects an unknown campaign', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token']),
        'api.powerbi.com/*' => Http::response(fakeCampaigns([
            ['id' => '701Pl00001TVgOQ', 'name' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026'],
        ])),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('dataverse.campaign.email-logs', [
        'campaignId' => 'does-not-exist',
        'email_name' => 'Whatever_Register1',
    ]));

    $response->assertForbidden();
});

test('rejects a non carib campaign even for an admin', function () {
    $admin = User::factory()->admin()->create();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token']),
        'api.powerbi.com/*' => Http::response(fakeCampaigns([
            ['id' => '701Pl00001NETW', 'name' => 'NETWORKS_Event_Something_Apr2026'],
        ])),
    ]);

    $response = $this->actingAs($admin)->getJson(route('dataverse.campaign.email-logs', [
        'campaignId' => '701Pl00001NETW',
        'email_name' => 'NETWORKS_Event_Something_Apr2026_Register1',
    ]));

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Recipient engagement is only available for Carib campaigns.');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'dynamics.com'));
});

test('requires an email name', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token']),
        'api.powerbi.com/*' => Http::response(fakeCampaigns([
            ['id' => '701Pl00001TVgOQ', 'name' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026'],
        ])),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('dataverse.campaign.email-logs', ['campaignId' => '701Pl00001TVgOQ']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('email_name');
});

test('returns a 500 when dataverse fails', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token']),
        'api.powerbi.com/*' => Http::response(fakeCampaigns([
            ['id' => '701Pl00001TVgOQ', 'name' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026'],
        ])),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['error' => 'nope'], 403),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('dataverse.campaign.email-logs', [
            'campaignId' => '701Pl00001TVgOQ',
            'email_name' => 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026_Register2',
        ]))
        ->assertStatus(500)
        ->assertJsonPath('success', false);
});

test('requires authentication', function () {
    $this->getJson(route('dataverse.campaign.email-logs', [
        'campaignId' => '701Pl00001TVgOQ',
        'email_name' => 'Something',
    ]))->assertUnauthorized();
});
