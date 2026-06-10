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
     * Show the Power BI dashboard.
     */
    public function dashboard(Request $request): Response
    {
        $selectedRegion = $request->query('region');
        $selectedYear = $request->query('year');
        $selectedCampaignId = $request->query('campaign_id');

        try {
            $campaigns = [];

            // Only fetch campaigns if both region and year are selected
            if ($selectedRegion && $selectedYear) {
                // Fetch unique campaigns from Power BI (already deduplicated)
                $rawCampaigns = $this->powerBiService->getUniqueCampaigns();
                $allCampaigns = PowerBiDataTransformer::transformCampaigns($rawCampaigns);

                // Filter by region and year
                $campaigns = array_values(array_filter($allCampaigns, function ($campaign) use ($selectedRegion, $selectedYear) {
                    $campaignName = strtolower($campaign['name']);
                    $region = strtolower($selectedRegion);

                    // Check if campaign contains the region (carib or latam)
                    $hasRegion = str_contains($campaignName, $region);

                    // Check if campaign contains the year
                    $hasYear = str_contains($campaignName, $selectedYear);

                    return $hasRegion && $hasYear;
                }));
            }

            $analytics = null;

            if ($selectedCampaignId) {
                try {
                    $analytics = $this->powerBiService->getCampaignMetrics($selectedCampaignId);
                } catch (\Exception $e) {
                    Log::error('Failed to load campaign metrics for dashboard', [
                        'campaign_id' => $selectedCampaignId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return Inertia::render('dashboard', [
                'campaigns' => $campaigns,
                'selectedCampaignId' => $selectedCampaignId,
                'selectedRegion' => $selectedRegion,
                'selectedYear' => $selectedYear,
                'analytics' => $analytics,
                'lastUpdated' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load Power BI dashboard', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return Inertia::render('dashboard', [
                'campaigns' => [],
                'selectedCampaignId' => null,
                'selectedRegion' => $selectedRegion,
                'selectedYear' => $selectedYear,
                'error' => 'Failed to load dashboard data. Please try again later.',
            ]);
        }
    }

    /**
     * Get all available email campaigns.
     */
    public function campaigns(): JsonResponse
    {
        try {
            // Fetch unique campaigns from Power BI (already deduplicated)
            $rawCampaigns = $this->powerBiService->getUniqueCampaigns();
            $campaigns = PowerBiDataTransformer::transformCampaigns($rawCampaigns);

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
    public function campaignMetrics(string $campaignId): JsonResponse
    {
        try {
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
    public function campaignMembers(string $campaignId, string $metric): JsonResponse
    {
        try {
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
