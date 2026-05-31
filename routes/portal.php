<?php

use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalEstimateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal Routes (token-based, no session auth required)
|--------------------------------------------------------------------------
*/

Route::prefix('portal')->name('portal.')->group(function () {

    // Login page (for portal users with customer account)
    Route::get('login', [PortalAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [PortalAuthController::class, 'login'])->name('login.submit');
    Route::post('logout', [PortalAuthController::class, 'logout'])->name('logout');

    // Token-based direct estimate access (no login needed)
    Route::get('estimate/{token}',                              [PortalEstimateController::class, 'show'])->name('estimate.show');
    Route::post('estimate/{token}/approve',                     [PortalEstimateController::class, 'bulkApprove'])->name('estimate.approve');
    Route::post('estimate/{token}/reject',                      [PortalEstimateController::class, 'bulkReject'])->name('estimate.reject');
    Route::post('estimate/{token}/lines/{lineItem}/action',     [PortalEstimateController::class, 'lineAction'])->name('estimate.line-action');
    Route::get('estimate/{token}/photos',                       [PortalEstimateController::class, 'photos'])->name('estimate.photos');
    Route::get('estimate/{token}/photos/{document}/view',       [PortalEstimateController::class, 'viewPhoto'])->name('estimate.photo.view');
});
