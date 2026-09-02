<?php

use App\Services\PowerBiDataTransformer;
use Carbon\CarbonImmutable;

test('transformCampaigns formats unique campaign data correctly', function () {
    $uniqueCampaigns = [
        [
            'campaign_id' => '701Pl00000hB2yb',
            'campaign_name' => 'CARIB_JAM_Info_CustomerCare_MSeg_May2025',
            'business_unit' => 'CaribRegional',
            'start_date' => '5/5/2025',
        ],
        [
            'campaign_id' => '701Pl00000hB3xc',
            'campaign_name' => 'Summer_Sale_2026_Enterprise',
            'business_unit' => 'North America',
            'start_date' => '5/15/2025',
        ],
    ];

    $transformed = PowerBiDataTransformer::transformCampaigns($uniqueCampaigns);

    expect($transformed)->toHaveCount(2)
        ->and($transformed[0]['id'])->toBe('701Pl00000hB2yb')
        ->and($transformed[0]['name'])->toBe('CARIB_JAM_Info_CustomerCare_MSeg_May2025')
        ->and($transformed[0]['business_unit'])->toBe('CaribRegional')
        ->and($transformed[0]['created_at'])->toBe('5/5/2025')
        ->and($transformed[1]['id'])->toBe('701Pl00000hB3xc')
        ->and($transformed[1]['name'])->toBe('Summer_Sale_2026_Enterprise');
});

test('aggregateEngagementsByCampaign calculates metrics correctly', function () {
    $engagements = [
        ['(raw) Engagement[Campaign ID]' => 'camp1', '(raw) Engagement[Campaign Name]' => 'Campaign 1', '(raw) Engagement[Member Status]' => 'Sent', '(raw) Engagement[Member ID]' => 'm1'],
        ['(raw) Engagement[Campaign ID]' => 'camp1', '(raw) Engagement[Campaign Name]' => 'Campaign 1', '(raw) Engagement[Member Status]' => 'Opened', '(raw) Engagement[Member ID]' => 'm2'],
        ['(raw) Engagement[Campaign ID]' => 'camp1', '(raw) Engagement[Campaign Name]' => 'Campaign 1', '(raw) Engagement[Member Status]' => 'Clicked', '(raw) Engagement[Member ID]' => 'm3'],
        ['(raw) Engagement[Campaign ID]' => 'camp1', '(raw) Engagement[Campaign Name]' => 'Campaign 1', '(raw) Engagement[Member Status]' => 'Bounced', '(raw) Engagement[Member ID]' => 'm4'],
        ['(raw) Engagement[Campaign ID]' => 'camp2', '(raw) Engagement[Campaign Name]' => 'Campaign 2', '(raw) Engagement[Member Status]' => 'Sent', '(raw) Engagement[Member ID]' => 'm5'],
        ['(raw) Engagement[Campaign ID]' => 'camp2', '(raw) Engagement[Campaign Name]' => 'Campaign 2', '(raw) Engagement[Member Status]' => 'Opened', '(raw) Engagement[Member ID]' => 'm6'],
    ];

    $aggregated = PowerBiDataTransformer::aggregateEngagementsByCampaign($engagements);

    expect($aggregated)->toHaveKeys(['camp1', 'camp2'])
        ->and($aggregated['camp1']['total_sent'])->toBe(1)
        ->and($aggregated['camp1']['total_opened'])->toBe(1)
        ->and($aggregated['camp1']['total_clicked'])->toBe(1)
        ->and($aggregated['camp1']['total_bounced'])->toBe(1)
        ->and($aggregated['camp1']['unique_members'])->toBe(4)
        ->and($aggregated['camp2']['total_sent'])->toBe(1)
        ->and($aggregated['camp2']['total_opened'])->toBe(1)
        ->and($aggregated['camp2']['unique_members'])->toBe(2);
});

