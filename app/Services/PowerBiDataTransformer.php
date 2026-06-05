<?php

namespace App\Services;

class PowerBiDataTransformer
{
    /**
     * Transform Power BI campaign data to frontend format.
     *
     * @param  array  $powerBiCampaigns  Raw data from Power BI
     * @return array<int, array{id: string, name: string, created_at: string}>
     */
    public static function transformCampaigns(array $powerBiCampaigns): array
    {
        return array_map(function ($campaign) {
            // Use Full Campaign Name if available, otherwise fall back to Campaign
            $campaignName = $campaign['REPORT - Campaign Tracker[Full Campaign Name]']
                ?? $campaign['REPORT - Campaign Tracker[Campaign]']
                ?? 'Unknown Campaign';
            $createdAt = $campaign['REPORT - Campaign Tracker[Date]'] ?? now()->toIso8601String();

            return [
                'id' => $campaignName,
                'name' => $campaignName,
                'created_at' => $createdAt,
            ];
        }, $powerBiCampaigns);
    }

    /**
     * Transform Power BI email data to frontend format.
     *
     * @param  array  $powerBiEmails  Raw data from Power BI
     * @return array<int, array{id: string, campaign_id: string, subject: string, from: string, to: string, sent_at: string, delivered: int}>
     */
    public static function transformEmails(array $powerBiEmails): array
    {
        return array_map(function ($email, $index) {
            // Emails come from different table: (raw email) Campaign Outcomes AllLiberty
            $campaignName = $email['(raw email) Campaign%20Outcomes%20AllLiberty[Campaign Name]']
                ?? $email['REPORT - Campaign Tracker[Campaign Name]']
                ?? $email['REPORT - Campaign Tracker[Full Campaign Name]']
                ?? $email['REPORT - Campaign Tracker[Campaign]']
                ?? 'Unknown Campaign';
            $subject = $email['(raw email) Campaign%20Outcomes%20AllLiberty[Subject]']
                ?? $email['REPORT - Campaign Tracker[Subject]']
                ?? 'No Subject';
            $scheduledDate = $email['(raw email) Campaign%20Outcomes%20AllLiberty[Scheduled Date]']
                ?? $email['REPORT - Campaign Tracker[Scheduled Date]']
                ?? now()->toIso8601String();
            $delivered = (int) ($email['(raw email) Campaign%20Outcomes%20AllLiberty[Total Delivered]'] ?? 0);

            return [
                'id' => self::stableEmailId($email),
                'campaign_id' => $campaignName,
                'subject' => $subject,
                'from' => 'campaigns@company.com',
                'to' => 'recipient@example.com',
                'sent_at' => $scheduledDate,
                'delivered' => $delivered,
            ];
        }, $powerBiEmails, array_keys($powerBiEmails));
    }

    /**
     * Generate a stable ID for an email by sorting keys before hashing,
     * so that different key orderings from the API produce the same ID.
     */
    public static function stableEmailId(array $email): string
    {
        ksort($email);

        return hash('sha256', json_encode($email));
    }

    /**
     * Extract analytics from Power BI email data.
     *
     * @param  array  $powerBiEmail  Email data from Power BI
     * @return array{bounces: int, bounce_rate: float, opens: int, open_rate: float, clicks: int, click_rate: float, total_delivered: int, unique_opens: int, unique_clicks: int, opt_out_rate: float}
     */
    public static function extractEmailAnalytics(array $powerBiEmail): array
    {
        // Emails come from different table with different prefix
        $totalDelivered = (int) ($powerBiEmail['(raw email) Campaign%20Outcomes%20AllLiberty[Total Delivered]']
            ?? $powerBiEmail['REPORT - Campaign Tracker[Total Delivered]']
            ?? 0);
        $totalOpens = (int) ($powerBiEmail['(raw email) Campaign%20Outcomes%20AllLiberty[Total Opens]']
            ?? $powerBiEmail['REPORT - Campaign Tracker[Total Opens]']
            ?? 0);
        $totalClicks = (int) ($powerBiEmail['(raw email) Campaign%20Outcomes%20AllLiberty[Total Clicks]']
            ?? $powerBiEmail['REPORT - Campaign Tracker[Total Clicks]']
            ?? 0);
        $openRate = (float) ($powerBiEmail['(raw email) Campaign%20Outcomes%20AllLiberty[Open Rate]']
            ?? $powerBiEmail['REPORT - Campaign Tracker[Open Rate]']
            ?? 0);
        $clickRate = (float) ($powerBiEmail['(raw email) Campaign%20Outcomes%20AllLiberty[Total Click Through Rate]']
            ?? $powerBiEmail['REPORT - Campaign Tracker[Total Click Through Rate]']
            ?? 0);

        $bounceRate = 0.0;
        $bounces = 0;
        $optOutRate = 0.0;

        if ($totalDelivered > 0) {
            $bounces = $totalDelivered - $totalOpens;
            $bounceRate = round(($bounces / $totalDelivered) * 100, 2);
            $optOutRate = round(($bounces / $totalDelivered) * 100, 2);
        }

        return [
            'bounces' => $bounces,
            'bounce_rate' => $bounceRate,
            'opens' => $totalOpens,
            'open_rate' => $openRate,
            'clicks' => $totalClicks,
            'click_rate' => $clickRate,
            'total_delivered' => $totalDelivered,
            'unique_opens' => $totalOpens,
            'unique_clicks' => $totalClicks,
            'opt_out_rate' => $optOutRate,
        ];
    }

    /**
     * Remove duplicate campaigns by name.
     *
     * @return array<int, array>
     */
    public static function deduplicateCampaigns(array $campaigns): array
    {
        $seen = [];
        $unique = [];

        foreach ($campaigns as $campaign) {
            $name = $campaign['name'] ?? 'Unknown';
            if (! isset($seen[$name])) {
                $seen[$name] = true;
                $unique[] = $campaign;
            }
        }

        return $unique;
    }
}
