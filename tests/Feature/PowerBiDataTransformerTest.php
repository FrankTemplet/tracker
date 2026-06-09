<?php

use App\Services\PowerBiDataTransformer;

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
