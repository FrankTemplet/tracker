<?php

namespace App\Http\Controllers;

use App\Services\FakePowerBiData;
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
        $selectedEmailId = $request->query('email_id');

        try {
            $campaigns = [];

            // Only fetch campaigns if both region and year are selected
            if ($selectedRegion && $selectedYear) {
                // Fetch campaigns from Power BI and filter by those that have email data
                $rawCampaigns = $this->powerBiService->getCampaigns();
                $allCampaigns = PowerBiDataTransformer::deduplicateCampaigns(
                    PowerBiDataTransformer::transformCampaigns($rawCampaigns)
                );

                // Get unique campaign names that have email data
                $emailCampaignNames = $this->powerBiService->getEmailCampaignNames();

                // Filter campaigns to only those with email data
                $filteredCampaigns = array_filter($allCampaigns, function ($campaign) use ($emailCampaignNames) {
                    return in_array($campaign['id'], $emailCampaignNames);
                });

                // Filter by region and year
                $campaigns = array_values(array_filter($filteredCampaigns, function ($campaign) use ($selectedRegion, $selectedYear) {
                    $campaignName = strtolower($campaign['name']);
                    $region = strtolower($selectedRegion);

                    // Check if campaign contains the region (carib or latam)
                    $hasRegion = str_contains($campaignName, $region);

                    // Check if campaign contains the year
                    $hasYear = str_contains($campaignName, $selectedYear);

                    return $hasRegion && $hasYear;
                }));
            }

            $emails = [];
            $analytics = null;
            $bouncesOpens = null;
            $engagement = null;

            // If a campaign is selected, fetch its emails
            if ($selectedCampaignId) {
                $rawEmails = $this->powerBiService->getCampaignEmails($selectedCampaignId);
                $emails = PowerBiDataTransformer::transformEmails($rawEmails);
            }

            // If an email is selected, find it and extract analytics
            if ($selectedEmailId && count($emails) > 0) {
                $selectedEmail = collect($emails)->firstWhere('id', $selectedEmailId);
                if ($selectedEmail) {
                    // Find the raw email data to extract analytics
                    $rawEmails = $this->powerBiService->getCampaignEmails($selectedCampaignId);
                    $rawEmail = collect($rawEmails)->first(function ($email) use ($selectedEmailId) {
                        return PowerBiDataTransformer::stableEmailId($email) === $selectedEmailId;
                    });
                    if ($rawEmail) {
                        $analytics = PowerBiDataTransformer::extractEmailAnalytics($rawEmail);
                        $bouncesOpens = [
                            'bounces' => $analytics['bounces'],
                            'opens' => $analytics['opens'],
                        ];
                    }

                    // TODO: Replace with real endpoint when engagement data is available
                    $engagement = FakePowerBiData::getEngagementData($selectedEmailId);
                }
            }

            return Inertia::render('dashboard', [
                'campaigns' => $campaigns,
                'emails' => $emails,
                'selectedCampaignId' => $selectedCampaignId,
                'selectedEmailId' => $selectedEmailId,
                'selectedRegion' => $selectedRegion,
                'selectedYear' => $selectedYear,
                'analytics' => $analytics,
                'bouncesOpens' => $bouncesOpens,
                'engagement' => $engagement,
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
                'emails' => [],
                'selectedCampaignId' => $selectedCampaignId,
                'selectedEmailId' => $selectedEmailId,
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
            $rawCampaigns = $this->powerBiService->getCampaigns();
            $allCampaigns = PowerBiDataTransformer::deduplicateCampaigns(
                PowerBiDataTransformer::transformCampaigns($rawCampaigns)
            );

            // Get unique campaign names that have email data
            $emailCampaignNames = $this->powerBiService->getEmailCampaignNames();

            // Filter campaigns to only those with email data
            $campaigns = array_values(array_filter($allCampaigns, function ($campaign) use ($emailCampaignNames) {
                return in_array($campaign['id'], $emailCampaignNames);
            }));

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
     * Get sent emails for a specific campaign.
     */
    public function campaignEmails(string $campaignName): JsonResponse
    {
        try {
            $rawEmails = $this->powerBiService->getCampaignEmails($campaignName);
            $emails = PowerBiDataTransformer::transformEmails($rawEmails);

            return response()->json([
                'success' => true,
                'data' => $emails,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch campaign emails', [
                'campaign_name' => $campaignName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch emails. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get analytics for a specific email.
     */
    public function emailAnalytics(string $emailId): JsonResponse
    {
        try {
            // This would need more context to work properly in a real scenario
            return response()->json([
                'success' => true,
                'data' => [
                    'bounces' => 0,
                    'bounce_rate' => 0.0,
                    'opens' => 0,
                    'open_rate' => 0.0,
                    'clicks' => 0,
                    'click_rate' => 0.0,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch email analytics', [
                'email_id' => $emailId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics. Please try again later.',
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
