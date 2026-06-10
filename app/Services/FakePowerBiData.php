<?php

namespace App\Services;

class FakePowerBiData
{
    /**
     * Get all fake engagement records (granular data).
     * Each record represents one member's interaction with one campaign.
     *
     * @return array<int, array>
     */
    public static function getAllEngagements(): array
    {
        $engagements = [];
        $campaigns = self::getUniqueCampaigns();

        foreach ($campaigns as $campaign) {
            $campaignEngagements = self::generateCampaignEngagements($campaign);
            $engagements = array_merge($engagements, $campaignEngagements);
        }

        return $engagements;
    }

    /**
     * Get unique campaigns data.
     *
     * @return array<int, array{campaign_id: string, campaign_name: string, business_unit: string, start_date: string}>
     */
    public static function getUniqueCampaigns(): array
    {
        return [
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
            [
                'campaign_id' => '701Pl00000hB4yd',
                'campaign_name' => 'Newsletter_May_2026_Global',
                'business_unit' => 'Global Marketing',
                'start_date' => '5/20/2025',
            ],
        ];
    }

    /**
     * Get engagements for a specific campaign.
     *
     * @return array<int, array>
     */
    public static function getEngagementsByCampaign(string $campaignId): array
    {
        $allEngagements = self::getAllEngagements();

        return array_values(array_filter(
            $allEngagements,
            fn ($e) => ($e['(raw) Engagement[Campaign ID]'] ?? '') === $campaignId
        ));
    }

    /**
     * Get members with a specific status for a campaign.
     *
     * @return array<int, array{member_id: string, first_name: string, last_name: string, email: string, company: string, status_update_date: string}>
     */
    public static function getMembersByStatus(string $campaignId, string $metric): array
    {
        $engagements = self::getEngagementsByCampaign($campaignId);

        $filtered = array_filter($engagements, function ($engagement) use ($metric) {
            $status = $engagement['(raw) Engagement[Member Status]'] ?? '';

            return match ($metric) {
                'delivered' => $status !== 'Bounced',
                'unique-opens', 'total-opens' => in_array($status, ['Opened', 'Clicked'], true),
                'unique-clicks' => $status === 'Clicked',
                'hard-bounces' => $status === 'Bounced',
                'registered-appointment' => in_array($status, ['Registered', 'Schedule Appointment'], true),
                default => $status === $metric,
            };
        });

        return PowerBiDataTransformer::transformMemberDetails(array_values($filtered));
    }

    /**
     * Generate realistic engagement data for a campaign.
     *
     * @param  array  $campaign  Campaign info
     * @return array<int, array>
     */
    private static function generateCampaignEngagements(array $campaign): array
    {
        $campaignId = $campaign['campaign_id'];
        $campaignName = $campaign['campaign_name'];
        $businessUnit = $campaign['business_unit'];
        $startDate = $campaign['start_date'];

        // Generate different statuses with realistic distribution
        $members = [
            ['id' => '00vPl00000UmUCI', 'first' => 'Shanequa', 'last' => 'Hall', 'email' => 'elloquentshanae@gmail.com', 'company' => 'Drink Pure', 'status' => 'Opened'],
            ['id' => '00vPl00000UmUDJ', 'first' => 'John', 'last' => 'Smith', 'email' => 'john.smith@example.com', 'company' => 'Tech Corp', 'status' => 'Clicked'],
            ['id' => '00vPl00000UmUEK', 'first' => 'Maria', 'last' => 'Garcia', 'email' => 'maria.garcia@example.com', 'company' => 'Digital Solutions', 'status' => 'Opened'],
            ['id' => '00vPl00000UmUFL', 'first' => 'David', 'last' => 'Johnson', 'email' => 'david.j@example.com', 'company' => 'Cloud Services', 'status' => 'Registered'],
            ['id' => '00vPl00000UmUGM', 'first' => 'Sarah', 'last' => 'Williams', 'email' => 'sarah.w@example.com', 'company' => 'Marketing Inc', 'status' => 'Clicked'],
            ['id' => '00vPl00000UmUHN', 'first' => 'Michael', 'last' => 'Brown', 'email' => 'mbrown@example.com', 'company' => 'Enterprise Ltd', 'status' => 'Opened'],
            ['id' => '00vPl00000UmUIO', 'first' => 'Jessica', 'last' => 'Davis', 'email' => 'jessica.davis@example.com', 'company' => 'Global Trade', 'status' => 'Bounced'],
            ['id' => '00vPl00000UmUJP', 'first' => 'Robert', 'last' => 'Miller', 'email' => 'robert.miller@example.com', 'company' => 'Finance Plus', 'status' => 'Opened'],
            ['id' => '00vPl00000UmUKQ', 'first' => 'Emily', 'last' => 'Wilson', 'email' => 'ewilson@example.com', 'company' => 'Retail Group', 'status' => 'Clicked'],
            ['id' => '00vPl00000UmULR', 'first' => 'James', 'last' => 'Moore', 'email' => 'james.moore@example.com', 'company' => 'Healthcare Systems', 'status' => 'Schedule Appointment'],
            ['id' => '00vPl00000UmUMS', 'first' => 'Linda', 'last' => 'Taylor', 'email' => 'linda.taylor@example.com', 'company' => 'Education Services', 'status' => 'Opened'],
            ['id' => '00vPl00000UmUNT', 'first' => 'William', 'last' => 'Anderson', 'email' => 'wanderson@example.com', 'company' => 'Manufacturing Co', 'status' => 'Bounced'],
            ['id' => '00vPl00000UmUOU', 'first' => 'Patricia', 'last' => 'Thomas', 'email' => 'pthomas@example.com', 'company' => 'Legal Advisors', 'status' => 'Opened'],
            ['id' => '00vPl00000UmUPV', 'first' => 'Richard', 'last' => 'Jackson', 'email' => 'rjackson@example.com', 'company' => 'Consulting Firm', 'status' => 'Clicked'],
            ['id' => '00vPl00000UmUQW', 'first' => 'Barbara', 'last' => 'White', 'email' => 'bwhite@example.com', 'company' => 'Design Studio', 'status' => 'Registered'],
        ];

        $engagements = [];
        foreach ($members as $member) {
            $engagements[] = [
                '(raw) Engagement[Campaign ID]' => $campaignId,
                '(raw) Engagement[Campaign Name]' => $campaignName,
                '(raw) Engagement[Reporting Business Unit]' => $businessUnit,
                '(raw) Engagement[Primary Campaign Purpose]' => 'Operational',
                '(raw) Engagement[Category]' => 'Email',
                '(raw) Engagement[Sub-Category]' => 'Operational',
                '(raw) Engagement[Campaign Status]' => 'In Progress',
                '(raw) Engagement[Start Date]' => $startDate,
                '(raw) Engagement[Member ID]' => $member['id'],
                '(raw) Engagement[Member Type]' => 'Contact',
                '(raw) Engagement[Member Status]' => $member['status'],
                '(raw) Engagement[First Name]' => $member['first'],
                '(raw) Engagement[Last Name]' => $member['last'],
                '(raw) Engagement[Email]' => $member['email'],
                '(raw) Engagement[Company]' => $member['company'],
                '(raw) Engagement[Member Status Update Date]' => '5/19/2025',
                '(raw) Engagement[Related Record ID]' => '0034X00003APepj',
                '(raw) Engagement[Segment]' => 'Large - Enterprise',
            ];
        }

        return $engagements;
    }

