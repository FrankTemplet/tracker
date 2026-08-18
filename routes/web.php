<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\EmailEngagementController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\PowerBiController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'auth/login', [
    'canRegister' => Features::enabled(Features::registration()),
    'canResetPassword' => Features::enabled(Features::resetPasswords()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [PowerBiController::class, 'dashboard'])->name('dashboard');
    Route::get('events', [PowerBiController::class, 'events'])->name('events');
    Route::get('webinars', [PowerBiController::class, 'webinars'])->name('webinars');
    Route::get('leads', [LeadsController::class, 'index'])->name('leads');

    // User management (admins only)
    Route::middleware('admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    // Power BI API endpoints
    Route::prefix('api/powerbi')->name('powerbi.')->middleware('throttle:60,1')->group(function () {
        Route::get('campaigns', [PowerBiController::class, 'campaigns'])->name('campaigns');
        Route::get('campaigns/{campaignId}/metrics', [PowerBiController::class, 'campaignMetrics'])->name('campaign.metrics');
        Route::get('campaigns/{campaignId}/members/{status}', [PowerBiController::class, 'campaignMembers'])->name('campaign.members');
        Route::get('embed-token/{reportId}', [PowerBiController::class, 'embedToken'])->name('embed.token');
    });

    // Dataverse API endpoints (Carib only for now)
    Route::prefix('api/dataverse')->name('dataverse.')->middleware('throttle:60,1')->group(function () {
        Route::get('campaigns/{campaignId}/email-engagement-logs', [EmailEngagementController::class, 'emailLogs'])
            ->name('campaign.email-logs');
    });
});

require __DIR__.'/settings.php';
