<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesCampaigns;
use App\Services\DataverseService;
use App\Services\PowerBiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EmailEngagementController extends Controller
{
    use AuthorizesCampaigns;

    /**
     * Regions whose engagement logs live in Dataverse.
     *
     * Networks and LATAM will get their own source later, so their campaigns
     * are rejected instead of silently returning Carib-shaped data.
     *
     * @var list<string>
     */
    private const SUPPORTED_REGIONS = ['carib'];

    public function __construct(
        protected PowerBiService $powerBiService,
        protected DataverseService $dataverseService,
    ) {}

    /**
     * Get one page of per-recipient engagement logs for a single email send.
     */
    public function emailLogs(Request $request, string $campaignId): JsonResponse
    {
        $validated = $request->validate([
            'email_name' => ['required', 'string', 'max:255'],
            'cursor' => ['nullable', 'string', 'max:2000'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'engagement' => ['nullable', 'string', Rule::in(array_keys(DataverseService::ENGAGEMENT_FILTERS))],
        ]);

        $campaignName = $this->campaignName($campaignId);
        $allowedRegions = $request->user()->allowedRegions();

        if ($campaignName === null || ! $this->campaignNameInRegions($campaignName, $allowedRegions)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this campaign.',
            ], 403);
        }

        if (! $this->campaignNameInRegions($campaignName, self::SUPPORTED_REGIONS)) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient engagement is only available for Carib campaigns.',
            ], 422);
        }

        try {
            $page = $this->dataverseService->getEmailEngagementLogs(
                $campaignId,
                $validated['email_name'],
                $validated['cursor'] ?? null,
                $validated['page_size'] ?? null,
                $validated['engagement'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data' => $page['logs'],
                'next_cursor' => $page['next_cursor'],
                'total' => $page['total'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch email engagement logs', [
                'campaign_id' => $campaignId,
                'email_name' => $validated['email_name'],
                'engagement' => $validated['engagement'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recipient engagement. Please try again later.',
            ], 500);
        }
    }
}
