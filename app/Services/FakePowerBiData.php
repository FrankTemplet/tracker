<?php

namespace App\Services;

class FakePowerBiData
{
    /**
     * Get fake campaigns data for demo purposes.
     *
     * @return array<int, array{id: string, name: string, created_at: string}>
     */
    public static function getCampaigns(): array
    {
        return [
            [
                'id' => 'campaign-001',
                'name' => 'Summer Sale 2026',
                'created_at' => '2026-05-01T10:00:00Z',
            ],
            [
                'id' => 'campaign-002',
                'name' => 'Newsletter May 2026',
                'created_at' => '2026-05-05T14:30:00Z',
            ],
            [
                'id' => 'campaign-003',
                'name' => 'Product Launch Campaign',
                'created_at' => '2026-05-10T09:15:00Z',
            ],
            [
                'id' => 'campaign-004',
                'name' => 'Customer Retention Email',
                'created_at' => '2026-05-12T16:45:00Z',
            ],
        ];
    }

    /**
     * Get fake emails for a specific campaign.
     *
     * @return array<int, array{id: string, campaign_id: string, subject: string, from: string, to: string, sent_at: string}>
     */
    public static function getCampaignEmails(string $campaignId): array
    {
        $allEmails = [
            // Campaign 001 emails
            [
                'id' => 'email-001',
                'campaign_id' => 'campaign-001',
                'subject' => 'Summer Sale - 50% Off Everything!',
                'from' => 'marketing@example.com',
                'to' => 'customer1@example.com',
                'sent_at' => '2026-05-01T11:00:00Z',
            ],
            [
                'id' => 'email-002',
                'campaign_id' => 'campaign-001',
                'subject' => 'Last Chance: Summer Sale Ends Tonight',
                'from' => 'marketing@example.com',
                'to' => 'customer2@example.com',
                'sent_at' => '2026-05-01T11:05:00Z',
            ],
            [
                'id' => 'email-003',
                'campaign_id' => 'campaign-001',
                'subject' => 'Exclusive Summer Deals Just for You',
                'from' => 'marketing@example.com',
                'to' => 'customer3@example.com',
                'sent_at' => '2026-05-01T11:10:00Z',
            ],
            [
                'id' => 'email-004',
                'campaign_id' => 'campaign-001',
                'subject' => 'Your Summer Shopping Guide',
                'from' => 'marketing@example.com',
                'to' => 'customer4@example.com',
                'sent_at' => '2026-05-01T11:15:00Z',
            ],
            [
                'id' => 'email-005',
                'campaign_id' => 'campaign-001',
                'subject' => 'Hot Summer Savings - Limited Time',
                'from' => 'marketing@example.com',
                'to' => 'customer5@example.com',
                'sent_at' => '2026-05-01T11:20:00Z',
            ],

            // Campaign 002 emails
            [
                'id' => 'email-006',
                'campaign_id' => 'campaign-002',
                'subject' => 'May Newsletter - What\'s New',
                'from' => 'newsletter@example.com',
                'to' => 'subscriber1@example.com',
                'sent_at' => '2026-05-05T15:00:00Z',
            ],
            [
                'id' => 'email-007',
                'campaign_id' => 'campaign-002',
                'subject' => 'This Month in Tech - May Edition',
                'from' => 'newsletter@example.com',
                'to' => 'subscriber2@example.com',
                'sent_at' => '2026-05-05T15:05:00Z',
            ],
            [
                'id' => 'email-008',
                'campaign_id' => 'campaign-002',
                'subject' => 'Top Stories from May 2026',
                'from' => 'newsletter@example.com',
                'to' => 'subscriber3@example.com',
                'sent_at' => '2026-05-05T15:10:00Z',
            ],

            // Campaign 003 emails
            [
                'id' => 'email-009',
                'campaign_id' => 'campaign-003',
                'subject' => 'Introducing Our Revolutionary New Product',
                'from' => 'products@example.com',
                'to' => 'customer6@example.com',
                'sent_at' => '2026-05-10T10:00:00Z',
            ],
            [
                'id' => 'email-010',
                'campaign_id' => 'campaign-003',
                'subject' => 'Be the First to Try Our New Launch',
                'from' => 'products@example.com',
                'to' => 'customer7@example.com',
                'sent_at' => '2026-05-10T10:15:00Z',
            ],
            [
                'id' => 'email-011',
                'campaign_id' => 'campaign-003',
                'subject' => 'Product Launch: Early Bird Special',
                'from' => 'products@example.com',
                'to' => 'customer8@example.com',
                'sent_at' => '2026-05-10T10:30:00Z',
            ],
            [
                'id' => 'email-012',
                'campaign_id' => 'campaign-003',
                'subject' => 'Why You\'ll Love Our New Product',
                'from' => 'products@example.com',
                'to' => 'customer9@example.com',
                'sent_at' => '2026-05-10T10:45:00Z',
            ],

            // Campaign 004 emails
            [
                'id' => 'email-013',
                'campaign_id' => 'campaign-004',
                'subject' => 'We Miss You! Come Back for 20% Off',
                'from' => 'retention@example.com',
                'to' => 'inactive1@example.com',
                'sent_at' => '2026-05-12T17:00:00Z',
            ],
            [
                'id' => 'email-014',
                'campaign_id' => 'campaign-004',
                'subject' => 'Special Offer Just for You',
                'from' => 'retention@example.com',
                'to' => 'inactive2@example.com',
                'sent_at' => '2026-05-12T17:15:00Z',
            ],
            [
                'id' => 'email-015',
                'campaign_id' => 'campaign-004',
                'subject' => 'Your Exclusive Comeback Discount',
                'from' => 'retention@example.com',
                'to' => 'inactive3@example.com',
                'sent_at' => '2026-05-12T17:30:00Z',
            ],
        ];

        return array_values(array_filter($allEmails, fn ($email) => $email['campaign_id'] === $campaignId));
    }

