<?php

use App\Services\DataverseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config([
        'dataverse.tenant_id' => 'fake-tenant',
        'dataverse.client_id' => 'fake-client',
        'dataverse.client_secret' => 'fake-secret',
        'dataverse.url' => 'https://org9e047986.api.crm.dynamics.com',
        'dataverse.api_version' => 'v9.2',
        'dataverse.token_url' => 'https://login.microsoftonline.com/fake-tenant/oauth2/v2.0/token',
        'dataverse.scope' => null,
        'dataverse.cache_ttl' => 1800,
        'dataverse.page_size' => 100,
    ]);
});

function fakeLogRow(array $overrides = []): array
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
        'cr21a_opencount' => 3,
        'cr21a_clickcount' => 2,
        'cr21a_hardbounced' => 0,
        'cr21a_softbounced' => 0,
    ], $overrides);
}

test('hasCredentials is false when configuration is missing', function () {
    config(['dataverse.client_secret' => null]);

    expect((new DataverseService)->hasCredentials())->toBeFalse();
});

test('getAccessToken requests a v2.0 token scoped to the environment url', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token-123']),
    ]);

    $token = (new DataverseService)->getAccessToken();

    expect($token)->toBe('dv-token-123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/oauth2/v2.0/token')
            && $request['scope'] === 'https://org9e047986.api.crm.dynamics.com/.default'
            && $request['grant_type'] === 'client_credentials';
    });
});

test('getAccessToken caches the token', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token-123']),
    ]);

    $service = new DataverseService;
    $service->getAccessToken();
    $service->getAccessToken();

    Http::assertSentCount(1);
});

test('getAccessToken throws on failed authentication', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401),
    ]);

    expect(fn () => (new DataverseService)->getAccessToken())
        ->toThrow(Exception::class, 'Failed to obtain Dataverse access token');
});

test('getEmailEngagementLogs filters by email name and campaign id', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response([
            '@odata.count' => 1,
            'value' => [fakeLogRow()],
        ]),
    ]);

    $result = (new DataverseService)->getEmailEngagementLogs(
        '701Pl00001TVgOQ',
        'CARIB_CAY_Event_SolutionSession_Ent_Apr2026_Register2',
    );

    expect($result['logs'])->toHaveCount(1)
        ->and($result['logs'][0]['recipient_email'])->toBe('Evana.martinez@gov.ky')
        ->and($result['logs'][0]['opens'])->toBe(3)
        ->and($result['logs'][0]['clicks'])->toBe(2)
        ->and($result['logs'][0]['delivered'])->toBe(1)
        ->and($result['total'])->toBe(1)
        ->and($result['next_cursor'])->toBeNull();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'cr21a_emailengagementlogs')) {
            return false;
        }

        $url = urldecode($request->url());

        return str_contains($url, "cr21a_emailname eq 'CARIB_CAY_Event_SolutionSession_Ent_Apr2026_Register2'")
            && str_contains($url, "startswith(cr21a_campaignid,'701Pl00001TVgOQ')")
            && str_contains($url, '$count=true')
            && $request->header('Prefer')[0] === 'odata.maxpagesize=100';
    });
});

test('getEmailEngagementLogs escapes single quotes in the filter', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['value' => []]),
    ]);

    (new DataverseService)->getEmailEngagementLogs('701Pl0', "O'Brien_Send");

    Http::assertSent(function ($request) {
        return str_contains(urldecode($request->url()), "cr21a_emailname eq 'O''Brien_Send'");
    });
});

test('getEmailEngagementLogs returns the skip token from the next link', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response([
            'value' => [fakeLogRow()],
            '@odata.nextLink' => 'https://org9e047986.api.crm.dynamics.com/api/data/v9.2/cr21a_emailengagementlogs?$select=cr21a_recipientemail&$skiptoken=%3Ccookie%20pagenumber%3D%222%22%2F%3E',
        ]),
    ]);

    $result = (new DataverseService)->getEmailEngagementLogs('701Pl0', 'Send1');

    expect($result['next_cursor'])->toBe('<cookie pagenumber="2"/>');
});

test('a cursor is sent back as a skiptoken on the next request', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['value' => []]),
    ]);

    (new DataverseService)->getEmailEngagementLogs('701Pl0', 'Send1', '<cookie pagenumber="2"/>');

    Http::assertSent(function ($request) {
        return str_contains(urldecode($request->url()), '$skiptoken=<cookie pagenumber="2"/>');
    });
});

test('getEmailEngagementLogs caches each page', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['value' => [fakeLogRow()]]),
    ]);

    $service = new DataverseService;
    $service->getEmailEngagementLogs('701Pl0', 'Send1');
    $service->getEmailEngagementLogs('701Pl0', 'Send1');

    // One token request plus one data request
    Http::assertSentCount(2);
});

test('getEmailEngagementLogs throws when the api fails', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['error' => ['message' => 'nope']], 403),
    ]);

    expect(fn () => (new DataverseService)->getEmailEngagementLogs('701Pl0', 'Send1'))
        ->toThrow(Exception::class, 'Failed to fetch Dataverse data');
});

