<?php

namespace App\Http\Controllers;

use App\Services\PowerBiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LeadsController extends Controller
{
    public function __construct(protected PowerBiService $powerBiService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isCarib = $user->region === 'carib';
        $region = $isCarib ? 'carib' : 'latam';

        try {
            $data = $this->powerBiService->getLeadsData($region);

            $summary = $isCarib
                ? [
                    'leads_created' => $data['summary']['leads_created'],
                    'leads_assigned' => $data['summary']['leads_assigned'],
                    'mqls' => $data['summary']['mqls'],
                    'sqls' => $data['summary']['sqls'],
                ]
                : [
                    'leads_assigned' => $data['summary']['leads_assigned'],
                    'mqls' => $data['summary']['mqls'],
                    'sqls' => $data['summary']['sqls'],
                ];

            return Inertia::render('leads', [
                'variant' => $region,
                'summary' => $summary,
                'leads' => $data['leads'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load leads data', ['error' => $e->getMessage()]);

            return Inertia::render('leads', [
                'variant' => $region,
                'summary' => $isCarib
                    ? ['leads_created' => 0, 'leads_assigned' => 0, 'mqls' => 0, 'sqls' => 0]
                    : ['leads_assigned' => 0, 'mqls' => 0, 'sqls' => 0],
                'leads' => [],
                'error' => 'Failed to load leads data. Please try again later.',
            ]);
        }
    }
}