    /**
     * Get fake analytics for a specific email.
     *
     * @return array{bounces: int, bounce_rate: float, opens: int, open_rate: float, clicks: int, click_rate: float}
     */
    public static function getEmailAnalytics(string $emailId): array
    {
        // Different analytics based on email ID to make it realistic
        $analytics = [
            'email-001' => ['bounces' => 12, 'bounce_rate' => 1.2, 'opens' => 850, 'open_rate' => 85.0, 'clicks' => 425, 'click_rate' => 42.5],
            'email-002' => ['bounces' => 8, 'bounce_rate' => 0.9, 'opens' => 780, 'open_rate' => 78.0, 'clicks' => 390, 'click_rate' => 39.0],
            'email-003' => ['bounces' => 15, 'bounce_rate' => 1.5, 'opens' => 920, 'open_rate' => 92.0, 'clicks' => 552, 'click_rate' => 55.2],
            'email-004' => ['bounces' => 6, 'bounce_rate' => 0.7, 'opens' => 750, 'open_rate' => 75.0, 'clicks' => 300, 'click_rate' => 30.0],
            'email-005' => ['bounces' => 20, 'bounce_rate' => 2.1, 'opens' => 680, 'open_rate' => 68.0, 'clicks' => 272, 'click_rate' => 27.2],
            'email-006' => ['bounces' => 5, 'bounce_rate' => 0.5, 'opens' => 950, 'open_rate' => 95.0, 'clicks' => 665, 'click_rate' => 66.5],
            'email-007' => ['bounces' => 7, 'bounce_rate' => 0.8, 'opens' => 880, 'open_rate' => 88.0, 'clicks' => 528, 'click_rate' => 52.8],
            'email-008' => ['bounces' => 10, 'bounce_rate' => 1.0, 'opens' => 900, 'open_rate' => 90.0, 'clicks' => 540, 'click_rate' => 54.0],
            'email-009' => ['bounces' => 18, 'bounce_rate' => 1.8, 'opens' => 820, 'open_rate' => 82.0, 'clicks' => 410, 'click_rate' => 41.0],
            'email-010' => ['bounces' => 9, 'bounce_rate' => 0.9, 'opens' => 890, 'open_rate' => 89.0, 'clicks' => 534, 'click_rate' => 53.4],
            'email-011' => ['bounces' => 11, 'bounce_rate' => 1.3, 'opens' => 760, 'open_rate' => 76.0, 'clicks' => 380, 'click_rate' => 38.0],
            'email-012' => ['bounces' => 14, 'bounce_rate' => 1.6, 'opens' => 800, 'open_rate' => 80.0, 'clicks' => 400, 'click_rate' => 40.0],
            'email-013' => ['bounces' => 25, 'bounce_rate' => 3.5, 'opens' => 550, 'open_rate' => 55.0, 'clicks' => 220, 'click_rate' => 22.0],
            'email-014' => ['bounces' => 22, 'bounce_rate' => 3.0, 'opens' => 600, 'open_rate' => 60.0, 'clicks' => 240, 'click_rate' => 24.0],
            'email-015' => ['bounces' => 28, 'bounce_rate' => 4.0, 'opens' => 520, 'open_rate' => 52.0, 'clicks' => 208, 'click_rate' => 20.8],
        ];

        return $analytics[$emailId] ?? [
            'bounces' => 0,
            'bounce_rate' => 0.0,
            'opens' => 0,
            'open_rate' => 0.0,
            'clicks' => 0,
            'click_rate' => 0.0,
        ];
    }

