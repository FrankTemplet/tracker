<?php

namespace Tests\Feature;

use App\Services\PowerBiDataTransformer;
use Tests\TestCase;

class PowerBiDataTransformerTest extends TestCase
{
    public function test_transform_campaigns_from_power_bi_format(): void
    {
        $powerBiData = [
            [
                'REPORT - Campaign Tracker[Full Campaign Name]' => 'CARIB_JAM_Prod_CloudSuite_Ent_May2025',
                'REPORT - Campaign Tracker[Date]' => '2025-05-14T00:00:00',
                'REPORT - Campaign Tracker[Status]' => 'In Progress',
            ],
            [
                'REPORT - Campaign Tracker[Full Campaign Name]' => 'CARIB_TRI_Prod_CloudBundle_Ent_Sep2025',
                'REPORT - Campaign Tracker[Date]' => '2025-09-01T00:00:00',
                'REPORT - Campaign Tracker[Status]' => 'Completed',
            ],
        ];

        $transformed = PowerBiDataTransformer::transformCampaigns($powerBiData);

        $this->assertCount(2, $transformed);
        $this->assertEquals('CARIB_JAM_Prod_CloudSuite_Ent_May2025', $transformed[0]['name']);
        $this->assertEquals('CARIB_JAM_Prod_CloudSuite_Ent_May2025', $transformed[0]['id']);
        $this->assertEquals('2025-05-14T00:00:00', $transformed[0]['created_at']);
    }

    public function test_transform_emails_from_power_bi_format(): void
    {
        $powerBiData = [
            [
                '(raw email) Campaign%20Outcomes%20AllLiberty[Campaign Name]' => 'CARIB_TRI_Prod_CloudBundle_Ent_Sep2025',
                '(raw email) Campaign%20Outcomes%20AllLiberty[Subject]' => 'Take your business to the next level with Cloud',
                '(raw email) Campaign%20Outcomes%20AllLiberty[Scheduled Date]' => '2025-09-15T10:00:00',
                '(raw email) Campaign%20Outcomes%20AllLiberty[Total Delivered]' => 520,
            ],
        ];

        $transformed = PowerBiDataTransformer::transformEmails($powerBiData);

        $this->assertCount(1, $transformed);
        $this->assertEquals('Take your business to the next level with Cloud', $transformed[0]['subject']);
        $this->assertEquals('CARIB_TRI_Prod_CloudBundle_Ent_Sep2025', $transformed[0]['campaign_id']);
        $this->assertEquals('2025-09-15T10:00:00', $transformed[0]['sent_at']);
        $this->assertEquals(520, $transformed[0]['delivered']);
        $this->assertNotEmpty($transformed[0]['id']);
    }

    public function test_extract_email_analytics(): void
    {
        $powerBiEmail = [
            '(raw email) Campaign%20Outcomes%20AllLiberty[Total Delivered]' => 520,
            '(raw email) Campaign%20Outcomes%20AllLiberty[Total Opens]' => 210,
            '(raw email) Campaign%20Outcomes%20AllLiberty[Total Clicks]' => 21,
            '(raw email) Campaign%20Outcomes%20AllLiberty[Open Rate]' => 31.45,
            '(raw email) Campaign%20Outcomes%20AllLiberty[Total Click Through Rate]' => 4.12,
        ];

        $analytics = PowerBiDataTransformer::extractEmailAnalytics($powerBiEmail);

        $this->assertEquals(210, $analytics['opens']);
        $this->assertEquals(31.45, $analytics['open_rate']);
        $this->assertEquals(21, $analytics['clicks']);
        $this->assertEquals(4.12, $analytics['click_rate']);
        $this->assertGreaterThanOrEqual(0, $analytics['bounces']);
    }

    public function test_deduplicate_campaigns(): void
    {
        $campaigns = [
            ['id' => 'camp1', 'name' => 'Campaign A', 'created_at' => '2025-05-01'],
            ['id' => 'camp1', 'name' => 'Campaign A', 'created_at' => '2025-05-01'],
            ['id' => 'camp2', 'name' => 'Campaign B', 'created_at' => '2025-05-02'],
            ['id' => 'camp2', 'name' => 'Campaign B', 'created_at' => '2025-05-02'],
        ];

        $unique = PowerBiDataTransformer::deduplicateCampaigns($campaigns);

        $this->assertCount(2, $unique);
        $this->assertEquals('Campaign A', $unique[0]['name']);
        $this->assertEquals('Campaign B', $unique[1]['name']);
    }
}
