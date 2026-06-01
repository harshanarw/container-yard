<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistMasterItemController;
use App\Http\Controllers\CloudStorageSettingController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CountryStateController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\TaxCodeController;
use App\Http\Controllers\ChargeCodeController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EquipmentTypeController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\RepairInvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StorageBillingController;
use App\Http\Controllers\StorageTariffController;
use App\Http\Controllers\StorageHandlingController;
use App\Http\Controllers\HandlingTariffController;
use App\Http\Controllers\StorageZoneController;
use App\Http\Controllers\EmailConfigController;
use App\Http\Controllers\MrCodeController;
use App\Http\Controllers\MrCodeChargeMappingController;
use App\Http\Controllers\MrTariffController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\YardController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
require __DIR__.'/portal.php';

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
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::resource('customers', CustomerController::class);

    // Container Master
    Route::get('containers/master-lookup', [ContainerController::class, 'masterLookup'])->name('containers.master-lookup');
    Route::resource('containers', ContainerController::class);

    // Container Surveys (formerly Inquiries)
    Route::resource('surveys', SurveyController::class);
    Route::delete('surveys/{survey}/photos/{photo}', [SurveyController::class, 'destroyPhoto'])->name('surveys.photos.destroy');
    Route::get('surveys/{survey}/pdf',             [SurveyController::class, 'pdf'])->name('surveys.pdf');

    // Container Inquiries (legacy — kept for backward compatibility with estimates)
    Route::resource('inquiries', InquiryController::class);
    Route::delete('inquiries/{inquiry}/photos/{photo}', [InquiryController::class, 'destroyPhoto'])->name('inquiries.photos.destroy');

    // Repair Estimates
    Route::get('estimates/resolve-charge-code', [EstimateController::class, 'resolveChargeCode'])->name('estimates.resolve-charge-code');
    Route::resource('estimates', EstimateController::class);
    Route::get('estimates/import-damages/{inquiry}',   [EstimateController::class, 'importDamages'])->name('estimates.import-damages');
    Route::post('estimates/{estimate}/send',           [EstimateController::class, 'send'])->name('estimates.send');
    Route::post('estimates/{estimate}/send-reminder',  [EstimateController::class, 'sendReminder'])->name('estimates.send-reminder');
    Route::patch('estimates/{estimate}/revoke-token',  [EstimateController::class, 'revokeToken'])->name('estimates.revoke-token');
    Route::patch('estimates/{estimate}/approve',       [EstimateController::class, 'approve'])->name('estimates.approve');
    Route::patch('estimates/{estimate}/reject',        [EstimateController::class, 'reject'])->name('estimates.reject');
    Route::get('estimates/{estimate}/pdf',             [EstimateController::class, 'pdf'])->name('estimates.pdf');

    // Work Orders
    Route::resource('work-orders', WorkOrderController::class);
    Route::patch('work-orders/{workOrder}/status',                                  [WorkOrderController::class, 'updateStatus'])->name('work-orders.update-status');
    Route::post('work-orders/{workOrder}/qc',                                       [WorkOrderController::class, 'submitQc'])->name('work-orders.submit-qc');
    Route::get('work-orders/{estimate}/available-categories',                        [WorkOrderController::class, 'availableCategories'])->name('work-orders.available-categories');
    Route::get('work-orders/{estimate}/preview-lines/{repairCategory}',              [WorkOrderController::class, 'previewLines'])->name('work-orders.preview-lines');

    // Repair Invoices
    Route::resource('repair-invoices', RepairInvoiceController::class);
    Route::patch('repair-invoices/{repairInvoice}/issue',          [RepairInvoiceController::class, 'issue'])->name('repair-invoices.issue');
    Route::patch('repair-invoices/{repairInvoice}/record-payment', [RepairInvoiceController::class, 'recordPayment'])->name('repair-invoices.record-payment');
    Route::patch('repair-invoices/{repairInvoice}/cancel',         [RepairInvoiceController::class, 'cancel'])->name('repair-invoices.cancel');

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
        Route::get('/container-lookup',    [YardController::class, 'containerLookup'])->name('container-lookup');
        Route::get('/zones/{zone}/slots',  [YardController::class, 'slotsByZone'])->name('zones.slots');
        Route::get('/survey/{survey}', [YardController::class, 'surveyLookup'])->name('survey.lookup');
        Route::get('/movements/{movement}/edit',            [YardController::class, 'editMovement'])->name('movements.edit');
        Route::patch('/movements/{movement}',             [YardController::class, 'updateMovement'])->name('movements.update');
        Route::delete('/movements/{movement}/photos/{photo}', [YardController::class, 'destroyMovementPhoto'])->name('movements.photo.destroy');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/inventory',                        [ReportController::class, 'inventory'])->name('inventory');
        Route::get('/billing',                          [ReportController::class, 'billing'])->name('billing');
        Route::get('/daily-movements',                  [ReportController::class, 'dailyMovements'])->name('daily-movements');
        Route::post('/daily-movements/export/csv',      [ReportController::class, 'exportMovementsCsv'])->name('daily-movements.export.csv');
        Route::post('/daily-movements/export/codeco',   [ReportController::class, 'exportMovementsCodeco'])->name('daily-movements.export.codeco');
    });

    // Masters
    Route::prefix('masters')->name('masters.')->group(function () {
        Route::prefix('zones')->name('zones.')->group(function () {
            Route::get('/',                    [StorageZoneController::class, 'index'])->name('index');
            Route::post('/',                   [StorageZoneController::class, 'store'])->name('store');
            Route::patch('{zone}/toggle',      [StorageZoneController::class, 'toggleActive'])->name('toggle');
            Route::patch('{zone}',             [StorageZoneController::class, 'update'])->name('update');
            Route::delete('{zone}',            [StorageZoneController::class, 'destroy'])->name('destroy');
            // Slot configuration — sub-resource under each zone
            Route::get('{zone}/slots',             [StorageZoneController::class, 'slots'])->name('slots');
            Route::post('{zone}/slots/generate',   [StorageZoneController::class, 'generateSlots'])->name('slots.generate');
            Route::post('{zone}/slots/move',       [StorageZoneController::class, 'moveSlot'])->name('slots.move');
            Route::delete('{zone}/slots/clear',    [StorageZoneController::class, 'clearSlots'])->name('slots.clear');
            Route::delete('{zone}/slots/{slot}',   [StorageZoneController::class, 'destroySlot'])->name('slots.destroy');
        });
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
        // Customer Types
        Route::prefix('customer-types')->name('customer-types.')->group(function () {
            Route::get('/',                       [CustomerTypeController::class, 'index'])->name('index');
            Route::post('/',                      [CustomerTypeController::class, 'store'])->name('store');
            Route::post('reorder',                [CustomerTypeController::class, 'reorder'])->name('reorder');
            Route::patch('{customerType}',        [CustomerTypeController::class, 'update'])->name('update');
            Route::patch('{customerType}/toggle', [CustomerTypeController::class, 'toggleActive'])->name('toggle');
            Route::delete('{customerType}',       [CustomerTypeController::class, 'destroy'])->name('destroy');
        });
        // Repair Categories
        Route::prefix('repair-categories')->name('repair-categories.')->group(function () {
            Route::get('/',                              [\App\Http\Controllers\RepairCategoryController::class, 'index'])->name('index');
            Route::post('/',                             [\App\Http\Controllers\RepairCategoryController::class, 'store'])->name('store');
            Route::post('reorder',                       [\App\Http\Controllers\RepairCategoryController::class, 'reorder'])->name('reorder');
            Route::patch('{repairCategory}',             [\App\Http\Controllers\RepairCategoryController::class, 'update'])->name('update');
            Route::patch('{repairCategory}/toggle',      [\App\Http\Controllers\RepairCategoryController::class, 'toggleActive'])->name('toggle');
            Route::delete('{repairCategory}',            [\App\Http\Controllers\RepairCategoryController::class, 'destroy'])->name('destroy');
        });
        // Repair Category Mappings
        Route::prefix('repair-category-mappings')->name('repair-category-mappings.')->group(function () {
            Route::get('/',                                      [\App\Http\Controllers\RepairCategoryMappingController::class, 'index'])->name('index');
            Route::post('/',                                     [\App\Http\Controllers\RepairCategoryMappingController::class, 'store'])->name('store');
            Route::patch('{repairCategoryMapping}',              [\App\Http\Controllers\RepairCategoryMappingController::class, 'update'])->name('update');
            Route::patch('{repairCategoryMapping}/toggle',       [\App\Http\Controllers\RepairCategoryMappingController::class, 'toggleActive'])->name('toggle');
            Route::delete('{repairCategoryMapping}',             [\App\Http\Controllers\RepairCategoryMappingController::class, 'destroy'])->name('destroy');
        });
        // MR Code → Charge Code Mappings
        Route::prefix('mr-charge-mappings')->name('mr-charge-mappings.')->group(function () {
            Route::get('/',                                [MrCodeChargeMappingController::class, 'index'])->name('index');
            Route::post('/',                              [MrCodeChargeMappingController::class, 'store'])->name('store');
            Route::patch('{mrCodeChargeMapping}/toggle',  [MrCodeChargeMappingController::class, 'toggleActive'])->name('toggle');
            Route::patch('{mrCodeChargeMapping}',         [MrCodeChargeMappingController::class, 'update'])->name('update');
            Route::delete('{mrCodeChargeMapping}',        [MrCodeChargeMappingController::class, 'destroy'])->name('destroy');
        });
        // M&R Codes (location / component / damage / repair / material / responsibility)
        Route::prefix('mr-codes/{mrCodeType}')->name('mr-codes.')->group(function () {
            Route::get('/',                          [MrCodeController::class, 'index'])->name('index');
            Route::post('/',                         [MrCodeController::class, 'store'])->name('store');
            Route::post('reorder',                   [MrCodeController::class, 'reorder'])->name('reorder');
            Route::patch('{mrCode}',                 [MrCodeController::class, 'update'])->name('update');
            Route::patch('{mrCode}/toggle',          [MrCodeController::class, 'toggleActive'])->name('toggle');
            Route::delete('{mrCode}',                [MrCodeController::class, 'destroy'])->name('destroy');
        });
        // Charge Codes
        Route::prefix('charge-codes')->name('charge-codes.')->group(function () {
            Route::get('/',                       [ChargeCodeController::class, 'index'])->name('index');
            Route::post('/',                      [ChargeCodeController::class, 'store'])->name('store');
            Route::post('reorder',                [ChargeCodeController::class, 'reorder'])->name('reorder');
            Route::patch('{chargeCode}',          [ChargeCodeController::class, 'update'])->name('update');
            Route::patch('{chargeCode}/toggle',   [ChargeCodeController::class, 'toggleActive'])->name('toggle');
            Route::delete('{chargeCode}',         [ChargeCodeController::class, 'destroy'])->name('destroy');
        });
        // Tax Codes
        Route::prefix('tax-codes')->name('tax-codes.')->group(function () {
            Route::get('/',                    [TaxCodeController::class, 'index'])->name('index');
            Route::post('/',                   [TaxCodeController::class, 'store'])->name('store');
            Route::post('reorder',             [TaxCodeController::class, 'reorder'])->name('reorder');
            Route::post('labels',              [TaxCodeController::class, 'updateLabels'])->name('labels');
            Route::patch('{taxCode}',          [TaxCodeController::class, 'update'])->name('update');
            Route::patch('{taxCode}/toggle',   [TaxCodeController::class, 'toggleActive'])->name('toggle');
            Route::delete('{taxCode}',         [TaxCodeController::class, 'destroy'])->name('destroy');
        });
        // Currencies
        Route::prefix('currencies')->name('currencies.')->group(function () {
            Route::get('/',                          [CurrencyController::class, 'index'])->name('index');
            Route::post('/',                         [CurrencyController::class, 'store'])->name('store');
            Route::post('reorder',                   [CurrencyController::class, 'reorder'])->name('reorder');
            Route::patch('{currency}/set-default',   [CurrencyController::class, 'setDefault'])->name('set-default');
            Route::patch('{currency}/toggle',        [CurrencyController::class, 'toggleActive'])->name('toggle');
            Route::patch('{currency}',               [CurrencyController::class, 'update'])->name('update');
            Route::delete('{currency}',              [CurrencyController::class, 'destroy'])->name('destroy');
        });
        // Daily Exchange Rates
        Route::prefix('exchange-rates')->name('exchange-rates.')->group(function () {
            Route::get('/',                       [ExchangeRateController::class, 'index'])->name('index');
            Route::post('/',                      [ExchangeRateController::class, 'store'])->name('store');
            Route::patch('{exchangeRate}',        [ExchangeRateController::class, 'update'])->name('update');
            Route::delete('{exchangeRate}',       [ExchangeRateController::class, 'destroy'])->name('destroy');
        });
        // M&R Rate Tariff
        Route::prefix('mr-tariff')->name('mr-tariff.')->group(function () {
            Route::get('/',                          [MrTariffController::class, 'index'])->name('index');
            Route::post('/',                         [MrTariffController::class, 'store'])->name('store');
            Route::get('{mrTariff}',                 [MrTariffController::class, 'show'])->name('show');
            Route::patch('{mrTariff}',               [MrTariffController::class, 'update'])->name('update');
            Route::patch('{mrTariff}/toggle',        [MrTariffController::class, 'toggleActive'])->name('toggle');
            Route::delete('{mrTariff}',              [MrTariffController::class, 'destroy'])->name('destroy');
            // Rule lines nested under header
            Route::prefix('{mrTariff}/rules')->name('rules.')->group(function () {
                Route::post('/',         [MrTariffController::class, 'storeRule'])->name('store');
                Route::patch('{rule}',   [MrTariffController::class, 'updateRule'])->name('update');
                Route::delete('{rule}',  [MrTariffController::class, 'destroyRule'])->name('destroy');
            });
        });
        // Handling Charges Tariff
        Route::prefix('handling-tariff')->name('handling-tariff.')->group(function () {
            Route::get('/',                                [HandlingTariffController::class, 'index'])->name('index');
            Route::post('/',                               [HandlingTariffController::class, 'store'])->name('store');
            Route::get('{handlingTariff}',                 [HandlingTariffController::class, 'show'])->name('show');
            Route::patch('{handlingTariff}',               [HandlingTariffController::class, 'update'])->name('update');
            Route::patch('{handlingTariff}/toggle',        [HandlingTariffController::class, 'toggleActive'])->name('toggle');
            Route::delete('{handlingTariff}',              [HandlingTariffController::class, 'destroy'])->name('destroy');
            // Rate line routes — nested under header
            Route::prefix('{handlingTariff}/rates')->name('rates.')->group(function () {
                Route::post('/',        [HandlingTariffController::class, 'storeRate'])->name('store');
                Route::patch('{rate}',  [HandlingTariffController::class, 'updateRate'])->name('update');
                Route::delete('{rate}', [HandlingTariffController::class, 'destroyRate'])->name('destroy');
            });
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
        Route::get('/',                [StorageBillingController::class, 'index'])->name('index');
        Route::get('/create',          [StorageBillingController::class, 'create'])->name('create');
        Route::post('/preview',        [StorageBillingController::class, 'preview'])->name('preview');
        Route::get('/exchange-rate',   [StorageBillingController::class, 'exchangeRateLookup'])->name('exchange-rate');
        Route::post('/',               [StorageBillingController::class, 'store'])->name('store');

        // Storage & Handling — must come BEFORE the /{invoice} wildcard
        Route::prefix('storage-handling')->name('storage-handling.')->group(function () {
            Route::get('/',                [StorageHandlingController::class, 'index'])->name('index');
            Route::get('/create',          [StorageHandlingController::class, 'create'])->name('create');
            Route::post('/preview',        [StorageHandlingController::class, 'preview'])->name('preview');
            Route::post('/',               [StorageHandlingController::class, 'store'])->name('store');
            Route::get('/{storageHandlingInvoice}',         [StorageHandlingController::class, 'show'])->name('show');
            Route::delete('/{storageHandlingInvoice}',      [StorageHandlingController::class, 'destroy'])->name('destroy');
            Route::patch('/{storageHandlingInvoice}/issue', [StorageHandlingController::class, 'markIssued'])->name('issue');
            Route::patch('/{storageHandlingInvoice}/pay',   [StorageHandlingController::class, 'markPaid'])->name('pay');
            Route::patch('/{storageHandlingInvoice}/cancel',[StorageHandlingController::class, 'cancel'])->name('cancel');
            Route::get('/{storageHandlingInvoice}/pdf',     [StorageHandlingController::class, 'pdf'])->name('pdf');
        });

        // Wildcard /{invoice} routes — must come AFTER all named sub-paths
        Route::get('/{invoice}',         [StorageBillingController::class, 'show'])->name('show');
        Route::delete('/{invoice}',      [StorageBillingController::class, 'destroy'])->name('destroy');
        Route::patch('/{invoice}/issue', [StorageBillingController::class, 'markIssued'])->name('issue');
        Route::patch('/{invoice}/pay',   [StorageBillingController::class, 'markPaid'])->name('pay');
        Route::patch('/{invoice}/cancel',[StorageBillingController::class, 'cancel'])->name('cancel');
        Route::get('/{invoice}/pdf',     [StorageBillingController::class, 'pdf'])->name('pdf');
        Route::post('/{invoice}/email',  [StorageBillingController::class, 'sendEmail'])->name('email');
    });

    // Documents (polymorphic, provider-agnostic)
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::post('/',                        [DocumentController::class, 'store'])->name('store');
        Route::get('/{document}/preview',       [DocumentController::class, 'preview'])->name('preview');
        Route::get('/{document}/download',      [DocumentController::class, 'download'])->name('download');
        Route::delete('/{document}',            [DocumentController::class, 'destroy'])->name('destroy');
    });

    // AJAX — country subdivision lookups
    Route::get('/ajax/states',    [CountryStateController::class, 'byCountry'])->name('ajax.states');
    Route::get('/ajax/districts', [CountryStateController::class, 'byState'])->name('ajax.districts');

    // Country List — System Administrator only
    Route::prefix('settings/countries')->name('settings.countries.')->group(function () {
        Route::get('/',                   [CountryController::class, 'index'])->name('index');
        Route::post('/',                  [CountryController::class, 'store'])->name('store');
        Route::patch('/{country}',        [CountryController::class, 'update'])->name('update');
        Route::patch('/{country}/toggle', [CountryController::class, 'toggleActive'])->name('toggle');
        Route::delete('/{country}',       [CountryController::class, 'destroy'])->name('destroy');
    });

    // Company Settings — System Administrator only
    Route::prefix('settings/company')->name('settings.company.')->group(function () {
        Route::get('/',        [CompanySettingController::class, 'index'])->name('index');
        Route::post('/',       [CompanySettingController::class, 'update'])->name('update');
        Route::patch('/default-currency', [CompanySettingController::class, 'setDefaultCurrency'])->name('default-currency');
        Route::delete('/logo',         [CompanySettingController::class, 'deleteLogo'])->name('logo.delete');
        Route::delete('/icon',         [CompanySettingController::class, 'deleteIcon'])->name('icon.delete');
        Route::delete('/product-icon', [CompanySettingController::class, 'deleteProductIcon'])->name('product-icon.delete');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/',  [SettingController::class, 'index'])->name('index');
        Route::post('/', [SettingController::class, 'update'])->name('update');

        // Email Configuration
        Route::prefix('email-config')->name('email-config.')->group(function () {
            Route::get('/',                         [EmailConfigController::class, 'index'])->name('index');
            Route::post('/',                        [EmailConfigController::class, 'store'])->name('store');
            Route::patch('/{emailConfig}',          [EmailConfigController::class, 'update'])->name('update');
            Route::delete('/{emailConfig}',         [EmailConfigController::class, 'destroy'])->name('destroy');
            Route::post('/{emailConfig}/test',      [EmailConfigController::class, 'test'])->name('test');
        });

        // Cloud storage
        Route::prefix('cloud-storage')->name('cloud-storage.')->group(function () {
            Route::get('/',                    [CloudStorageSettingController::class, 'index'])->name('index');
            Route::post('/',                   [CloudStorageSettingController::class, 'save'])->name('save');
            Route::post('/test',               [CloudStorageSettingController::class, 'test'])->name('test');
            Route::get('/gdrive/auth',         [CloudStorageSettingController::class, 'gdriveAuth'])->name('gdrive.auth');
            Route::get('/gdrive/callback',     [CloudStorageSettingController::class, 'gdriveCallback'])->name('gdrive.callback');
            Route::get('/dropbox/auth',        [CloudStorageSettingController::class, 'dropboxAuth'])->name('dropbox.auth');
            Route::get('/dropbox/callback',    [CloudStorageSettingController::class, 'dropboxCallback'])->name('dropbox.callback');
        });
    });

});
