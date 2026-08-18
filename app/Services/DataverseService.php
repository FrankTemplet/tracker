<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataverseService
{
    /**
     * Columns selected from cr21a_emailengagementlogs.
     *
     * @var array<int, string>
     */
    private const LOG_COLUMNS = [
        'cr21a_emailengagementlogid',
        'cr21a_recipientemail',
        'cr21a_emailname',
        'cr21a_emailsubject',
        'cr21a_campaignid',
        'cr21a_campaignname',
        'cr21a_listemailid',
        'cr21a_prospectid',
        'cr21a_datesent',
        'cr21a_delivered',
        'cr21a_opencount',
        'cr21a_clickcount',
        'cr21a_hardbounced',
        'cr21a_softbounced',
    ];

    /**
     * Check if Dataverse credentials are configured.
     */
    public function hasCredentials(): bool
    {
        return ! empty(config('dataverse.client_id'))
            && ! empty(config('dataverse.client_secret'))
            && ! empty(config('dataverse.tenant_id'))
            && ! empty(config('dataverse.url'));
    }

    /**
     * Get an access token from Azure AD using the client credentials flow.
     *
     * Dataverse requires a v2.0 token scoped to the environment URL, which is
     * why this does not reuse the Power BI token.
     */
    public function getAccessToken(): string
    {
        return Cache::remember('dataverse_access_token', 55 * 60, function () {
            $response = Http::asForm()->post(config('dataverse.token_url'), [
                'grant_type' => 'client_credentials',
                'client_id' => config('dataverse.client_id'),
                'client_secret' => config('dataverse.client_secret'),
                'scope' => $this->scope(),
            ]);

            if ($response->failed()) {
                Log::error('Failed to obtain Dataverse access token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Failed to obtain Dataverse access token: '.$response->status());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Get one page of email engagement logs for a single send of a campaign.
     *
     * The email name matches Email Campaign Metrics[Name] in Power BI, and the
     * campaign ID keeps sends that share a name across campaigns apart.
     *
     * @param  string|null  $cursor  Skip token returned as next_cursor by a previous call
     * @return array{
     *     logs: array<int, array<string, mixed>>,
     *     next_cursor: string|null,
     *     total: int|null
     * }
     */
    public function getEmailEngagementLogs(
        string $campaignId,
        string $emailName,
        ?string $cursor = null,
        ?int $pageSize = null,
    ): array {
        $pageSize = $this->pageSize($pageSize);

        if (! $this->hasCredentials()) {
            throw new \Exception('Dataverse credentials are not configured.');
        }

        $cacheKey = 'dataverse_email_logs_'.md5($campaignId.'|'.$emailName.'|'.$cursor.'|'.$pageSize);

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($campaignId, $emailName, $cursor, $pageSize) {
            $response = $this->get('cr21a_emailengagementlogs', [
                '$select' => implode(',', self::LOG_COLUMNS),
                '$filter' => sprintf(
                    "cr21a_emailname eq '%s' and cr21a_campaignid eq '%s'",
                    $this->escape($emailName),
                    $this->escape($campaignId),
                ),
                '$orderby' => 'cr21a_datesent desc,cr21a_recipientemail asc',
                '$count' => 'true',
            ], $cursor, $pageSize);

            return [
                'logs' => array_map(
                    fn (array $row) => $this->transformLog($row),
                    $response['value'] ?? [],
                ),
                'next_cursor' => $this->extractSkipToken($response['@odata.nextLink'] ?? null),
                'total' => isset($response['@odata.count']) ? (int) $response['@odata.count'] : null,
            ];
        });
    }

    /**
     * Get every engagement log for a send, following pagination to the end.
     *
     * Only use this for exports; a large send can return tens of thousands of
     * rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllEmailEngagementLogs(string $campaignId, string $emailName, int $maxRows = 50000): array
    {
        $logs = [];
        $cursor = null;

        do {
            $page = $this->getEmailEngagementLogs($campaignId, $emailName, $cursor, 5000);
            $logs = array_merge($logs, $page['logs']);
            $cursor = $page['next_cursor'];
        } while ($cursor !== null && count($logs) < $maxRows);

        return array_slice($logs, 0, $maxRows);
    }

    /**
     * Run a GET against the Dataverse Web API.
     *
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function get(string $entitySet, array $query, ?string $cursor = null, ?int $pageSize = null): array
    {
        if ($cursor !== null && $cursor !== '') {
            $query['$skiptoken'] = $cursor;
        }

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'Accept' => 'application/json',
                'OData-MaxVersion' => '4.0',
                'OData-Version' => '4.0',
                'Prefer' => 'odata.maxpagesize='.$this->pageSize($pageSize),
            ])
            ->get($this->buildUrl($entitySet), $query);

        if ($response->failed()) {
            Log::error('Failed to fetch data from Dataverse', [
                'status' => $response->status(),
                'body' => $response->body(),
                'entity_set' => $entitySet,
            ]);

            throw new \Exception('Failed to fetch Dataverse data: '.$response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Transform a raw log row into the shape used by the frontend.
     *
     * @param  array<string, mixed>  $row
     * @return array{
     *     id: string,
     *     recipient_email: string,
     *     email_name: string,
     *     email_subject: string,
     *     campaign_id: string,
     *     campaign_name: string,
     *     list_email_id: string,
     *     prospect_id: string,
     *     date_sent: string|null,
     *     delivered: int,
     *     opens: int,
     *     clicks: int,
     *     hard_bounced: int,
     *     soft_bounced: int
     * }
     */
    private function transformLog(array $row): array
    {
        return [
            'id' => (string) ($row['cr21a_emailengagementlogid'] ?? ''),
            'recipient_email' => (string) ($row['cr21a_recipientemail'] ?? ''),
            'email_name' => (string) ($row['cr21a_emailname'] ?? ''),
            'email_subject' => (string) ($row['cr21a_emailsubject'] ?? ''),
            'campaign_id' => (string) ($row['cr21a_campaignid'] ?? ''),
            'campaign_name' => (string) ($row['cr21a_campaignname'] ?? ''),
            'list_email_id' => (string) ($row['cr21a_listemailid'] ?? ''),
            'prospect_id' => (string) ($row['cr21a_prospectid'] ?? ''),
            'date_sent' => $row['cr21a_datesent'] ?? null,
            'delivered' => (int) ($row['cr21a_delivered'] ?? 0),
            'opens' => (int) ($row['cr21a_opencount'] ?? 0),
            'clicks' => (int) ($row['cr21a_clickcount'] ?? 0),
            'hard_bounced' => (int) ($row['cr21a_hardbounced'] ?? 0),
            'soft_bounced' => (int) ($row['cr21a_softbounced'] ?? 0),
        ];
    }

    /**
     * Pull the skip token out of an @odata.nextLink so the caller never has to
     * handle a full URL.
     */
    private function extractSkipToken(?string $nextLink): ?string
    {
        if (! $nextLink) {
            return null;
        }

        parse_str((string) parse_url($nextLink, PHP_URL_QUERY), $params);

        $token = $params['$skiptoken'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Escape a value for use inside an OData string literal.
     */
    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function buildUrl(string $entitySet): string
    {
        return config('dataverse.url').'/api/data/'.config('dataverse.api_version').'/'.$entitySet;
    }

    /**
     * OAuth scope, derived from the environment URL when not set explicitly.
     */
    private function scope(): string
    {
        return config('dataverse.scope') ?: config('dataverse.url').'/.default';
    }

    private function pageSize(?int $pageSize = null): int
    {
        $size = $pageSize ?? (int) config('dataverse.page_size', 100);

        return max(1, min($size, 5000));
    }

    private function cacheTtl(): int
    {
        return (int) config('dataverse.cache_ttl', 30 * 60);
    }
}
