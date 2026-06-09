<?php

use App\Http\Controllers\PowerBiController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'auth/login', [
    'canRegister' => Features::enabled(Features::registration()),
    'canResetPassword' => Features::enabled(Features::resetPasswords()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [PowerBiController::class, 'dashboard'])->name('dashboard');

    // Power BI API endpoints
    Route::prefix('api/powerbi')->name('powerbi.')->middleware('throttle:60,1')->group(function () {
        Route::get('campaigns', [PowerBiController::class, 'campaigns'])->name('campaigns');
        Route::get('campaigns/{campaignId}/metrics', [PowerBiController::class, 'campaignMetrics'])->name('campaign.metrics');
        Route::get('campaigns/{campaignId}/members/{status}', [PowerBiController::class, 'campaignMembers'])->name('campaign.members');
        Route::get('embed-token/{reportId}', [PowerBiController::class, 'embedToken'])->name('embed.token');
    });
});

require __DIR__.'/settings.php';
