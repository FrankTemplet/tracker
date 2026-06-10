<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PowerBiService
{
    /**
     * Check if Power BI credentials are configured.
     */
    public function hasCredentials(): bool
    {
        return ! empty(config('powerbi.client_id'))
            && ! empty(config('powerbi.client_secret'))
            && ! empty(config('powerbi.tenant_id'));
    }

    /**
     * Get an access token from Azure AD using client credentials flow.
     * Token is cached for 55 minutes to avoid hitting rate limits.
     */
    public function getAccessToken(): string
    {
        return Cache::remember('powerbi_access_token', 55 * 60, function () {
            $response = Http::asForm()->post(config('powerbi.token_url'), [
                'grant_type' => 'client_credentials',
                'client_id' => config('powerbi.client_id'),
                'client_secret' => config('powerbi.client_secret'),
                'resource' => 'https://analysis.windows.net/powerbi/api',
            ]);

            if ($response->failed()) {
                Log::error('Failed to obtain Power BI access token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Failed to obtain Power BI access token: '.$response->status());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Get all engagement records from Power BI dataset.
     * These are granular records (one per member per campaign).
     *
     * @return array<int, array>
     */
    public function getAllEngagements(): array
    {
        // Use fake data if credentials are not configured
        if (! $this->hasCredentials()) {
            return FakePowerBiData::getAllEngagements();
        }

        return Cache::remember('powerbi_all_engagements', $this->cacheTtl(), function () {
            $token = $this->getAccessToken();
            $url = $this->buildExecuteQueriesUrl();

            $body = [
                'queries' => [
                    [
                        'query' => "EVALUATE '(raw) Engagement'",
                    ],
                ],
                'serializerSettings' => [
                    'includeNulls' => true,
                ],
            ];

            $response = Http::withToken($token)->post($url, $body);

            if ($response->failed()) {
                Log::error('Failed to fetch engagements from Power BI', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Failed to fetch engagements: '.$response->status());
            }

            return $this->parsePowerBiResponse($response->json());
        });
    }

    /**
     * Get all engagement records for a specific campaign.
     *
     * @return array<int, array>
     */
    public function getEngagementsByCampaign(string $campaignId): array
    {
        // Use fake data if credentials are not configured
        if (! $this->hasCredentials()) {
            return FakePowerBiData::getEngagementsByCampaign($campaignId);
        }

        $cacheKey = 'powerbi_engagements_'.md5($campaignId);

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($campaignId) {
            $token = $this->getAccessToken();
            $url = $this->buildExecuteQueriesUrl();

            $body = [
                'queries' => [
                    [
                        'query' => "EVALUATE FILTER('(raw) Engagement', '(raw) Engagement'[Campaign ID] = \"$campaignId\")",
                    ],
                ],
                'serializerSettings' => [
                    'includeNulls' => true,
                ],
            ];

            $response = Http::withToken($token)->post($url, $body);

            if ($response->failed()) {
                Log::error('Failed to fetch campaign engagements from Power BI', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Failed to fetch campaign engagements: '.$response->status());
            }

            return $this->parsePowerBiResponse($response->json());
        });
    }

    /**
     * Get members with a specific status for a campaign.
     * Used for drilling down into metrics (e.g., "who opened this email?").
     *
     * @param  string  $campaignId  Campaign ID
     * @param  string  $status  Member Status (Opened, Clicked, Bounced, Sent, etc.)
     * @return array<int, array{member_id: string, first_name: string, last_name: string, email: string, company: string, status_update_date: string}>
     */
    public function getMembersByStatus(string $campaignId, string $metric): array
    {
        // Use fake data if credentials are not configured
        if (! $this->hasCredentials()) {
            return FakePowerBiData::getMembersByStatus($campaignId, $metric);
        }

        $cacheKey = 'powerbi_members_'.md5($campaignId.'_'.$metric);

        $rows = Cache::remember($cacheKey, $this->cacheTtl(), function () use ($campaignId, $metric) {
            $token = $this->getAccessToken();
            $url = $this->buildExecuteQueriesUrl();

            $body = [
                'queries' => [
                    [
                        'query' => $this->buildMembersByMetricQuery($campaignId, $metric),
                    ],
                ],
                'serializerSettings' => [
                    'includeNulls' => true,
                ],
            ];

            $response = Http::withToken($token)->post($url, $body);

            if ($response->failed()) {
                Log::error('Failed to fetch members by metric from Power BI', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'campaign_id' => $campaignId,
                    'metric' => $metric,
                ]);

                throw new \Exception('Failed to fetch members by metric: '.$response->status());
            }

            return $this->parsePowerBiResponse($response->json());
        });

        return PowerBiDataTransformer::transformMemberDetails($rows);
    }

    /**
     * Build a DAX query for member drill-down by campaign summary metric.
     */
    private function buildMembersByMetricQuery(string $campaignId, string $metric): string
    {
        $table = "'(raw) Engagement'";
        $campaignFilter = $table.'[Campaign ID] = "'.$campaignId.'"';

        return match ($metric) {
            'delivered' => 'EVALUATE FILTER('.$table.', AND('.$campaignFilter.', '.$table.'[Member Status] <> "Bounced"))',
            'unique-opens', 'total-opens' => 'EVALUATE FILTER('.$table.', AND('.$campaignFilter.', '.$table.'[Member Status] IN {"Opened", "Clicked"}))',
            'unique-clicks' => 'EVALUATE FILTER('.$table.', AND('.$campaignFilter.', '.$table.'[Member Status] = "Clicked"))',
            'hard-bounces' => 'EVALUATE FILTER('.$table.', AND('.$campaignFilter.', '.$table.'[Member Status] = "Bounced"))',
            'registered-appointment' => 'EVALUATE FILTER('.$table.', AND('.$campaignFilter.', '.$table.'[Member Status] IN {"Registered", "Schedule Appointment"}))',
            'Opened', 'Clicked', 'Bounced', 'Sent' => 'EVALUATE FILTER('.$table.', AND('.$campaignFilter.', '.$table.'[Member Status] = "'.$metric.'"))',
            default => throw new \InvalidArgumentException("Unknown member metric: {$metric}"),
        };
    }

    /**
     * Get all unique campaigns from engagement data.
     *
     * @return array<int, array{campaign_id: string, campaign_name: string, business_unit: string, start_date: string}>
     */
    public function getUniqueCampaigns(): array
    {
        // Use fake data if credentials are not configured
        if (! $this->hasCredentials()) {
            return FakePowerBiData::getUniqueCampaigns();
        }

        return Cache::remember('powerbi_campaigns', $this->cacheTtl(), function () {
            $token = $this->getAccessToken();
            $url = $this->buildExecuteQueriesUrl();

            $body = [
                'queries' => [
                    [
                        'query' => "EVALUATE SUMMARIZECOLUMNS('(raw) Engagement'[Campaign ID], '(raw) Engagement'[Campaign Name], '(raw) Engagement'[Reporting Business Unit], '(raw) Engagement'[Start Date])",
                    ],
                ],
                'serializerSettings' => [
                    'includeNulls' => true,
                ],
            ];

            $response = Http::withToken($token)->post($url, $body);

            if ($response->failed()) {
                Log::error('Failed to fetch unique campaigns from Power BI', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Failed to fetch unique campaigns: '.$response->status());
            }

            $rows = $this->parsePowerBiResponse($response->json());

            return array_map(function ($row) {
                return [
                    'campaign_id' => $row['(raw) Engagement[Campaign ID]'] ?? '',
                    'campaign_name' => $row['(raw) Engagement[Campaign Name]'] ?? '',
                    'business_unit' => $row['(raw) Engagement[Reporting Business Unit]'] ?? '',
                    'start_date' => $row['(raw) Engagement[Start Date]'] ?? '',
                ];
            }, $rows);
        });
    }

    /**
     * Get campaign analytics from the Email Campaign Metrics table.
     *
     * @return array{
     *     campaign_id: string,
     *     campaign_name: string,
     *     segment: string|null,
     *     summary: array<string, mixed>,
     *     emails: array<int, array<string, mixed>>
     * }|null
     */
    public function getCampaignMetrics(string $campaignId): ?array
    {
        if (! $this->hasCredentials()) {
            return FakePowerBiData::getCampaignMetrics($campaignId);
        }

        $cacheKey = 'powerbi_campaign_metrics_'.md5($campaignId);

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($campaignId) {
            return $this->fetchEmailCampaignMetrics($campaignId);
        });
    }

    /**
     * Fetch all email rows for a campaign from Email Campaign Metrics.
     *
     * @return array{
     *     campaign_id: string,
     *     campaign_name: string,
     *     segment: string|null,
     *     summary: array<string, mixed>,
     *     emails: array<int, array<string, mixed>>
     * }|null
     */
    private function fetchEmailCampaignMetrics(string $campaignId): ?array
    {
        $token = $this->getAccessToken();
        $url = $this->buildExecuteQueriesUrl();

        $body = [
            'queries' => [
                [
                    'query' => "EVALUATE FILTER('(raw) Email Campaign Metrics', '(raw) Email Campaign Metrics'[Campaign ID] = \"$campaignId\")",
                ],
            ],
            'serializerSettings' => [
                'includeNulls' => true,
            ],
        ];

        $response = Http::withToken($token)->post($url, $body);

        if ($response->failed()) {
            Log::error('Failed to fetch Email Campaign Metrics from Power BI', [
                'status' => $response->status(),
                'body' => $response->body(),
                'campaign_id' => $campaignId,
            ]);

            throw new \Exception('Failed to fetch campaign metrics: '.$response->status());
        }

        $rows = $this->parsePowerBiResponse($response->json());

        return PowerBiDataTransformer::buildCampaignAnalyticsFromEmailRows($rows);
    }

    /**
     * Parse Power BI executeQueries response into array format.
     *
     * @return array<int, array>
     */
    private function parsePowerBiResponse(array $response): array
    {
        $results = [];

        if (! isset($response['results'])) {
            return $results;
        }

        foreach ($response['results'] as $result) {
            if (! isset($result['tables'])) {
                continue;
            }

            foreach ($result['tables'] as $table) {
                if (! isset($table['rows'])) {
                    continue;
                }

                foreach ($table['rows'] as $row) {
                    $results[] = $row;
                }
            }
        }

        return $results;
    }

    /**
     * Get embed token for a specific Power BI report.
     * This allows embedding Power BI reports in the application.
     */
    public function getEmbedToken(string $reportId): string
    {
        if (! $this->hasCredentials()) {
            throw new \Exception('Power BI credentials not configured');
        }

        $token = $this->getAccessToken();
        $workspaceId = config('powerbi.workspace_id');

        $url = "https://api.powerbi.com/v1.0/myorg/groups/$workspaceId/reports/$reportId/GenerateToken";

        $response = Http::withToken($token)->post($url, [
            'accessLevel' => 'View',
        ]);

        if ($response->failed()) {
            Log::error('Failed to generate Power BI embed token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to generate embed token: '.$response->status());
        }

        return $response->json('token');
    }

    /**
     * Build the URL for executeQueries endpoint.
     */
    private function buildExecuteQueriesUrl(): string
    {
        $workspaceId = config('powerbi.workspace_id');
        $datasetId = config('powerbi.dataset_id');

        return 'https://api.powerbi.com/v1.0/myorg/groups/'.$workspaceId.'/datasets/'.$datasetId.'/executeQueries';
    }

    /**
     * Return configured cache TTL in seconds.
     * Set POWERBI_CACHE_TTL=0 in .env to disable caching.
     */
    private function cacheTtl(): int
    {
        return (int) config('powerbi.cache_ttl', 30 * 60);
    }
}