test('aggregateCampaignMetrics calculates rates correctly', function () {
    // Real Power BI data: status is the highest engagement level reached.
    // "Clicked" implies the member also opened (Clicked > Opened > Sent).
    $engagements = [
        ['(raw) Engagement[Member Status]' => 'Opened', '(raw) Engagement[Primary Campaign Purpose]' => 'Lead Generation', '(raw) Engagement[Category]' => 'Email', '(raw) Engagement[Sub-Category]' => 'Offer', '(raw) Engagement[Segment]' => 'Small - Medium', '(raw) Engagement[Opportunities in Campaign]' => 0],
        ['(raw) Engagement[Member Status]' => 'Opened'],
        ['(raw) Engagement[Member Status]' => 'Opened'],
        ['(raw) Engagement[Member Status]' => 'Opened'],
        ['(raw) Engagement[Member Status]' => 'Opened'], // 5 opened-only
        ['(raw) Engagement[Member Status]' => 'Clicked'],
        ['(raw) Engagement[Member Status]' => 'Clicked'], // 2 clicked (also opens)
        ['(raw) Engagement[Member Status]' => 'Bounced'], // 1 bounced
        ['(raw) Engagement[Member Status]' => 'Other'],
        ['(raw) Engagement[Member Status]' => 'Other'],
    ];

    // sent = 10 (total), bounced = 1, delivered = 9
    // opened = 5 (Opened) + 2 (Clicked, implicit open) = 7
    // clicked = 2
    // open_rate  = round(7/9*100, 2) = 77.78
    // click_rate = round(2/9*100, 2) = 22.22
    // bounce_rate = round(1/10*100, 2) = 10.0
    $metrics = PowerBiDataTransformer::aggregateCampaignMetrics($engagements);

    expect($metrics['sent'])->toBe(10)
        ->and($metrics['opened'])->toBe(7)  // 5 Opened + 2 Clicked
        ->and($metrics['clicked'])->toBe(2)
        ->and($metrics['bounced'])->toBe(1)
        ->and($metrics['delivered'])->toBe(9)
        ->and($metrics['open_rate'])->toBe(77.78) // 7 / 9 * 100
        ->and($metrics['click_rate'])->toBe(22.22) // 2 / 9 * 100
        ->and($metrics['bounce_rate'])->toBe(10.0) // 1 / 10 * 100
        ->and($metrics['primary_purpose'])->toBe('Lead Generation')
        ->and($metrics['category'])->toBe('Email')
        ->and($metrics['sub_category'])->toBe('Offer')
        ->and($metrics['segment'])->toBe('Small - Medium')
        ->and($metrics['opportunities_in_campaign'])->toBe(0);
});

test('aggregateCampaignMetrics includes null detail fields when missing', function () {
    $engagements = [
        ['(raw) Engagement[Member Status]' => 'Opened'],
    ];

    $metrics = PowerBiDataTransformer::aggregateCampaignMetrics($engagements);

    expect($metrics['primary_purpose'])->toBeNull()
        ->and($metrics['category'])->toBeNull()
        ->and($metrics['sub_category'])->toBeNull()
        ->and($metrics['segment'])->toBeNull()
        ->and($metrics['opportunities_in_campaign'])->toBeNull();
});

test('aggregateCampaignMetrics handles zero delivered correctly', function () {
    // All members bounced → delivered = 0, rates = 0 (except bounce_rate = 100)
    $engagements = [
        ['(raw) Engagement[Member Status]' => 'Bounced'],
        ['(raw) Engagement[Member Status]' => 'Bounced'],
    ];

    $metrics = PowerBiDataTransformer::aggregateCampaignMetrics($engagements);

    expect($metrics['sent'])->toBe(2)
        ->and($metrics['bounced'])->toBe(2)
        ->and($metrics['delivered'])->toBe(0)
        ->and($metrics['open_rate'])->toBe(0.0)
        ->and($metrics['click_rate'])->toBe(0.0)
        ->and($metrics['bounce_rate'])->toBe(100.0);
});

