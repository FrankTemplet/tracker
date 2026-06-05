<?php

use App\Http\Controllers\PowerBiController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [PowerBiController::class, 'dashboard'])->name('dashboard');

    // Power BI API endpoints
    Route::prefix('api/powerbi')->name('powerbi.')->middleware('throttle:60,1')->group(function () {
        Route::get('campaigns', [PowerBiController::class, 'campaigns'])->name('campaigns');
        Route::get('campaigns/{campaignId}/emails', [PowerBiController::class, 'campaignEmails'])->name('campaign.emails');
        Route::get('emails/{emailId}/analytics', [PowerBiController::class, 'emailAnalytics'])->name('email.analytics');
        Route::get('embed-token/{reportId}', [PowerBiController::class, 'embedToken'])->name('embed.token');
    });
});

require __DIR__.'/settings.php';
