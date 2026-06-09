<?php

namespace App\Services;

class PowerBiDataTransformer
{
    /**
     * Transform unique campaign data from Power BI to frontend format.
     *
     * @param  array  $powerBiCampaigns  Unique campaign data from Power BI
     * @return array<int, array{id: string, name: string, business_unit: string, created_at: string}>
     */
    public static function transformCampaigns(array $powerBiCampaigns): array
    {
        return array_map(function ($campaign) {
            return [
                'id' => $campaign['campaign_id'] ?? '',
                'name' => $campaign['campaign_name'] ?? 'Unknown Campaign',
                'business_unit' => $campaign['business_unit'] ?? '',
                'created_at' => $campaign['start_date'] ?? now()->toIso8601String(),
            ];
        }, $powerBiCampaigns);
    }

    /**
     * Aggregate engagement data by campaign to calculate metrics.
     * Takes granular engagement records and groups them by campaign.
     *
     * @param  array  $engagements  Raw engagement records from Power BI
     * @return array<string, array{campaign_id: string, campaign_name: string, total_sent: int, total_opened: int, total_clicked: int, total_bounced: int, unique_members: int}>
     */
    public static function aggregateEngagementsByCampaign(array $engagements): array
    {
        $campaignMetrics = [];

        foreach ($engagements as $engagement) {
            $campaignId = $engagement['(raw) Engagement[Campaign ID]'] ?? '';
            $campaignName = $engagement['(raw) Engagement[Campaign Name]'] ?? '';
            $memberStatus = $engagement['(raw) Engagement[Member Status]'] ?? '';
            $memberId = $engagement['(raw) Engagement[Member ID]'] ?? '';

            if (! isset($campaignMetrics[$campaignId])) {
                $campaignMetrics[$campaignId] = [
                    'campaign_id' => $campaignId,
                    'campaign_name' => $campaignName,
                    'total_sent' => 0,
                    'total_opened' => 0,
                    'total_clicked' => 0,
                    'total_bounced' => 0,
                    'unique_members' => [],
                ];
            }

            // Track unique members
            if ($memberId && ! in_array($memberId, $campaignMetrics[$campaignId]['unique_members'])) {
                $campaignMetrics[$campaignId]['unique_members'][] = $memberId;
            }

            // Count by status
            match ($memberStatus) {
                'Sent' => $campaignMetrics[$campaignId]['total_sent']++,
                'Opened' => $campaignMetrics[$campaignId]['total_opened']++,
                'Clicked' => $campaignMetrics[$campaignId]['total_clicked']++,
                'Bounced' => $campaignMetrics[$campaignId]['total_bounced']++,
                default => null,
            };
        }

        // Convert unique_members array to count
        foreach ($campaignMetrics as $campaignId => $metrics) {
            $campaignMetrics[$campaignId]['unique_members'] = count($metrics['unique_members']);
        }

        return $campaignMetrics;
    }

    /**
     * Calculate aggregate metrics for a campaign from engagement records.
     *
     * @param  array  $engagements  Engagement records for a specific campaign
     * @return array{sent: int, delivered: int, opened: int, clicked: int, bounced: int, open_rate: float, click_rate: float, bounce_rate: float}
     */
    public static function aggregateCampaignMetrics(array $engagements): array
    {
        // sent = total records in the engagement table for this campaign.
        // Each row represents one campaign member regardless of their status.
        $sent = count($engagements);
        $opened = 0;
        $clicked = 0;
        $bounced = 0;

        foreach ($engagements as $engagement) {
            $memberStatus = strtolower($engagement['(raw) Engagement[Member Status]'] ?? '');

            // "Clicked" implies the member also opened, so count toward both.
            // Salesforce stores the highest status only (Clicked > Opened > Sent).
            if (str_contains($memberStatus, 'click')) {
                $clicked++;
                $opened++; // implicit open
            } elseif (str_contains($memberStatus, 'open')) {
                $opened++;
            } elseif (str_contains($memberStatus, 'bounce')) {
                $bounced++;
            }
            // "Sent"/"Enviado" fall through — they are already counted in $sent (total count)
        }

        $delivered = $sent - $bounced;
        $openRate = $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0.0;
        $clickRate = $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0.0;
        $bounceRate = $sent > 0 ? round(($bounced / $sent) * 100, 2) : 0.0;

        return [
            'sent' => $sent,
            'delivered' => $delivered,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
            'bounce_rate' => $bounceRate,
        ];
    }

    /**
     * Transform member engagement details to frontend format.
     *
     * @param  array  $members  Member engagement records
     * @return array<int, array{member_id: string, first_name: string, last_name: string, email: string, company: string, status_update_date: string}>
     */
    public static function transformMemberDetails(array $members): array
    {
        return array_map(function ($member) {
            return [
                'member_id' => $member['(raw) Engagement[Member ID]'] ?? $member['member_id'] ?? '',
                'first_name' => $member['(raw) Engagement[First Name]'] ?? $member['first_name'] ?? '',
                'last_name' => $member['(raw) Engagement[Last Name]'] ?? $member['last_name'] ?? '',
                'email' => $member['(raw) Engagement[Email]'] ?? $member['email'] ?? '',
                'company' => $member['(raw) Engagement[Company]'] ?? $member['company'] ?? '',
                'status_update_date' => $member['(raw) Engagement[Member Status Update Date]'] ?? $member['status_update_date'] ?? '',
            ];
        }, $members);
    }

    /**
     * Generate a stable ID from engagement record.
     *
     * @param  array  $engagement  Engagement record
     */
    public static function stableEngagementId(array $engagement): string
    {
        $campaignId = $engagement['(raw) Engagement[Campaign ID]'] ?? '';
        $memberId = $engagement['(raw) Engagement[Member ID]'] ?? '';

        return hash('sha256', $campaignId.$memberId);
    }
}