test('transformMemberDetails formats member data correctly', function () {
    $members = [
        [
            '(raw) Engagement[Member ID]' => '00vPl00000UmUCI',
            '(raw) Engagement[First Name]' => 'Shanequa',
            '(raw) Engagement[Last Name]' => 'Hall',
            '(raw) Engagement[Email]' => 'elloquentshanae@gmail.com',
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
    ];

    $transformed = PowerBiDataTransformer::transformMemberDetails($members);

    expect($transformed)->toHaveCount(2)
        ->and($transformed[0]['member_id'])->toBe('00vPl00000UmUCI')
        ->and($transformed[0]['first_name'])->toBe('Shanequa')
        ->and($transformed[0]['last_name'])->toBe('Hall')
        ->and($transformed[0]['email'])->toBe('elloquentshanae@gmail.com')
        ->and($transformed[0]['company'])->toBe('Drink Pure')
        ->and($transformed[1]['member_id'])->toBe('00vPl00000UmUDJ');
});

test('transformMemberDetails handles already transformed data', function () {
    $members = [
        [
            'member_id' => '00vPl00000UmUCI',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'company' => 'Test Inc',
            'status_update_date' => '5/19/2025',
        ],
    ];

    $transformed = PowerBiDataTransformer::transformMemberDetails($members);

    expect($transformed)->toHaveCount(1)
        ->and($transformed[0]['member_id'])->toBe('00vPl00000UmUCI')
        ->and($transformed[0]['first_name'])->toBe('John');
});

test('buildCampaignAnalyticsFromEmailRows aggregates email metrics correctly', function () {
    $rows = [
        [
            '(raw) Email Campaign Metrics[RowID]' => 1,
            '(raw) Email Campaign Metrics[Name]' => 'Email 1',
            '(raw) Email Campaign Metrics[Subject]' => 'Subject 1',
            '(raw) Email Campaign Metrics[Scheduled Date]' => '5/5/2025 10:00:00 AM',
            '(raw) Email Campaign Metrics[Campaign ID]' => 'camp1',
            '(raw) Email Campaign Metrics[Campaign Name]' => 'Campaign 1',
            '(raw) Email Campaign Metrics[Total Delivered]' => 100,
            '(raw) Email Campaign Metrics[Unique Opens]' => 50,
            '(raw) Email Campaign Metrics[Open Rate]' => 50,
            '(raw) Email Campaign Metrics[Unique Clicks]' => 10,
            '(raw) Email Campaign Metrics[Unique Click Through Rate]' => 10,
            '(raw) Email Campaign Metrics[Click To Open Ratio]' => 20,
            '(raw) Email Campaign Metrics[Total Click Through Rate]' => 8,
            '(raw) Email Campaign Metrics[Total Opens]' => 70,
            '(raw) Email Campaign Metrics[Total Hard Bounces]' => 2,
            '(raw) Email Campaign Metrics[Delivery Rate]' => 98,
            '(raw) Email Campaign Metrics[Segment]' => 'Enterprise',
        ],
        [
            '(raw) Email Campaign Metrics[RowID]' => 2,
            '(raw) Email Campaign Metrics[Name]' => 'Email 2',
            '(raw) Email Campaign Metrics[Subject]' => 'Subject 2',
            '(raw) Email Campaign Metrics[Scheduled Date]' => '5/12/2025 10:00:00 AM',
            '(raw) Email Campaign Metrics[Campaign ID]' => 'camp1',
            '(raw) Email Campaign Metrics[Campaign Name]' => 'Campaign 1',
            '(raw) Email Campaign Metrics[Total Delivered]' => 50,
            '(raw) Email Campaign Metrics[Unique Opens]' => 25,
            '(raw) Email Campaign Metrics[Open Rate]' => 50,
            '(raw) Email Campaign Metrics[Unique Clicks]' => 5,
            '(raw) Email Campaign Metrics[Unique Click Through Rate]' => 10,
            '(raw) Email Campaign Metrics[Click To Open Ratio]' => 20,
            '(raw) Email Campaign Metrics[Total Click Through Rate]' => 6,
            '(raw) Email Campaign Metrics[Total Opens]' => 35,
            '(raw) Email Campaign Metrics[Total Hard Bounces]' => 1,
            '(raw) Email Campaign Metrics[Delivery Rate]' => 98.04,
            '(raw) Email Campaign Metrics[Segment]' => 'Enterprise',
        ],
    ];

    $analytics = PowerBiDataTransformer::buildCampaignAnalyticsFromEmailRows($rows);

    expect($analytics)->not->toBeNull()
        ->and($analytics['campaign_id'])->toBe('camp1')
        ->and($analytics['summary']['delivered'])->toBe(150)
        ->and($analytics['summary']['unique_opens'])->toBe(75)
        ->and($analytics['summary']['unique_clicks'])->toBe(15)
        ->and($analytics['summary']['total_opens'])->toBe(105)
        ->and($analytics['summary']['hard_bounces'])->toBe(3)
        ->and($analytics['summary']['open_rate'])->toBe(50.0)
        ->and($analytics['emails'])->toHaveCount(2);
});

test('stableEngagementId generates consistent hash', function () {
    $engagement1 = [
        '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
        '(raw) Engagement[Member ID]' => '00vPl00000UmUCI',
    ];

    $engagement2 = [
        '(raw) Engagement[Campaign ID]' => '701Pl00000hB2yb',
        '(raw) Engagement[Member ID]' => '00vPl00000UmUCI',
    ];

    $id1 = PowerBiDataTransformer::stableEngagementId($engagement1);
    $id2 = PowerBiDataTransformer::stableEngagementId($engagement2);

    expect($id1)->toBe($id2)
        ->and($id1)->toHaveLength(64); // SHA-256 produces 64 character hex string
});

// ---------------------------------------------------------------------------
// Lead aging
// ---------------------------------------------------------------------------

/**
 * Build history rows in the normalized shape PowerBiService::getLeadHistoryRows()
 * produces (every row in '(raw) Lead History' is a Lead Owner reassignment).
 */
function historyRows(array $editDates): array
{
    return array_map(fn ($editDate) => [
        'edit_date' => $editDate,
        'field' => 'Lead Owner',
        'old_value' => 'Sales Outcomes Lead Triage',
        'new_value' => 'Tracey Mullings',
        'edited_by' => 'Sales Outcomes Lead Triage',
    ], $editDates);
}

test('normalizeLeadId truncates 18-character Salesforce IDs to the 15-character form', function () {
    // '(raw) Lead v2' stores 18-char IDs, '(raw) Lead History' stores 15-char.
    expect(PowerBiDataTransformer::normalizeLeadId('00QPl00001AnpWAMAZ'))->toBe('00QPl00001AnpWA')
        ->and(PowerBiDataTransformer::normalizeLeadId('00QPl00000YlDUT'))->toBe('00QPl00000YlDUT')
        ->and(PowerBiDataTransformer::normalizeLeadId('  00QPl00000YlDUT  '))->toBe('00QPl00000YlDUT')
        ->and(PowerBiDataTransformer::normalizeLeadId(''))->toBe('');
});

test('parseLeadDate handles both date-only and date-with-time values', function () {
    expect(PowerBiDataTransformer::parseLeadDate('5/6/2025')->toIso8601String())->toBe('2025-05-06T00:00:00+00:00')
        ->and(PowerBiDataTransformer::parseLeadDate('5/6/2025 9:04:00 AM')->toIso8601String())->toBe('2025-05-06T09:04:00+00:00')
        ->and(PowerBiDataTransformer::parseLeadDate('5/6/2025 2:11:00 PM')->toIso8601String())->toBe('2025-05-06T14:11:00+00:00')
        ->and(PowerBiDataTransformer::parseLeadDate('not a date'))->toBeNull()
        ->and(PowerBiDataTransformer::parseLeadDate(''))->toBeNull()
        ->and(PowerBiDataTransformer::parseLeadDate(null))->toBeNull();
});

test('buildLeadAging measures time to first touch from the creation date', function () {
    $now = CarbonImmutable::create(2025, 5, 20, 12, 0, 0);

    $aging = PowerBiDataTransformer::buildLeadAging('5/6/2025', historyRows(['5/6/2025 9:04:00 AM']), $now);

    // Created at midnight 5/6, touched at 09:04 the same day.
    expect($aging['untouched'])->toBeFalse()
        ->and($aging['time_to_first_touch_hours'])->toBe(9.07)
        ->and($aging['first_touch_at'])->toBe('2025-05-06T09:04:00+00:00')
        ->and($aging['first_touch_to'])->toBe('Tracey Mullings')
        ->and($aging['age_hours'])->toBe(348.0)
        ->and($aging['event_count'])->toBe(1);
});

test('buildLeadAging flags a lead with no history as untouched', function () {
    $now = CarbonImmutable::create(2025, 5, 20, 12, 0, 0);

    $aging = PowerBiDataTransformer::buildLeadAging('5/6/2025', [], $now);

    // With nothing to go on, idle time falls back to the full age of the lead.
    expect($aging['untouched'])->toBeTrue()
        ->and($aging['time_to_first_touch_hours'])->toBeNull()
        ->and($aging['first_touch_at'])->toBeNull()
        ->and($aging['age_hours'])->toBe(348.0)
        ->and($aging['idle_hours'])->toBe(348.0)
        ->and($aging['event_count'])->toBe(0);
});

test('buildLeadAging ignores events at or before the creation date', function () {
    $now = CarbonImmutable::create(2025, 5, 20, 12, 0, 0);

    // Creation resolves to midnight, so an event stamped exactly at midnight is
    // indistinguishable from the lead's own creation and is not a touch.
    $aging = PowerBiDataTransformer::buildLeadAging('5/6/2025', historyRows(['5/6/2025 12:00:00 AM']), $now);

    expect($aging['untouched'])->toBeTrue()
        ->and($aging['time_to_first_touch_hours'])->toBeNull()
        ->and($aging['event_count'])->toBe(1);
});

test('buildLeadAging returns null when the creation date cannot be parsed', function () {
    $now = CarbonImmutable::create(2025, 5, 20, 12, 0, 0);

    expect(PowerBiDataTransformer::buildLeadAging('', historyRows(['5/6/2025 9:04:00 AM']), $now))->toBeNull()
        ->and(PowerBiDataTransformer::buildLeadAging('garbage', [], $now))->toBeNull();
});

test('buildLeadAging measures idle time from the most recent event', function () {
    $now = CarbonImmutable::create(2025, 5, 20, 12, 0, 0);

    $aging = PowerBiDataTransformer::buildLeadAging(
        '5/6/2025',
        historyRows(['5/8/2025 2:11:00 PM', '5/6/2025 9:04:00 AM']),
        $now,
    );

    // First touch comes from the earliest event, idle time from the latest.
    expect($aging['time_to_first_touch_hours'])->toBe(9.07)
        ->and($aging['idle_hours'])->toBe(285.82)
        ->and($aging['event_count'])->toBe(2);
});

test('compactLeadAging reduces the aging measurement to the three plotted numbers', function () {
    $now = CarbonImmutable::create(2025, 5, 20, 12, 0, 0);

    $compact = PowerBiDataTransformer::compactLeadAging(
        '5/6/2025',
        historyRows(['5/8/2025 2:11:00 PM', '5/6/2025 9:04:00 AM']),
        $now,
    );

    // [age_hours, hours_to_first_touch, owner_change_count] — same numbers the
    // full object carries, in the shape the leads page ships for every lead.
    expect($compact)->toBe([348.0, 9.07, 2]);
});

test('compactLeadAging reports a null first touch for an untouched lead', function () {
    $now = CarbonImmutable::create(2025, 5, 20, 12, 0, 0);

    $compact = PowerBiDataTransformer::compactLeadAging('5/6/2025', [], $now);

    expect($compact)->toBe([348.0, null, 0]);
});

test('compactLeadAging returns null when the creation date cannot be parsed', function () {
    $now = CarbonImmutable::create(2025, 5, 20, 12, 0, 0);

    expect(PowerBiDataTransformer::compactLeadAging('', historyRows(['5/6/2025 9:04:00 AM']), $now))->toBeNull()
        ->and(PowerBiDataTransformer::compactLeadAging('garbage', [], $now))->toBeNull();
});

test('buildLeadHistoryTimeline sorts events and measures the gap between them', function () {
    $timeline = PowerBiDataTransformer::buildLeadHistoryTimeline(
        historyRows(['5/8/2025 2:11:00 PM', '5/6/2025 9:04:00 AM']),
    );

    expect($timeline)->toHaveCount(2)
        ->and($timeline[0]['at'])->toBe('2025-05-06T09:04:00+00:00')
        ->and($timeline[0]['delta_hours'])->toBeNull()
        ->and($timeline[1]['at'])->toBe('2025-05-08T14:11:00+00:00')
        ->and($timeline[1]['delta_hours'])->toBe(53.12);
});

test('buildLeadHistoryTimeline sorts unparseable dates last without breaking the order', function () {
    $timeline = PowerBiDataTransformer::buildLeadHistoryTimeline(
        historyRows(['bogus', '5/8/2025 2:11:00 PM', '5/6/2025 9:04:00 AM']),
    );

    // The bad row still surfaces in the UI, it just cannot carry a delta.
    expect($timeline)->toHaveCount(3)
        ->and($timeline[0]['at'])->toBe('2025-05-06T09:04:00+00:00')
        ->and($timeline[1]['at'])->toBe('2025-05-08T14:11:00+00:00')
        ->and($timeline[2]['at'])->toBeNull()
        ->and($timeline[2]['at_display'])->toBe('bogus')
        ->and($timeline[2]['delta_hours'])->toBeNull();
});

test('buildCampaignAnalyticsFromEmailRows drops sends whose name starts with a number', function () {
    $row = function (string $name, int $delivered, int $hardBounces): array {
        return [
            '(raw) Email Campaign Metrics[RowID]' => $name,
            '(raw) Email Campaign Metrics[Name]' => $name,
            '(raw) Email Campaign Metrics[Subject]' => 'Subject',
            '(raw) Email Campaign Metrics[Scheduled Date]' => '5/5/2026 10:00:00 AM',
            '(raw) Email Campaign Metrics[Campaign ID]' => 'camp1',
            '(raw) Email Campaign Metrics[Campaign Name]' => 'CARIB_JAM_Prod_MDM_Ent_Feb2026',
            '(raw) Email Campaign Metrics[Total Delivered]' => $delivered,
            '(raw) Email Campaign Metrics[Total Hard Bounces]' => $hardBounces,
        ];
    };

    $analytics = PowerBiDataTransformer::buildCampaignAnalyticsFromEmailRows([
        $row('13895_CARIB_REG_Info_MDM_Ent_Mar2026', 500, 40),
        $row('CARIB_JAM_Prod_MDM_Ent_Feb2026', 100, 6),
    ]);

    expect($analytics)->not->toBeNull()
        ->and($analytics['emails'])->toHaveCount(1)
        ->and($analytics['emails'][0]['name'])->toBe('CARIB_JAM_Prod_MDM_Ent_Feb2026')
        ->and($analytics['summary']['delivered'])->toBe(100)
        ->and($analytics['summary']['hard_bounces'])->toBe(6);
});

test('buildCampaignAnalyticsFromEmailRows returns null when every send is numbered', function () {
    $analytics = PowerBiDataTransformer::buildCampaignAnalyticsFromEmailRows([
        [
            '(raw) Email Campaign Metrics[Name]' => '13895_CARIB_REG_Info_MDM_Ent_Mar2026',
            '(raw) Email Campaign Metrics[Campaign ID]' => 'camp1',
        ],
    ]);

    expect($analytics)->toBeNull();
});

test('isNumberedSendName only matches a leading digit', function (string $name, bool $expected) {
    expect(PowerBiDataTransformer::isNumberedSendName($name))->toBe($expected);
})->with([
    ['13895_CARIB_REG_Info_MDM_Ent_Mar2026', true],
    ['  20545_CARIB_Reg_Event_SpecialEvent_Apr2025', true],
    ['CARIB_JAM_Prod_MDM_Ent_Feb2026', false],
    ['CARIB_REG_Event_Amplify_Ent_May2026_3', false],
    ['', false],
]);
