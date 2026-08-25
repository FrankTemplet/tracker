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

        // Both sides of the comparison must be the same length. The IDs that
        // reach this method come from '(raw) Engagement'[Campaign ID] in their
        // 18-character form, while the DAX side is truncated to 15, so the
        // filter never matched until the incoming ID is truncated too.
        $campaignKey = PowerBiDataTransformer::normalizeSalesforceId($campaignId);

        $body = [
            'queries' => [
                [
                    'query' => "EVALUATE FILTER('(raw) Email Campaign Metrics', LEFT('(raw) Email Campaign Metrics'[Campaign ID], 15) = \"$campaignKey\")",
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
     * Get all leads from (raw) Lead v2, filtered by region countries.
     *
     * @param  'carib'|'latam'  $region
     * @return array{summary: array{leads_created: int, mqls: int, sqls: int}, leads: array<int, array>}
     */
    public function getLeadsData(string $region): array
    {
        $cacheKey = 'powerbi_leads_v2_'.$region;

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($region) {
            $token = $this->getAccessToken();
            $url = $this->buildExecuteQueriesUrl();

            $countryFilter = $this->buildLeadsCountryFilter($region);

            $response = Http::withToken($token)->post($url, [
                'queries' => [[
                    'query' => "EVALUATE FILTER('(raw) Lead v2', {$countryFilter})",
                ]],
                'serializerSettings' => ['includeNulls' => true],
            ]);

            if ($response->failed()) {
                Log::error('Failed to fetch leads from Power BI', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to fetch leads: '.$response->status());
            }

            $rows = $this->parsePowerBiResponse($response->json());

            $leadsCreated = 0;
            $leadsAssigned = 0;
            $mqls = 0;
            $sqls = 0;
            $leads = [];

            // TODO: Consider using PowerBiDataTransformer for this as well, but it may be overkill for now.

            foreach ($rows as $row) {
                $createdBy = $row['(raw) Lead v2[Created By]'] ?? '';
                $createdAlias = $row['(raw) Lead v2[Created Alias]'] ?? '';
                $stage = $row['(raw) Lead v2[Lead Stage]'] ?? '';

                if ($createdBy === 'Sales Outcomes Lead Triage') {
                    $leadsCreated++;
                }

                if ($createdAlias === 'b2bmausr' || $createdAlias === 'LeadTrge') {
                    $leadsAssigned++;
                }

                if ($stage === 'MQL') {
                    $mqls++;
                }

                if ($stage === 'SQL') {
                    $sqls++;
                }

                $firstName = $row['(raw) Lead v2[First Name]'] ?? '';
                $lastName = $row['(raw) Lead v2[Last Name]'] ?? '';
                $name = trim("$firstName $lastName") ?: ($row['(raw) Lead v2[Company / Account]'] ?? '');

                $leads[] = [
                    'lead_id' => $row['(raw) Lead v2[Lead ID]'] ?? '',
                    'name' => $name,
                    'owner' => $row['(raw) Lead v2[Lead Owner]'] ?? '',
                    'email' => $row['(raw) Lead v2[Email]'] ?? '',
                    'company' => $row['(raw) Lead v2[Company / Account]'] ?? '',
                    'created_date' => $row['(raw) Lead v2[Create Date]'] ?? '',
                    'country' => $row['(raw) Lead v2[Country Normalized]'] ?? '',
                    'lead_stage' => $stage,
                    'created_by' => $createdBy,
                    'created_alias' => $createdAlias,
                    'lead_source' => $row['(raw) Lead v2[Lead Source]'] ?? '',
                    'lead_status' => $row['(raw) Lead v2[Lead Status]'] ?? '',
                ];
            }

            return [
                'summary' => [
                    'leads_created' => $leadsCreated,
                    'leads_assigned' => $leadsAssigned,
                    'mqls' => $mqls,
                    'sqls' => $sqls,
                ],
                'leads' => $leads,
            ];
        });
    }

    /**
     * Get all '(raw) Lead History' rows, grouped by 15-character Lead ID.
     *
     * The whole table is ~14k rows, so it is fetched once and cached rather than
     * queried per lead. Both the aging metrics attached to getLeadsData() and the
     * per-lead timeline endpoint read from this single cached payload, so opening
     * a lead never costs an extra Power BI round trip.
     *
     * @return array<string, array<int, array>>
     */
    public function getLeadHistoryRows(): array
    {
        return Cache::remember('powerbi_lead_history_rows', $this->cacheTtl(), function () {
            $token = $this->getAccessToken();
            $url = $this->buildExecuteQueriesUrl();

            // Only the columns the timeline needs — the table has 17.
            // Power BI returns SELECTCOLUMNS aliases wrapped in brackets, so
            // "lead_id" comes back on the response as the key "[lead_id]".
            $dax = <<<'DAX'
                EVALUATE
                SELECTCOLUMNS(
                    '(raw) Lead History',
                    "lead_id", '(raw) Lead History'[Lead ID],
                    "edit_date", '(raw) Lead History'[Edit Date],
                    "field", '(raw) Lead History'[Field / Event],
                    "old_value", '(raw) Lead History'[Old Value],
                    "new_value", '(raw) Lead History'[New Value],
                    "edited_by", '(raw) Lead History'[Edited By]
                )
                DAX;

            $response = Http::withToken($token)->post($url, [
                'queries' => [['query' => $dax]],
                'serializerSettings' => ['includeNulls' => true],
            ]);

            if ($response->failed()) {
                Log::error('Failed to fetch lead history from Power BI', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to fetch lead history: '.$response->status());
            }

            $grouped = [];

            foreach ($this->parsePowerBiResponse($response->json()) as $row) {
                $leadId = PowerBiDataTransformer::normalizeLeadId($row['[lead_id]'] ?? '');

                if ($leadId === '') {
                    continue;
                }

                $grouped[$leadId][] = [
                    'edit_date' => $row['[edit_date]'] ?? '',
                    'field' => $row['[field]'] ?? '',
                    'old_value' => $row['[old_value]'] ?? '',
                    'new_value' => $row['[new_value]'] ?? '',
                    'edited_by' => $row['[edited_by]'] ?? '',
                ];
            }

            return $grouped;
        });
    }

    /**
     * Get the aging metrics and owner-reassignment timeline for a single lead.
     *
     * Aging is computed here rather than attached to every lead on the leads page:
     * it is only ever shown in the lead detail modal, and carrying it for all
     * ~3k leads more than doubled the Inertia payload. Both this and the leads
     * page read the same cached history, so this costs no extra Power BI query.
     *
     * @param  string  $createdDate  Raw '(raw) Lead v2'[Create Date] for this lead
     * @return array{aging: ?array, events: array<int, array>}
     */
    public function getLeadHistoryDetail(string $leadId, string $createdDate): array
    {
        $history = $this->getLeadHistoryRows();
        $rows = $history[PowerBiDataTransformer::normalizeLeadId($leadId)] ?? [];

        return [
            'aging' => PowerBiDataTransformer::buildLeadAging($createdDate, $rows),
            'events' => PowerBiDataTransformer::buildLeadHistoryTimeline($rows),
        ];
    }

    /**
     * Build DAX country filter for the leads region.
     */
    private function buildLeadsCountryFilter(string $region): string
    {
        $col = "'(raw) Lead v2'[Country Normalized]";

        // Caribbean: sovereign island nations + dependent territories only
        // "Networks - *" and "Business - *" groupings go to LATAM
        $caribCountries = [
            // Sovereign nations
            'Jamaica', 'Barbados', 'Bahamas', 'Antigua and Barbuda', 'Antigua',
            'Dominica', 'Grenada', 'St. Lucia', 'St. Kitts', 'St. Kitts & Nevis',
            'St. Vincent', 'St. Vincent & Grenadines', 'St. Vincent and the Grenadines', 'St. Vincent & the Grenadines',
            'Trinidad', 'Trinidad and Tobago', 'Trinidad & Tobago', 'trinidad-and-tobago',
            // Territories
            'Anguilla', 'British Virgin Islands', 'British Virgin Isalnds', 'british-virgin-islands',
            'Cayman', 'Montserrat', 'Turks & Caicos', 'Turks and Caicos',
            'Puerto Rico', 'Curacao', 'Curaçao', 'Bonaire', 'St. Eustatius',
            'St. Maarten', 'French St. Martin',
            // Regional grouping
            'Caribbean Region',
        ];

        // LATAM + Networks: Mexico + Central America + South America
        // All "Networks - *" groupings go here regardless of sub-region
        // All "Business - *" groupings go here
        // Wholesale goes here
        $latamCountries = [
            // Mexico
            'Mexico',
            'Dominican Republic', 'Dominican Republic', 'República Dominicana', 'Republica Dominicana', 'república-dominicana',
            // Central America
            'Guatemala', 'El Salvador', 'Honduras', 'Nicaragua', 'Costa Rica', 'Panama', 'Panamá', 'Belize',
            // South America
            'Colombia', 'Venezuela', 'Ecuador', 'Peru', 'Bolivia', 'Argentina', 'Chile',
            'Uruguay', 'Paraguay', 'Brazil', 'Guyana', 'Suriname',
            // Business groupings → LATAM
            'Business - Colombia', 'Business - Guatemala', 'Business - El Salvador',
            'Business - Honduras', 'Business - Dominican Republic',
            // Networks groupings → always LATAM + Networks
            'Networks - Mexico and Central America',
            'Networks - Andean (Colombia, Venezuela, Ecuador)',
            'Networks - United States of America',
            'Networks - Caribbean',
            // Other
            'Wholesale',
        ];

        $countries = $region === 'carib' ? $caribCountries : $latamCountries;

        $quoted = implode(', ', array_map(fn ($c) => '"'.addslashes($c).'"', $countries));

        return "{$col} IN {{$quoted}}";
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