test('getAllEmailEngagementLogs follows pagination to the end', function () {
    $nextLink = 'https://org9e047986.api.crm.dynamics.com/api/data/v9.2/cr21a_emailengagementlogs?$skiptoken=page2';

    Http::fakeSequence()
        ->push(['access_token' => 'dv-token'])
        ->push(['value' => [fakeLogRow(['cr21a_recipientemail' => 'a@example.com'])], '@odata.nextLink' => $nextLink])
        ->push(['value' => [fakeLogRow(['cr21a_recipientemail' => 'b@example.com'])]]);

    $logs = (new DataverseService)->getAllEmailEngagementLogs('701Pl0', 'Send1');

    expect($logs)->toHaveCount(2)
        ->and(array_column($logs, 'recipient_email'))->toBe(['a@example.com', 'b@example.com']);
});

test('a missing configuration throws instead of returning data', function () {
    config(['dataverse.client_id' => null]);
    Http::fake();

    expect(fn () => (new DataverseService)->getEmailEngagementLogs('701Pl0', 'Send1'))
        ->toThrow(Exception::class, 'Dataverse credentials are not configured.');

    Http::assertNothingSent();
});

test('getEmailEngagementLogs can restrict results to an engagement subset', function (string $engagement, string $predicate) {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['value' => [fakeLogRow()]]),
    ]);

    (new DataverseService)->getEmailEngagementLogs('701Pl0', 'Send1', null, null, $engagement);

    Http::assertSent(function ($request) use ($predicate) {
        if (! str_contains($request->url(), 'cr21a_emailengagementlogs')) {
            return false;
        }

        return str_contains(urldecode($request->url()), 'and '.$predicate);
    });
})->with([
    ['delivered', 'cr21a_delivered eq 1'],
    ['hard-bounced', 'cr21a_hardbounced eq 1'],
    ['clicked', 'cr21a_clickcount gt 0'],
]);

test('getEmailEngagementLogs ignores an unknown engagement filter', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['value' => [fakeLogRow()]]),
    ]);

    (new DataverseService)->getEmailEngagementLogs('701Pl0', 'Send1', null, null, 'soft-bounced');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'cr21a_emailengagementlogs')) {
            return false;
        }

        parse_str((string) parse_url(urldecode($request->url()), PHP_URL_QUERY), $query);

        return ! str_contains((string) ($query['$filter'] ?? ''), 'soft');
    });
});

test('getEmailEngagementLogs caches each engagement subset separately', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['value' => [fakeLogRow()]]),
    ]);

    $service = new DataverseService;
    $service->getEmailEngagementLogs('701Pl0', 'Send1');
    $service->getEmailEngagementLogs('701Pl0', 'Send1', null, null, 'delivered');
    $service->getEmailEngagementLogs('701Pl0', 'Send1', null, null, 'hard-bounced');

    Http::assertSentCount(4); // token + three distinct log queries
});

test('getEmailEngagementLogs filters with the 15-character campaign id', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response([
            '@odata.count' => 0,
            'value' => [],
        ]),
    ]);

    // cr21a_campaignid holds a mix of 15- and 18-character IDs (and some empty
    // values); the IDs coming from Power BI are always 18 characters, so the
    // filter has to match on the shared 15-character prefix.
    (new DataverseService)->getEmailEngagementLogs(
        '701Pl00001NdCmkIAF',
        'CARIB_BAR_Event_CybersecSummit_Ent_Mar2026',
    );

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'cr21a_emailengagementlogs')) {
            return false;
        }

        $url = urldecode($request->url());

        return str_contains($url, "startswith(cr21a_campaignid,'701Pl00001NdCmk')")
            && str_contains($url, 'cr21a_campaignid eq null')
            && ! str_contains($url, '701Pl00001NdCmkIAF');
    });
});

test('getSendNamesWithLogs groups every send in the log', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['value' => [
            ['cr21a_emailname' => 'Send1'],
            ['cr21a_emailname' => ' SEND2 '],
            ['cr21a_emailname' => ''],
        ]]),
    ]);

    expect((new DataverseService)->getSendNamesWithLogs())->toBe(['send1', 'send2']);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'cr21a_emailengagementlogs')) {
            return false;
        }

        parse_str((string) parse_url(urldecode($request->url()), PHP_URL_QUERY), $query);

        return ($query['$apply'] ?? '') === 'groupby((cr21a_emailname))';
    });
});

test('getSendNamesWithLogs narrows the group to one engagement subset', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['value' => [['cr21a_emailname' => 'Send1']]]),
    ]);

    expect((new DataverseService)->getSendNamesWithLogs('hard-bounced'))->toBe(['send1']);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'cr21a_emailengagementlogs')) {
            return false;
        }

        parse_str((string) parse_url(urldecode($request->url()), PHP_URL_QUERY), $query);

        return ($query['$apply'] ?? '') === 'filter(cr21a_hardbounced eq 1)/groupby((cr21a_emailname))';
    });
});

test('getSendNamesWithLogs caches each engagement subset separately', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'dv-token']),
        'org9e047986.api.crm.dynamics.com/*' => Http::response(['value' => [['cr21a_emailname' => 'Send1']]]),
    ]);

    $service = new DataverseService;
    $service->getSendNamesWithLogs();
    $service->getSendNamesWithLogs();
    $service->getSendNamesWithLogs('clicked');
    $service->getSendNamesWithLogs('hard-bounced');

    Http::assertSentCount(4); // token + three distinct coverage queries
});
