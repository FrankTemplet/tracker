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
     * Get available email campaigns from the Power BI dataset using DAX query.
     *
     * @return array<int, array>
     */
    public function getCampaigns(): array
    {
        // Use fake data if credentials are not configured
        if (! $this->hasCredentials()) {
            return FakePowerBiData::getCampaigns();
        }

        $token = $this->getAccessToken();
        $url = $this->buildExecuteQueriesUrl();

        $body = [
            'queries' => [
                [
                    'query' => "EVALUATE VALUES('REPORT - Campaign Tracker')",
                ],
            ],
            'serializerSettings' => [
                'includeNulls' => true,
            ],
        ];

        $response = Http::withToken($token)->post($url, $body);

        if ($response->failed()) {
            Log::error('Failed to fetch campaigns from Power BI', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to fetch campaigns: '.$response->status());
        }

        return $this->parsePowerBiResponse($response->json());
    }

    /**
     * Get sent emails for a specific campaign using DAX query.
     *
     * @return array<int, array>
     */
    public function getCampaignEmails(string $campaignName): array
    {
        // Use fake data if credentials are not configured
        if (! $this->hasCredentials()) {
            return FakePowerBiData::getCampaignEmails($campaignName);
        }

        $token = $this->getAccessToken();
        $url = $this->buildExecuteQueriesUrl();

        $body = [
            'queries' => [
                [
                    'query' => "EVALUATE FILTER('(raw email) Campaign%20Outcomes%20AllLiberty', '(raw email) Campaign%20Outcomes%20AllLiberty'[Campaign Name] = \"$campaignName\")",
                ],
            ],
            'serializerSettings' => [
                'includeNulls' => true,
            ],
        ];

        $response = Http::withToken($token)->post($url, $body);

        if ($response->failed()) {
            Log::error('Failed to fetch campaign emails from Power BI', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to fetch campaign emails: '.$response->status());
        }

        return $this->parsePowerBiResponse($response->json());
    }

    /**
     * Get all unique campaign names that have email data.
     *
     * @return array<int, string>
     */
    public function getEmailCampaignNames(): array
    {
        // Use fake data if credentials are not configured
        if (! $this->hasCredentials()) {
            return array_unique(array_map(
                fn ($campaign) => $campaign['id'],
                FakePowerBiData::getCampaigns()
            ));
        }

        $token = $this->getAccessToken();
        $url = $this->buildExecuteQueriesUrl();

        $body = [
            'queries' => [
                [
                    'query' => "EVALUATE VALUES('(raw email) Campaign%20Outcomes%20AllLiberty'[Campaign Name])",
                ],
            ],
            'serializerSettings' => [
                'includeNulls' => true,
            ],
        ];

        $response = Http::withToken($token)->post($url, $body);

        if ($response->failed()) {
            Log::error('Failed to fetch email campaign names from Power BI', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to fetch email campaign names: '.$response->status());
        }

        $rows = $this->parsePowerBiResponse($response->json());
        $names = [];

        foreach ($rows as $row) {
            $name = $row['(raw email) Campaign%20Outcomes%20AllLiberty[Campaign Name]'] ?? null;
            if ($name && ! in_array($name, $names)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Get analytics for a specific email.
     *
     * @return array{bounces: int, bounce_rate: float, opens: int, open_rate: float, clicks: int, click_rate: float}
     */
    public function getEmailAnalytics(string $emailId): array
    {
        // Use fake data if credentials are not configured
        if (! $this->hasCredentials()) {
            return FakePowerBiData::getEmailAnalytics($emailId);
        }

        // For now, extract analytics from email data
        // In production, you might want a separate query or table
        return [
            'bounces' => 0,
            'bounce_rate' => 0.0,
            'opens' => 0,
            'open_rate' => 0.0,
            'clicks' => 0,
            'click_rate' => 0.0,
        ];
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
     * Build the URL for executeQueries endpoint.
     */
    private function buildExecuteQueriesUrl(): string
    {
        $workspaceId = config('powerbi.workspace_id');
        $datasetId = config('powerbi.dataset_id');

        return 'https://api.powerbi.com/v1.0/myorg/groups/'.$workspaceId.'/datasets/'.$datasetId.'/executeQueries';
    }
}
