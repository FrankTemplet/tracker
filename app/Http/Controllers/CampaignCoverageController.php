<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesCampaigns;
use App\Services\PowerBiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Diagnostic page: which campaigns actually have email metrics behind them.
 *
 * The dashboard, events and webinars pages read their metric tiles from
 * '(raw) Email Campaign Metrics'. Campaigns missing from that table render no
 * tiles at all, which looks like a bug in the app rather than a gap in the
 * source. This page makes the gap explicit and countable.
 */
class CampaignCoverageController extends Controller
{
    use AuthorizesCampaigns;

    public function __construct(protected PowerBiService $powerBiService) {}

    public function index(Request $request): Response
    {
        $allowedRegions = $request->user()->allowedRegions();

        try {
            // Same visibility rule the campaign pages apply: a user only sees
            // campaigns whose name starts with one of their regions.
            $all = $this->powerBiService->getEmailMetricsCoverage();

            $campaigns = array_values(array_filter(
                $all,
                fn (array $campaign) => $this->campaignNameInRegions($campaign['campaign_name'], $allowedRegions),
            ));

            return Inertia::render('campaign-coverage', [
                'campaigns' => $campaigns,
                'allowedRegions' => $allowedRegions,
                // The region filter matches on the name prefix, so campaigns whose
                // name starts with something else fall out for every user. Sending
                // the unfiltered totals keeps that gap visible instead of silently
                // shrinking the denominator.
                'datasetTotal' => count($all),
                'datasetWithMetrics' => count(array_filter($all, fn (array $c) => $c['in_catalogue'])),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load campaign coverage', ['error' => $e->getMessage()]);

            return Inertia::render('campaign-coverage', [
                'campaigns' => [],
                'allowedRegions' => $allowedRegions,
                'datasetTotal' => 0,
                'datasetWithMetrics' => 0,
                'error' => 'No se pudo cargar la cobertura de campañas. Intenta de nuevo más tarde.',
            ]);
        }
    }
}
