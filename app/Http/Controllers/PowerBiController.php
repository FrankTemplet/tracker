<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesCampaigns;
use App\Services\DataverseService;
use App\Services\PowerBiDataTransformer;
use App\Services\PowerBiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PowerBiController extends Controller
{
    use AuthorizesCampaigns;

    private const ALLOWED_YEARS = ['2025', '2026'];

    public function __construct(
        protected PowerBiService $powerBiService
    ) {}

    /**
     * Show the dashboard (campaigns that are neither events nor webinars).
     */
    public function dashboard(Request $request): Response
    {
        return $this->renderCampaignPage($request, 'dashboard');
    }

    /**
     * Show event campaigns (name contains "event").
     */
    public function events(Request $request): Response
    {
        return $this->renderCampaignPage($request, 'events');
    }

    /**
     * Show webinar campaigns (name contains "web").
     */
    public function webinars(Request $request): Response
    {
        return $this->renderCampaignPage($request, 'webinars');
    }

    /**
     * Render one of the campaign pages (dashboard, events or webinars).
     */
    protected function renderCampaignPage(Request $request, string $page): Response
    {
        $allowedRegions = $request->user()->allowedRegions();

        $selectedRegion = strtolower((string) $request->query('region')) ?: null;
        $selectedYear = $request->query('year');
        $selectedCampaignId = $request->query('campaign_id');

        // The page fills in whatever the request leaves out, so landing on it
        // never shows an empty portal. `clear=1` is how the Clear button asks
        // for the empty state on purpose; without it an empty query string is
        // read as "first visit" and gets the defaults.
        $applyDefaults = ! $request->boolean('clear');

        // Users can only filter by a region they are assigned to
        if ($selectedRegion && ! in_array($selectedRegion, $allowedRegions, true)) {
            $selectedRegion = null;
        }

        if ($selectedYear !== null && ! in_array((string) $selectedYear, self::ALLOWED_YEARS, true)) {
            $selectedYear = null;
        }

        if ($applyDefaults && $selectedRegion === null) {
            $selectedRegion = $allowedRegions[0] ?? null;
        }

        $yearWasDefaulted = false;

        if ($applyDefaults && $selectedYear === null) {
            $selectedYear = $this->defaultYear();
            $yearWasDefaulted = true;
        }

        try {
            $campaigns = [];

            // Only fetch campaigns if both region and year are selected
            if ($selectedRegion && $selectedYear) {
                // Fetch unique campaigns from Power BI (already deduplicated)
                $rawCampaigns = $this->powerBiService->getUniqueCampaigns();
                $allCampaigns = PowerBiDataTransformer::transformCampaigns($rawCampaigns);

                $campaigns = $this->filterCampaigns($allCampaigns, $selectedRegion, (string) $selectedYear, $allowedRegions, $page);

                // A defaulted year that turns up nothing is worse than useless:
                // it puts the user on a year with no campaigns on their very
                // first visit. A year the user picked is left alone.
                if ($campaigns === [] && $yearWasDefaulted) {
                    foreach ($this->fallbackYears() as $year) {
                        $candidates = $this->filterCampaigns($allCampaigns, $selectedRegion, $year, $allowedRegions, $page);

                        if ($candidates !== []) {
                            $selectedYear = $year;
                            $campaigns = $candidates;
                            break;
                        }
                    }
                }
            }

            // A campaign is only accessible when it appears in the user's filtered list
            if ($selectedCampaignId && ! in_array($selectedCampaignId, array_column($campaigns, 'id'), true)) {
                $selectedCampaignId = null;
            }

            if ($applyDefaults && $selectedCampaignId === null) {
                $selectedCampaignId = $this->mostRecentCampaignId($campaigns);
            }

            $analytics = null;

            if ($selectedCampaignId) {
                try {
                    $analytics = $this->powerBiService->getCampaignMetrics($selectedCampaignId);

                    if ($analytics !== null) {
                        try {
                            $registeredMembers = $this->powerBiService->getMembersByStatus($selectedCampaignId, 'registered-appointment');
                            $analytics['summary']['registered_appointment'] = count($registeredMembers);

                            $analytics['emails'] = $this->markRecipientCoverage($analytics['emails'] ?? []);

                            // Fetch campaign details from engagement table
                            $engagements = $this->powerBiService->getEngagementsByCampaign($selectedCampaignId);
                            if (! empty($engagements)) {
                                $firstRow = $engagements[0];
                                $analytics['primary_purpose'] = $firstRow['(raw) Engagement[Primary Campaign Purpose]'] ?? null;
                                $analytics['category'] = $firstRow['(raw) Engagement[Category]'] ?? null;
                                $analytics['sub_category'] = $firstRow['(raw) Engagement[Sub-Category]'] ?? null;
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to load engagement data for dashboard', [
                                'campaign_id' => $selectedCampaignId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to load campaign metrics for dashboard', [
                        'campaign_id' => $selectedCampaignId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return Inertia::render($page, [
                'campaigns' => $campaigns,
                'selectedCampaignId' => $selectedCampaignId,
                'selectedRegion' => $selectedRegion,
                'selectedYear' => $selectedYear,
                'availableRegions' => $allowedRegions,
                'analytics' => $analytics,
                'lastUpdated' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load Power BI dashboard', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return Inertia::render($page, [
                'campaigns' => [],
                'selectedCampaignId' => null,
                'selectedRegion' => $selectedRegion,
                'selectedYear' => $selectedYear,
                'availableRegions' => $allowedRegions,
                'error' => 'Failed to load dashboard data. Please try again later.',
            ]);
        }
    }

    /**
     * Get all available email campaigns.
     */
    public function campaigns(Request $request): JsonResponse
    {
        $allowedRegions = $request->user()->allowedRegions();

        try {
            // Fetch unique campaigns from Power BI (already deduplicated)
            $rawCampaigns = $this->powerBiService->getUniqueCampaigns();
            $campaigns = PowerBiDataTransformer::transformCampaigns($rawCampaigns);

            // Only expose campaigns from the user's allowed regions
            $campaigns = array_values(array_filter(
                $campaigns,
                fn (array $campaign) => $this->campaignNameInRegions($campaign['name'], $allowedRegions)
            ));

            return response()->json([
                'success' => true,
                'data' => $campaigns,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch campaigns', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get aggregated metrics for a specific campaign.
     */
    public function campaignMetrics(Request $request, string $campaignId): JsonResponse
    {
        try {
            if (! $this->campaignIsAllowed($campaignId, $request->user()->allowedRegions())) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this campaign.',
                ], 403);
            }

            $metrics = $this->powerBiService->getCampaignMetrics($campaignId);

            if ($metrics === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign metrics not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch campaign metrics', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch metrics. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get members with a specific status for a campaign (drill-down).
     */
    public function campaignMembers(Request $request, string $campaignId, string $metric): JsonResponse
    {
        try {
            if (! $this->campaignIsAllowed($campaignId, $request->user()->allowedRegions())) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this campaign.',
                ], 403);
            }

            $members = $this->powerBiService->getMembersByStatus($campaignId, $metric);

            return response()->json([
                'success' => true,
                'data' => $members,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to fetch campaign members', [
                'campaign_id' => $campaignId,
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch members. Please try again later.',
            ], 500);
        }
    }

    /**
     * Flag which sends can be drilled down to their recipients.
     *
     * Recipient-level data lives in Dataverse, which is loaded separately from
     * Power BI and lags it. Marking each send lets the UI say "not in the
     * recipient log" instead of opening an empty modal that reads as a bug.
     *
     * `null` means the coverage list could not be read; the UI keeps the
     * drill-down offered in that case rather than hiding a working feature
     * because of a transient Dataverse failure.
     *
     * @param  array<int, array<string, mixed>>  $emails
     * @return array<int, array<string, mixed>>
     */
    protected function markRecipientCoverage(array $emails): array
    {
        try {
            $covered = array_flip(app(DataverseService::class)->getSendNamesWithLogs());
        } catch (\Exception $e) {
            Log::warning('Could not read Dataverse recipient coverage', ['error' => $e->getMessage()]);

            return array_map(function (array $email) {
                $email['has_recipients'] = null;

                return $email;
            }, $emails);
        }

        return array_map(function (array $email) use ($covered) {
            $email['has_recipients'] = isset($covered[strtolower(trim((string) ($email['name'] ?? '')))]);

            return $email;
        }, $emails);
    }

    /**
     * Narrow the catalogue to the campaigns one page shows for a region and year.
     *
     * @param  array<int, array{id: string, name: string, business_unit: string, created_at: string}>  $allCampaigns
     * @param  list<string>  $allowedRegions
     * @return array<int, array{id: string, name: string, business_unit: string, created_at: string}>
     */
    protected function filterCampaigns(array $allCampaigns, string $region, string $year, array $allowedRegions, string $page): array
    {
        return array_values(array_filter($allCampaigns, function ($campaign) use ($region, $year, $allowedRegions, $page) {
            $campaignName = strtolower($campaign['name']);

            // Prefix must be one of the user's allowed regions
            // (also drops names starting with a number)
            $hasAllowedPrefix = $this->campaignNameInRegions($campaignName, $allowedRegions);

            // Check if campaign contains the region (carib, latam or networks)
            $hasRegion = str_contains($campaignName, $region);

            // Check if campaign contains the year
            $hasYear = str_contains($campaignName, $year);

            return $hasAllowedPrefix && $hasRegion && $hasYear
                && $this->campaignMatchesPage($campaignName, $page);
        }));
    }

    /**
     * The year a first visit lands on: this one when it is in range, otherwise
     * the latest year the pages support.
     */
    protected function defaultYear(): string
    {
        $currentYear = (string) now()->year;

        if (in_array($currentYear, self::ALLOWED_YEARS, true)) {
            return $currentYear;
        }

        $years = self::ALLOWED_YEARS;

        return (string) end($years);
    }

    /**
     * Years to try, newest first, when the defaulted year has no campaigns.
     *
     * @return list<string>
     */
    protected function fallbackYears(): array
    {
        $years = self::ALLOWED_YEARS;
        rsort($years);

        return array_values(array_diff($years, [$this->defaultYear()]));
    }

    /**
     * Pick the campaign a first visit opens on: the most recently started one.
     *
     * `created_at` comes from Power BI as free-form text ('Scheduled Date' is
     * not a typed date), so anything unparseable sorts last instead of being
     * trusted, and the list order decides when no date parses at all.
     *
     * @param  array<int, array{id: string, name: string, business_unit: string, created_at: string}>  $campaigns
     */
    protected function mostRecentCampaignId(array $campaigns): ?string
    {
        if ($campaigns === []) {
            return null;
        }

        $best = null;
        $bestTimestamp = null;

        foreach ($campaigns as $campaign) {
            $timestamp = strtotime((string) ($campaign['created_at'] ?? ''));

            if ($timestamp === false) {
                continue;
            }

            if ($bestTimestamp === null || $timestamp > $bestTimestamp) {
                $best = $campaign;
                $bestTimestamp = $timestamp;
            }
        }

        $best ??= $campaigns[0];

        return ($best['id'] ?? '') !== '' ? $best['id'] : null;
    }

    /**
     * Events lists campaigns whose name contains "event", Webinars those
     * containing "web" (unless they are events), and Dashboard the rest.
     */
    protected function campaignMatchesPage(string $campaignName, string $page): bool
    {
        $name = strtolower($campaignName);

        $isEvent = str_contains($name, 'event');
        $isWebinar = ! $isEvent && str_contains($name, 'web');

        return match ($page) {
            'events' => $isEvent,
            'webinars' => $isWebinar,
            default => ! $isEvent && ! $isWebinar,
        };
    }

    /**
     * Get embed token for a specific Power BI report.
     */
    /**
     * Return the aging metrics and owner-reassignment timeline for a single lead.
     *
     * Served from the same cached history payload the leads page already uses,
     * so opening a lead costs no extra Power BI round trip.
     */
    public function leadHistory(Request $request, string $leadId): JsonResponse
    {
        // Salesforce IDs are alphanumeric; reject anything else before it reaches DAX.
        if (! preg_match('/^[A-Za-z0-9]{15,18}$/', $leadId)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid lead ID.',
            ], 400);
        }

        try {
            $region = $request->user()->region === 'carib' ? 'carib' : 'latam';
            $leads = $this->powerBiService->getLeadsData($region)['leads'];

            $normalized = PowerBiDataTransformer::normalizeLeadId($leadId);
            $match = null;

            foreach ($leads as $lead) {
                if (PowerBiDataTransformer::normalizeLeadId($lead['lead_id'] ?? '') === $normalized) {
                    $match = $lead;
                    break;
                }
            }

            // A lead outside the caller's region is not theirs to look at.
            if ($match === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this lead.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $this->powerBiService->getLeadHistoryDetail($leadId, $match['created_date'] ?? ''),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch lead history', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch lead history. Please try again later.',
            ], 500);
        }
    }

    public function embedToken(string $reportId): JsonResponse
    {
        try {
            $token = $this->powerBiService->getEmbedToken($reportId);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate embed token', [
                'report_id' => $reportId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate embed token. Please try again later.',
            ], 500);
        }
    }
}
