<?php

namespace App\Http\Controllers;

use App\Services\PowerBiDataTransformer;
use App\Services\PowerBiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PowerBiController extends Controller
{
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

        // Users can only filter by a region they are assigned to
        if ($selectedRegion && ! in_array($selectedRegion, $allowedRegions, true)) {
            $selectedRegion = null;
        }

        try {
            $campaigns = [];

            // Only fetch campaigns if both region and year are selected
            if ($selectedRegion && $selectedYear) {
                // Fetch unique campaigns from Power BI (already deduplicated)
                $rawCampaigns = $this->powerBiService->getUniqueCampaigns();
                $allCampaigns = PowerBiDataTransformer::transformCampaigns($rawCampaigns);

                // Filter by region and year
                $campaigns = array_values(array_filter($allCampaigns, function ($campaign) use ($selectedRegion, $selectedYear, $allowedRegions, $page) {
                    $campaignName = strtolower($campaign['name']);

                    // Prefix must be one of the user's allowed regions
                    // (also drops names starting with a number)
                    $hasAllowedPrefix = $this->campaignNameInRegions($campaignName, $allowedRegions);

                    // Check if campaign contains the region (carib, latam or networks)
                    $hasRegion = str_contains($campaignName, $selectedRegion);

                    // Check if campaign contains the year
                    $hasYear = str_contains($campaignName, $selectedYear);

                    return $hasAllowedPrefix && $hasRegion && $hasYear
                        && $this->campaignMatchesPage($campaignName, $page);
                }));
            }

            // A campaign is only accessible when it appears in the user's filtered list
            if ($selectedCampaignId && ! in_array($selectedCampaignId, array_column($campaigns, 'id'), true)) {
                $selectedCampaignId = null;
            }

            $analytics = null;

            if ($selectedCampaignId) {
                try {
                    $analytics = $this->powerBiService->getCampaignMetrics($selectedCampaignId);

                    if ($analytics !== null) {
                        try {
                            $registeredMembers = $this->powerBiService->getMembersByStatus($selectedCampaignId, 'registered-appointment');
                            $analytics['summary']['registered_appointment'] = count($registeredMembers);

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
     * Check if a campaign name starts with one of the given region prefixes.
     *
     * @param  list<string>  $allowedRegions
     */
    protected function campaignNameInRegions(string $campaignName, array $allowedRegions): bool
    {
        $name = strtolower($campaignName);

        foreach ($allowedRegions as $region) {
            if (str_starts_with($name, $region)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a campaign belongs to one of the user's allowed regions.
     *
     * @param  list<string>  $allowedRegions
     */
    protected function campaignIsAllowed(string $campaignId, array $allowedRegions): bool
    {
        foreach ($this->powerBiService->getUniqueCampaigns() as $campaign) {
            if (($campaign['campaign_id'] ?? '') === $campaignId) {
                return $this->campaignNameInRegions($campaign['campaign_name'] ?? '', $allowedRegions);
            }
        }

        return false;
    }

    /**
     * Get embed token for a specific Power BI report.
     */
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
