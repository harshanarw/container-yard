<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\YardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // User Management
    Route::resource('users', UserController::class);

    // Customer Management
    Route::resource('customers', CustomerController::class);

    // Container Inquiries
    Route::resource('inquiries', InquiryController::class);

    // Repair Estimates
    Route::resource('estimates', EstimateController::class);
    Route::post('estimates/{estimate}/send', [EstimateController::class, 'send'])->name('estimates.send');
    Route::patch('estimates/{estimate}/approve', [EstimateController::class, 'approve'])->name('estimates.approve');
    Route::patch('estimates/{estimate}/reject', [EstimateController::class, 'reject'])->name('estimates.reject');

    // Yard Operations
    Route::prefix('yard')->name('yard.')->group(function () {
        Route::get('/',         [YardController::class, 'index'])->name('index');
        Route::get('/gate',     [YardController::class, 'gate'])->name('gate');
        Route::post('/gate/in', [YardController::class, 'gateIn'])->name('gate.in');
        Route::post('/gate/out',[YardController::class, 'gateOut'])->name('gate.out');
        Route::get('/storage',  [YardController::class, 'storage'])->name('storage');
        Route::post('/storage/calculate', [YardController::class, 'calculate'])->name('storage.calculate');
        Route::get('/container/{containerNo}', [YardController::class, 'lookup'])->name('container.lookup');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/inventory', [ReportController::class, 'inventory'])->name('inventory');
        Route::get('/billing',   [ReportController::class, 'billing'])->name('billing');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/',  [SettingController::class, 'index'])->name('index');
        Route::post('/', [SettingController::class, 'update'])->name('update');
    });

});
