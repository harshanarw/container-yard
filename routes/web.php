<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistMasterItemController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentTypeController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StorageBillingController;
use App\Http\Controllers\StorageTariffController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\YardController;

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
    Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    // Customer Management
    Route::resource('customers', CustomerController::class);

    // Container Management
    Route::resource('containers', ContainerController::class);

    // Container Inquiries
    Route::resource('inquiries', InquiryController::class);
    Route::delete('inquiries/{inquiry}/photos/{photo}', [InquiryController::class, 'destroyPhoto'])->name('inquiries.photos.destroy');

    // Repair Estimates
    Route::resource('estimates', EstimateController::class);
    Route::post('estimates/{estimate}/send', [EstimateController::class, 'send'])->name('estimates.send');
    Route::patch('estimates/{estimate}/approve', [EstimateController::class, 'approve'])->name('estimates.approve');
    Route::patch('estimates/{estimate}/reject', [EstimateController::class, 'reject'])->name('estimates.reject');
    Route::get('estimates/{estimate}/pdf', [EstimateController::class, 'pdf'])->name('estimates.pdf');

    // Yard Operations
    Route::prefix('yard')->name('yard.')->group(function () {
        Route::get('/',         [YardController::class, 'index'])->name('index');
        Route::get('/gate',     [YardController::class, 'gate'])->name('gate');
        Route::post('/gate/in', [YardController::class, 'gateIn'])->name('gate.in');
        Route::post('/gate/out',[YardController::class, 'gateOut'])->name('gate.out');
        Route::get('/storage',  [YardController::class, 'storage'])->name('storage');
        Route::post('/storage/calculate', [YardController::class, 'calculate'])->name('storage.calculate');
        Route::get('/container/{containerNo}', [YardController::class, 'lookup'])->name('container.lookup');
        Route::get('/tariff/{customerId}', [YardController::class, 'tariffLookup'])->name('tariff.lookup');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/inventory', [ReportController::class, 'inventory'])->name('inventory');
        Route::get('/billing',   [ReportController::class, 'billing'])->name('billing');
    });

    // Masters
    Route::prefix('masters')->name('masters.')->group(function () {
        Route::prefix('checklist')->name('checklist.')->group(function () {
            Route::get('/',                              [ChecklistMasterItemController::class, 'index'])->name('index');
            Route::post('/',                             [ChecklistMasterItemController::class, 'store'])->name('store');
            Route::patch('{checklistMasterItem}',        [ChecklistMasterItemController::class, 'update'])->name('update');
            Route::patch('{checklistMasterItem}/toggle', [ChecklistMasterItemController::class, 'toggleActive'])->name('toggle');
            Route::delete('{checklistMasterItem}',       [ChecklistMasterItemController::class, 'destroy'])->name('destroy');
            Route::post('reorder',                       [ChecklistMasterItemController::class, 'reorder'])->name('reorder');
        });
        Route::prefix('equipment-types')->name('equipment-types.')->group(function () {
            Route::get('/',                           [EquipmentTypeController::class, 'index'])->name('index');
            Route::post('/',                          [EquipmentTypeController::class, 'store'])->name('store');
            Route::patch('{equipmentType}',           [EquipmentTypeController::class, 'update'])->name('update');
            Route::patch('{equipmentType}/toggle',    [EquipmentTypeController::class, 'toggleActive'])->name('toggle');
            Route::delete('{equipmentType}',          [EquipmentTypeController::class, 'destroy'])->name('destroy');
            Route::post('reorder',                    [EquipmentTypeController::class, 'reorder'])->name('reorder');
        });
        // Storage Rate Tariff
        Route::prefix('storage-tariff')->name('storage-tariff.')->group(function () {
            Route::get('/',                              [StorageTariffController::class, 'index'])->name('index');
            Route::post('/',                             [StorageTariffController::class, 'store'])->name('store');
            Route::get('{storageTariff}',                [StorageTariffController::class, 'show'])->name('show');
            Route::patch('{storageTariff}',              [StorageTariffController::class, 'update'])->name('update');
            Route::patch('{storageTariff}/toggle',       [StorageTariffController::class, 'toggleActive'])->name('toggle');
            Route::delete('{storageTariff}',             [StorageTariffController::class, 'destroy'])->name('destroy');
            // Detail (rate line) routes — nested under header
            Route::prefix('{storageTariff}/details')->name('details.')->group(function () {
                Route::post('/',          [StorageTariffController::class, 'storeDetail'])->name('store');
                Route::patch('{detail}',  [StorageTariffController::class, 'updateDetail'])->name('update');
                Route::delete('{detail}', [StorageTariffController::class, 'destroyDetail'])->name('destroy');
            });
        });
    });

    // Billing — Storage Invoice generation and management
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/',                                  [StorageBillingController::class, 'index'])->name('index');
        Route::get('/create',                            [StorageBillingController::class, 'create'])->name('create');
        Route::post('/preview',                          [StorageBillingController::class, 'preview'])->name('preview');
        Route::post('/',                                 [StorageBillingController::class, 'store'])->name('store');
        Route::get('/{invoice}',                         [StorageBillingController::class, 'show'])->name('show');
        Route::delete('/{invoice}',                      [StorageBillingController::class, 'destroy'])->name('destroy');
        Route::patch('/{invoice}/issue',                 [StorageBillingController::class, 'markIssued'])->name('issue');
        Route::patch('/{invoice}/pay',                   [StorageBillingController::class, 'markPaid'])->name('pay');
        Route::patch('/{invoice}/cancel',                [StorageBillingController::class, 'cancel'])->name('cancel');
        Route::get('/{invoice}/pdf',                     [StorageBillingController::class, 'pdf'])->name('pdf');
        Route::post('/{invoice}/email',                  [StorageBillingController::class, 'sendEmail'])->name('email');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/',  [SettingController::class, 'index'])->name('index');
        Route::post('/', [SettingController::class, 'update'])->name('update');
    });

});