    /**
     * Get fake engagement data for a specific email.
     * TODO: Replace with real endpoint when available.
     *
     * @return array{opened: int, registered: int, schedule_appointment: int, attended: int}
     */
    public static function getEngagementData(string $emailId): array
    {
        $data = [
            'email-001' => ['opened' => 720, 'registered' => 312, 'schedule_appointment' => 145, 'attended' => 98],
            'email-002' => ['opened' => 650, 'registered' => 280, 'schedule_appointment' => 130, 'attended' => 87],
            'email-003' => ['opened' => 810, 'registered' => 390, 'schedule_appointment' => 175, 'attended' => 120],
            'email-004' => ['opened' => 610, 'registered' => 245, 'schedule_appointment' => 110, 'attended' => 72],
            'email-005' => ['opened' => 540, 'registered' => 198, 'schedule_appointment' => 88, 'attended' => 55],
            'email-006' => ['opened' => 880, 'registered' => 430, 'schedule_appointment' => 210, 'attended' => 155],
            'email-007' => ['opened' => 790, 'registered' => 360, 'schedule_appointment' => 168, 'attended' => 112],
            'email-008' => ['opened' => 830, 'registered' => 410, 'schedule_appointment' => 192, 'attended' => 140],
            'email-009' => ['opened' => 700, 'registered' => 305, 'schedule_appointment' => 138, 'attended' => 92],
            'email-010' => ['opened' => 820, 'registered' => 395, 'schedule_appointment' => 185, 'attended' => 130],
            'email-011' => ['opened' => 640, 'registered' => 265, 'schedule_appointment' => 118, 'attended' => 78],
            'email-012' => ['opened' => 670, 'registered' => 290, 'schedule_appointment' => 135, 'attended' => 90],
            'email-013' => ['opened' => 420, 'registered' => 155, 'schedule_appointment' => 68, 'attended' => 42],
            'email-014' => ['opened' => 460, 'registered' => 178, 'schedule_appointment' => 80, 'attended' => 50],
            'email-015' => ['opened' => 395, 'registered' => 140, 'schedule_appointment' => 58, 'attended' => 35],
        ];

        return $data[$emailId] ?? ['opened' => 0, 'registered' => 0, 'schedule_appointment' => 0, 'attended' => 0];
    }
}