    /**
     * Get fake campaign metrics for testing without Power BI credentials.
     *
     * @return array<string, mixed>|null
     */
    public static function getCampaignMetrics(string $campaignId): ?array
    {
        $emailRows = [
            '701Pl00000hB2yb' => [
                [
                    '(raw) Email Campaign Metrics[RowID]' => 1,
                    '(raw) Email Campaign Metrics[Name]' => 'CARIB_JAM_Info_CustomerCare_MSeg_May2025_1',
                    '(raw) Email Campaign Metrics[Subject]' => 'Customer Care Update',
                    '(raw) Email Campaign Metrics[Scheduled Date]' => '5/5/2025 10:00:00 AM',
                    '(raw) Email Campaign Metrics[Campaign ID]' => '701Pl00000hB2yb',
                    '(raw) Email Campaign Metrics[Campaign Name]' => 'CARIB_JAM_Info_CustomerCare_MSeg_May2025',
                    '(raw) Email Campaign Metrics[Total Delivered]' => 100,
                    '(raw) Email Campaign Metrics[Unique Opens]' => 70,
                    '(raw) Email Campaign Metrics[Open Rate]' => 70,
                    '(raw) Email Campaign Metrics[Unique Clicks]' => 15,
                    '(raw) Email Campaign Metrics[Unique Click Through Rate]' => 15,
                    '(raw) Email Campaign Metrics[Click To Open Ratio]' => 21.43,
                    '(raw) Email Campaign Metrics[Total Click Through Rate]' => 12,
                    '(raw) Email Campaign Metrics[Total Opens]' => 90,
                    '(raw) Email Campaign Metrics[Total Hard Bounces]' => 3,
                    '(raw) Email Campaign Metrics[Delivery Rate]' => 97.09,
                    '(raw) Email Campaign Metrics[Segment]' => 'Small - Medium',
                ],
                [
                    '(raw) Email Campaign Metrics[RowID]' => 2,
                    '(raw) Email Campaign Metrics[Name]' => 'CARIB_JAM_Info_CustomerCare_MSeg_May2025_2',
                    '(raw) Email Campaign Metrics[Subject]' => 'Follow-up: Customer Care',
                    '(raw) Email Campaign Metrics[Scheduled Date]' => '5/12/2025 10:00:00 AM',
                    '(raw) Email Campaign Metrics[Campaign ID]' => '701Pl00000hB2yb',
                    '(raw) Email Campaign Metrics[Campaign Name]' => 'CARIB_JAM_Info_CustomerCare_MSeg_May2025',
                    '(raw) Email Campaign Metrics[Total Delivered]' => 50,
                    '(raw) Email Campaign Metrics[Unique Opens]' => 30,
                    '(raw) Email Campaign Metrics[Open Rate]' => 60,
                    '(raw) Email Campaign Metrics[Unique Clicks]' => 10,
                    '(raw) Email Campaign Metrics[Unique Click Through Rate]' => 20,
                    '(raw) Email Campaign Metrics[Click To Open Ratio]' => 33.33,
                    '(raw) Email Campaign Metrics[Total Click Through Rate]' => 18,
                    '(raw) Email Campaign Metrics[Total Opens]' => 40,
                    '(raw) Email Campaign Metrics[Total Hard Bounces]' => 2,
                    '(raw) Email Campaign Metrics[Delivery Rate]' => 96.15,
                    '(raw) Email Campaign Metrics[Segment]' => 'Small - Medium',
                ],
            ],
        ];

        if (! isset($emailRows[$campaignId])) {
            return null;
        }

        return PowerBiDataTransformer::buildCampaignAnalyticsFromEmailRows($emailRows[$campaignId]);
    }
}
