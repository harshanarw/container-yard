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
use App\Http\Controllers\DamageAssessmentRuleController;
use App\Http\Controllers\MrCodeController;
use App\Http\Controllers\MrCodeChargeMappingController;
use App\Http\Controllers\MrTariffController;
use App\Http\Controllers\WashingTariffController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalWorkflowController;
use App\Http\Controllers\YardController;
use App\Http\Controllers\YardJobController;
use App\Http\Controllers\ContainerOcrController;
use App\Http\Controllers\PlateOcrController;
use App\Http\Controllers\AccessController;
use App\Http\Controllers\ContainerGradeController;
use App\Http\Controllers\GuardPostController;
use App\Http\Controllers\ReeferTariffController;
use App\Http\Controllers\ReeferController;
use App\Http\Controllers\ReeferBillingController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ContainerHireController;
use App\Http\Controllers\ContainerInquiryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Finance\FinancialYearController;
use App\Http\Controllers\Finance\ChartOfAccountsController;
use App\Http\Controllers\Finance\AccountMappingController;
use App\Http\Controllers\Finance\GeneralLedgerController;
use App\Http\Controllers\Finance\InvoicePostingController;
use App\Http\Controllers\Finance\BankAccountController;
use App\Http\Controllers\Finance\ReceiptController;
use App\Http\Controllers\Finance\PaymentVoucherController;
use App\Http\Controllers\Finance\SupplierInvoiceController;
use App\Http\Controllers\InternalNotificationEmailController;
use App\Http\Controllers\CustomerEmailContactController;
use App\Http\Controllers\NumberSequenceController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
require __DIR__.'/portal.php';

/*
|--------------------------------------------------------------------------
| Public Routes — no authentication required
|--------------------------------------------------------------------------
*/
Route::get('/gp/verify/{movement}', [YardController::class, 'verifyGatePass'])->name('gp.verify');

// Driver-facing gate pass shared over WhatsApp — temporary signed URL so the
// link is tamper-proof, unguessable and self-expiring.
Route::get('/gp/pass/{movement}', [YardController::class, 'driverGatePass'])
    ->name('gp.pass')
    ->middleware('signed');

// Short branded variant of the above (/g/{code}) — resolves the movement by its
// unguessable share code, so the WhatsApp message shows a tidy link.
Route::get('/g/{code}', [YardController::class, 'shortGatePass'])->name('gp.short');

// Public document verification (QR target on invoice/estimate PDFs). Signed URL
// so the link is tamper-proof and cannot be forged or guessed.
Route::get('/verify/{type}/{id}', [\App\Http\Controllers\DocumentVerificationController::class, 'show'])
    ->name('documents.verify')
    ->middleware('signed');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/',          [NotificationController::class, 'index'])->name('index');
        Route::get('/unread',    [NotificationController::class, 'unread'])->name('unread');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('readAll');
        Route::post('/{id}/read',[NotificationController::class, 'markRead'])->name('markRead');
    });

    // User Management
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    // Customer Management
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::resource('customers', CustomerController::class);

    // Customer Email Contacts
    Route::post('customers/{customer}/email-contacts', [CustomerEmailContactController::class, 'store'])->name('customers.email-contacts.store');
    Route::put('customers/{customer}/email-contacts/sync', [CustomerEmailContactController::class, 'sync'])->name('customers.email-contacts.sync');
    Route::patch('customers/{customer}/email-contacts/{contact}', [CustomerEmailContactController::class, 'update'])->name('customers.email-contacts.update');
    Route::delete('customers/{customer}/email-contacts/{contact}', [CustomerEmailContactController::class, 'destroy'])->name('customers.email-contacts.destroy');

    // Container Master
    Route::get('containers/master-lookup', [ContainerController::class, 'masterLookup'])->name('containers.master-lookup');
    Route::get('containers/available-stock', [ContainerController::class, 'availableStock'])->name('containers.available-stock');
    Route::post('containers/{container}/mark-available', [ContainerController::class, 'markAvailable'])->name('containers.mark-available');
    Route::post('containers/{container}/hold', [ContainerController::class, 'placeHold'])->name('containers.hold');
    Route::post('containers/{container}/holds/{hold}/clear', [ContainerController::class, 'clearHold'])->name('containers.hold.clear');
    Route::post('containers/{container}/pti', [\App\Http\Controllers\ReeferPtiController::class, 'store'])->name('containers.pti');
    Route::resource('containers', ContainerController::class);

    // Container bookings (EDO) — reservation / export release
    Route::get('container-bookings', [\App\Http\Controllers\ContainerBookingController::class, 'index'])->name('container-bookings.index');
    Route::get('container-bookings/create', [\App\Http\Controllers\ContainerBookingController::class, 'create'])->name('container-bookings.create');
    Route::post('container-bookings', [\App\Http\Controllers\ContainerBookingController::class, 'store'])->name('container-bookings.store');
    Route::get('container-bookings/{containerBooking}', [\App\Http\Controllers\ContainerBookingController::class, 'show'])->name('container-bookings.show');
    Route::post('container-bookings/{containerBooking}/allocate', [\App\Http\Controllers\ContainerBookingController::class, 'allocate'])->name('container-bookings.allocate');
    Route::post('container-bookings/{containerBooking}/lines/{line}/auto-allocate', [\App\Http\Controllers\ContainerBookingController::class, 'autoAllocate'])->name('container-bookings.auto-allocate');
    Route::post('container-bookings/{containerBooking}/containers/{container}/deallocate', [\App\Http\Controllers\ContainerBookingController::class, 'deallocate'])->name('container-bookings.deallocate');
    Route::post('container-bookings/{containerBooking}/cancel', [\App\Http\Controllers\ContainerBookingController::class, 'cancel'])->name('container-bookings.cancel');
    Route::delete('container-bookings/{containerBooking}', [\App\Http\Controllers\ContainerBookingController::class, 'destroy'])->name('container-bookings.destroy');

    // Container Surveys (formerly Inquiries)
    Route::resource('surveys', SurveyController::class);
    Route::delete('surveys/{survey}/photos/{photo}', [SurveyController::class, 'destroyPhoto'])->name('surveys.photos.destroy');
    Route::get('surveys/{survey}/pdf',             [SurveyController::class, 'pdf'])->name('surveys.pdf');

    // Container Inquiries (legacy — kept for backward compatibility with estimates)
    Route::resource('inquiries', InquiryController::class);
    Route::delete('inquiries/{inquiry}/photos/{photo}', [InquiryController::class, 'destroyPhoto'])->name('inquiries.photos.destroy');

    // Repair Estimates
    Route::get('estimates/resolve-charge-code', [EstimateController::class, 'resolveChargeCode'])->name('estimates.resolve-charge-code');
    Route::get('estimates/exchange-rate',        [EstimateController::class, 'exchangeRateLookup'])->name('estimates.exchange-rate');
    Route::get('estimates/washing-lookup',       [EstimateController::class, 'washingLookup'])->name('estimates.washing-lookup');
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
    // NOTE: the route parameter must be named {invoice} to match the controller
    // method signatures (RepairInvoice $invoice); Laravel resolves implicit
    // model binding by parameter name, so a mismatch yields an empty model.
    Route::resource('repair-invoices', RepairInvoiceController::class)
        ->parameters(['repair-invoices' => 'invoice']);
    Route::patch('repair-invoices/{invoice}/issue',          [RepairInvoiceController::class, 'issue'])->name('repair-invoices.issue');
    Route::patch('repair-invoices/{invoice}/record-payment', [RepairInvoiceController::class, 'recordPayment'])->name('repair-invoices.record-payment');
    Route::patch('repair-invoices/{invoice}/cancel',         [RepairInvoiceController::class, 'cancel'])->name('repair-invoices.cancel');
    Route::get('repair-invoices/{invoice}/ird-print',        [RepairInvoiceController::class, 'irdPrint'])->name('repair-invoices.ird-print');

    // General Invoicing (misc AR — tax invoice / invoice / debit note)
    Route::prefix('billing/general')->name('billing.general.')->group(function () {
        Route::get('/',                [\App\Http\Controllers\GeneralInvoiceController::class, 'index'])->name('index');
        Route::get('create',           [\App\Http\Controllers\GeneralInvoiceController::class, 'create'])->name('create');
        Route::post('/',               [\App\Http\Controllers\GeneralInvoiceController::class, 'store'])->name('store');
        // AJAX endpoints — must precede the {general} wildcard
        Route::get('charge-code-info', [\App\Http\Controllers\GeneralInvoiceController::class, 'chargeCodeInfo'])->name('charge-code-info');
        Route::get('currency-rate',    [\App\Http\Controllers\GeneralInvoiceController::class, 'currencyRate'])->name('currency-rate');
        Route::get('{general}',        [\App\Http\Controllers\GeneralInvoiceController::class, 'show'])->name('show');
        Route::get('{general}/pdf',       [\App\Http\Controllers\GeneralInvoiceController::class, 'pdf'])->name('pdf');
        Route::get('{general}/ird-print', [\App\Http\Controllers\GeneralInvoiceController::class, 'irdPrint'])->name('ird-print');
        Route::get('{general}/edit',   [\App\Http\Controllers\GeneralInvoiceController::class, 'edit'])->name('edit');
        Route::patch('{general}',      [\App\Http\Controllers\GeneralInvoiceController::class, 'update'])->name('update');
        Route::patch('{general}/issue',[\App\Http\Controllers\GeneralInvoiceController::class, 'issue'])->name('issue');
        Route::patch('{general}/void', [\App\Http\Controllers\GeneralInvoiceController::class, 'void'])->name('void');
        Route::delete('{general}',     [\App\Http\Controllers\GeneralInvoiceController::class, 'destroy'])->name('destroy');
    });

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
        Route::get('/guard-post-check',    [YardController::class, 'guardPostCheck'])->name('guard-post-check');
        Route::get('/in-yard-search',      [YardController::class, 'inYardSearch'])->name('in-yard-search');
        Route::get('/driver-search',       [YardController::class, 'driverSearch'])->name('driver-search');
        Route::get('/zones/{zone}/slots',  [YardController::class, 'slotsByZone'])->name('zones.slots');
        Route::get('/survey/{survey}', [YardController::class, 'surveyLookup'])->name('survey.lookup');
        Route::post('/ocr-scan',       [ContainerOcrController::class, 'process'])->name('ocr-scan');
        Route::post('/ocr-plate',      [PlateOcrController::class,   'process'])->name('ocr-plate');
        Route::get('/movements/{movement}/edit',            [YardController::class, 'editMovement'])->name('movements.edit');
        Route::get('/movements/{movement}/delete-check',  [YardController::class, 'deleteCheck'])->name('movements.delete-check');
        Route::patch('/movements/{movement}',             [YardController::class, 'updateMovement'])->name('movements.update');
        Route::delete('/movements/{movement}',            [YardController::class, 'destroyMovement'])->name('movements.destroy');
        Route::delete('/movements/{movement}/photos/{photo}', [YardController::class, 'destroyMovementPhoto'])->name('movements.photo.destroy');
        Route::get('/movements/{movement}/gate-pass',     [YardController::class, 'gatePass'])->name('movements.gate-pass');
        Route::get('/movements/{movement}/wa-gatepass',   [YardController::class, 'whatsappGatePass'])->name('movements.wa-gatepass');
    });

    // Container Hires (On Hire / Off Hire)
    Route::prefix('yard/hires')->name('yard.hires.')->group(function () {
        Route::get('/',                              [ContainerHireController::class, 'index'])->name('index');
        Route::get('/create',                        [ContainerHireController::class, 'create'])->name('create');
        Route::post('/',                             [ContainerHireController::class, 'store'])->name('store');
        Route::get('/{hire}',                        [ContainerHireController::class, 'show'])->name('show');
        Route::get('/{hire}/off-hire',               [ContainerHireController::class, 'offHireForm'])->name('off-hire');
        Route::post('/{hire}/off-hire',              [ContainerHireController::class, 'processOffHire'])->name('off-hire.process');
        Route::post('/{hire}/cancel',                [ContainerHireController::class, 'cancel'])->name('cancel');
    });

    // Cargo Rental / Container Substitution ("cross-stuffing")
    Route::prefix('yard/cargo-transfers')->name('yard.cargo-transfers.')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\CargoTransferController::class, 'index'])->name('index');
        Route::get('/create/{movement}',   [\App\Http\Controllers\CargoTransferController::class, 'create'])->name('create');
        Route::post('/',                   [\App\Http\Controllers\CargoTransferController::class, 'store'])->name('store');
        Route::get('/{cargoTransfer}',     [\App\Http\Controllers\CargoTransferController::class, 'show'])->name('show');
        Route::post('/{cargoTransfer}/complete', [\App\Http\Controllers\CargoTransferController::class, 'complete'])->name('complete');
    });

    // Lessor On-Hire (yard as lessee) — on-hire from a shipping line as a costed job
    Route::prefix('yard/lessor-hires')->name('yard.lessor-hires.')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\LessorOnHireController::class, 'index'])->name('index');
        Route::get('/create',              [\App\Http\Controllers\LessorOnHireController::class, 'create'])->name('create');
        Route::post('/',                   [\App\Http\Controllers\LessorOnHireController::class, 'store'])->name('store');
        Route::get('/{lessorHire}',        [\App\Http\Controllers\LessorOnHireController::class, 'show'])->name('show');
        Route::post('/{lessorHire}/off-hire', [\App\Http\Controllers\LessorOnHireController::class, 'offHire'])->name('off-hire');
    });

    // Yard Jobs
    Route::prefix('yard/jobs')->name('yard.jobs.')->group(function () {
        Route::get('/',            [YardJobController::class, 'index'])->name('index');
        Route::get('/{yardJob}',   [YardJobController::class, 'show'])->name('show');
        Route::patch('/{yardJob}', [YardJobController::class, 'update'])->name('update');
    });

    // Reefer Operations — plug-in / plug-out / temp logs
    Route::prefix('yard/reefer')->name('yard.reefer.')->group(function () {
        Route::get('/',                                          [ReeferController::class, 'index'])->name('index');
        Route::get('/{plugSession}/plug-in',                    [ReeferController::class, 'plugIn'])->name('plug-in');
        Route::post('/{plugSession}/plug-in',                   [ReeferController::class, 'storePlugIn'])->name('store-plug-in');
        Route::get('/{plugSession}/plug-out',                   [ReeferController::class, 'plugOut'])->name('plug-out');
        Route::post('/{plugSession}/plug-out',                  [ReeferController::class, 'storePlugOut'])->name('store-plug-out');
        Route::get('/{plugSession}',                            [ReeferController::class, 'show'])->name('show');
        Route::post('/{plugSession}/temp-logs',                 [ReeferController::class, 'storeTempLog'])->name('temp-log.store');
        Route::delete('/{plugSession}/temp-logs/{tempLog}',     [ReeferController::class, 'destroyTempLog'])->name('temp-log.destroy');
    });

    // Guard Post Capture (optional feature — controller checks enable_guard_post flag)
    Route::prefix('guard-post')->name('guard-post.')->group(function () {
        Route::get('/',                              [GuardPostController::class, 'index'])->name('index');
        Route::get('/capture/new',                   [GuardPostController::class, 'create'])->name('create');
        Route::post('/capture',                      [GuardPostController::class, 'store'])->name('store');
        Route::get('/capture/{capture}/status',      [GuardPostController::class, 'status'])->name('status');
        Route::get('/capture/{capture}/status.json', [GuardPostController::class, 'statusJson'])->name('status-json');
        Route::post('/ocr-scan',                     [GuardPostController::class, 'ocrScan'])->name('ocr-scan');
        Route::get('/queue',                         [GuardPostController::class, 'queue'])->name('queue');
        Route::patch('/capture/{capture}/status',    [GuardPostController::class, 'updateStatus'])->name('update-status');
        Route::patch('/capture/{capture}/link',      [GuardPostController::class, 'link'])->name('link');
    });

    // Approvals
    Route::prefix('approvals')->name('approvals.')->group(function () {
        Route::get('/pending',                                       [ApprovalController::class, 'pending'])->name('pending');
        Route::post('/gate-pass/{movement}/initiate',               [ApprovalController::class, 'initiateGatePass'])->name('gate-pass.initiate');
        Route::post('/actions/{action}/approve',                    [ApprovalController::class, 'approve'])->name('actions.approve');
        Route::post('/actions/{action}/reject',                     [ApprovalController::class, 'reject'])->name('actions.reject');
        Route::post('/requests/{approvalRequest}/cancel',           [ApprovalController::class, 'cancel'])->name('requests.cancel');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/inventory',                        [ReportController::class, 'inventory'])->name('inventory');
        Route::get('/billing',                          [ReportController::class, 'billing'])->name('billing');
        Route::get('/daily-movements',                  [ReportController::class, 'dailyMovements'])->name('daily-movements');
        Route::post('/daily-movements/export/csv',      [ReportController::class, 'exportMovementsCsv'])->name('daily-movements.export.csv');
        Route::post('/daily-movements/export/codeco',   [ReportController::class, 'exportMovementsCodeco'])->name('daily-movements.export.codeco');
    });

    // File Storage report (admin) — usage, breakdown, filtered files, signed previews.
    // NOTE: prefix is "storage-report", not "storage": the latter collides with the
    // public/storage symlink (artisan storage:link), which the PHP dev server serves
    // directly, bypassing the router and 404-ing before Laravel is reached.
    Route::prefix('storage-report')->name('storage.')->group(function () {
        Route::get('/',                [\App\Http\Controllers\StorageReportController::class, 'index'])->name('report');
        Route::get('/preview/{asset}', [\App\Http\Controllers\StorageReportController::class, 'preview'])
            ->name('preview')->middleware('signed');
    });

    // Container Inquiry
    Route::prefix('container-inquiry')->name('container-inquiry.')->group(function () {
        Route::get('/',                             [ContainerInquiryController::class, 'index'])->name('index');
        Route::get('/export',                       [ContainerInquiryController::class, 'export'])->name('export');
        Route::get('/autocomplete',                 [ContainerInquiryController::class, 'autocomplete'])->name('autocomplete');
        Route::get('/{containerNo}/print',          [ContainerInquiryController::class, 'print'])->name('print');
        Route::get('/{containerNo}',                [ContainerInquiryController::class, 'show'])->name('show');
    });

    // Masters
    Route::prefix('masters')->name('masters.')->group(function () {
        // Driver master (auto-built from movements; admin list / edit / merge / history)
        Route::prefix('drivers')->name('drivers.')->group(function () {
            Route::get('/',           [\App\Http\Controllers\DriverController::class, 'index'])->name('index');
            Route::post('merge',      [\App\Http\Controllers\DriverController::class, 'merge'])->name('merge');
            Route::get('{driver}',    [\App\Http\Controllers\DriverController::class, 'show'])->name('show');
            Route::patch('{driver}',  [\App\Http\Controllers\DriverController::class, 'update'])->name('update');
            Route::delete('{driver}', [\App\Http\Controllers\DriverController::class, 'destroy'])->name('destroy');
        });
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
        Route::prefix('container-grades')->name('container-grades.')->group(function () {
            Route::get('/',                             [ContainerGradeController::class, 'index'])->name('index');
            Route::post('/',                            [ContainerGradeController::class, 'store'])->name('store');
            Route::patch('{containerGrade}',            [ContainerGradeController::class, 'update'])->name('update');
            Route::patch('{containerGrade}/toggle',     [ContainerGradeController::class, 'toggleActive'])->name('toggle');
            Route::delete('{containerGrade}',           [ContainerGradeController::class, 'destroy'])->name('destroy');
            Route::post('reorder',                      [ContainerGradeController::class, 'reorder'])->name('reorder');
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
        // Gate-In Job Types
        Route::prefix('job-types')->name('job-types.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\YardJobTypeController::class, 'index'])->name('index');
            Route::post('/',                   [\App\Http\Controllers\YardJobTypeController::class, 'store'])->name('store');
            Route::post('reorder',             [\App\Http\Controllers\YardJobTypeController::class, 'reorder'])->name('reorder');
            Route::patch('{jobType}',          [\App\Http\Controllers\YardJobTypeController::class, 'update'])->name('update');
            Route::patch('{jobType}/toggle',   [\App\Http\Controllers\YardJobTypeController::class, 'toggleActive'])->name('toggle');
            Route::delete('{jobType}',         [\App\Http\Controllers\YardJobTypeController::class, 'destroy'])->name('destroy');
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
        // Damage Assessment Rules
        Route::prefix('damage-assessment-rules')->name('damage-assessment-rules.')->group(function () {
            Route::get('/search',                                    [DamageAssessmentRuleController::class, 'search'])->name('search');
            Route::get('/',                                          [DamageAssessmentRuleController::class, 'index'])->name('index');
            Route::get('/create',                                    [DamageAssessmentRuleController::class, 'create'])->name('create');
            Route::post('/',                                         [DamageAssessmentRuleController::class, 'store'])->name('store');
            Route::get('/{damageAssessmentRule}/edit',               [DamageAssessmentRuleController::class, 'edit'])->name('edit');
            Route::put('/{damageAssessmentRule}',                    [DamageAssessmentRuleController::class, 'update'])->name('update');
            Route::delete('/{damageAssessmentRule}',                 [DamageAssessmentRuleController::class, 'destroy'])->name('destroy');
            Route::patch('/{damageAssessmentRule}/toggle',           [DamageAssessmentRuleController::class, 'toggleActive'])->name('toggle');
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
        // Banks
        Route::prefix('banks')->name('banks.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\BankController::class, 'index'])->name('index');
            Route::get('export',           [\App\Http\Controllers\BankController::class, 'export'])->name('export');
            Route::post('import',          [\App\Http\Controllers\BankController::class, 'import'])->name('import');
            Route::post('/',               [\App\Http\Controllers\BankController::class, 'store'])->name('store');
            Route::post('reorder',         [\App\Http\Controllers\BankController::class, 'reorder'])->name('reorder');
            Route::patch('{bank}/toggle',  [\App\Http\Controllers\BankController::class, 'toggleActive'])->name('toggle');
            Route::patch('{bank}',         [\App\Http\Controllers\BankController::class, 'update'])->name('update');
            Route::delete('{bank}',        [\App\Http\Controllers\BankController::class, 'destroy'])->name('destroy');
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
            // AJAX endpoints MUST come before the {mrTariff} wildcard to avoid route shadowing
            Route::get('item-search',    [MrTariffController::class, 'itemSearch'])->name('item-search');
            Route::get('rate-lookup',    [MrTariffController::class, 'rateLookup'])->name('rate-lookup');
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
            // Slab items + slab tiers nested under header
            Route::prefix('{mrTariff}/items')->name('items.')->group(function () {
                Route::post('/',                          [MrTariffController::class, 'storeItem'])->name('store');
                Route::patch('{item}',                    [MrTariffController::class, 'updateItem'])->name('update');
                Route::delete('{item}',                   [MrTariffController::class, 'destroyItem'])->name('destroy');
                Route::prefix('{item}/slabs')->name('slabs.')->group(function () {
                    Route::post('/',        [MrTariffController::class, 'storeSlab'])->name('store');
                    Route::patch('{slab}',  [MrTariffController::class, 'updateSlab'])->name('update');
                    Route::delete('{slab}', [MrTariffController::class, 'destroySlab'])->name('destroy');
                });
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
        // Washing / Cleaning Tariff (flat per-container rates by scope × type × size)
        Route::prefix('washing-tariff')->name('washing-tariff.')->group(function () {
            Route::get('/',                        [WashingTariffController::class, 'index'])->name('index');
            Route::get('create',                   [WashingTariffController::class, 'create'])->name('create');
            Route::post('/',                       [WashingTariffController::class, 'store'])->name('store');
            Route::get('{washingTariff}/edit',     [WashingTariffController::class, 'edit'])->name('edit');
            Route::patch('{washingTariff}',        [WashingTariffController::class, 'update'])->name('update');
            Route::patch('{washingTariff}/toggle', [WashingTariffController::class, 'toggleActive'])->name('toggle');
            Route::delete('{washingTariff}',       [WashingTariffController::class, 'destroy'])->name('destroy');
        });
        // Reefer Electricity Tariff
        Route::prefix('reefer-tariff')->name('reefer-tariff.')->group(function () {
            Route::get('/',                              [ReeferTariffController::class, 'index'])->name('index');
            Route::post('/',                             [ReeferTariffController::class, 'store'])->name('store');
            Route::get('{reeferTariff}',                 [ReeferTariffController::class, 'show'])->name('show');
            Route::patch('{reeferTariff}',               [ReeferTariffController::class, 'update'])->name('update');
            Route::patch('{reeferTariff}/toggle',        [ReeferTariffController::class, 'toggleActive'])->name('toggle');
            Route::delete('{reeferTariff}',              [ReeferTariffController::class, 'destroy'])->name('destroy');
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

    // Retry a failed / missing GL posting for any issued invoice (type + id).
    // Cross-cutting across all invoice modules, so it lives outside the storage
    // billing group to keep a single, module-agnostic endpoint + name.
    Route::patch('billing/postings/{type}/{id}/retry', [\App\Http\Controllers\InvoicePostingController::class, 'retry'])
        ->name('billing.postings.retry');

    // Billing — Storage Invoice generation and management
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/',                [StorageBillingController::class, 'index'])->name('index');
        Route::get('/create',          [StorageBillingController::class, 'create'])->name('create');
        Route::post('/preview',        [StorageBillingController::class, 'preview'])->name('preview');
        Route::post('/',               [StorageBillingController::class, 'store'])->name('store');

        // Reefer Electricity Billing — must come BEFORE the /{invoice} wildcard
        Route::prefix('reefer')->name('reefer.')->group(function () {
            Route::get('/',                              [ReeferBillingController::class, 'index'])->name('index');
            Route::get('/create',                        [ReeferBillingController::class, 'create'])->name('create');
            Route::post('/preview',                      [ReeferBillingController::class, 'preview'])->name('preview');
            Route::post('/',                             [ReeferBillingController::class, 'store'])->name('store');
            Route::get('/{reeferInvoice}',               [ReeferBillingController::class, 'show'])->name('show');
            Route::delete('/{reeferInvoice}',            [ReeferBillingController::class, 'destroy'])->name('destroy');
            Route::patch('/{reeferInvoice}/issue',       [ReeferBillingController::class, 'markIssued'])->name('issue');
            Route::patch('/{reeferInvoice}/pay',         [ReeferBillingController::class, 'markPaid'])->name('pay');
            Route::patch('/{reeferInvoice}/cancel',      [ReeferBillingController::class, 'cancel'])->name('cancel');
            Route::get('/{reeferInvoice}/pdf',           [ReeferBillingController::class, 'pdf'])->name('pdf');
            Route::get('/{reeferInvoice}/ird-print',     [ReeferBillingController::class, 'irdPrint'])->name('ird-print');
        });

        // Storage & Handling — must come BEFORE the /{invoice} wildcard
        Route::prefix('storage-handling')->name('storage-handling.')->group(function () {
            Route::get('/',                [StorageHandlingController::class, 'index'])->name('index');
            Route::get('/create',          [StorageHandlingController::class, 'create'])->name('create');
            Route::post('/preview',        [StorageHandlingController::class, 'preview'])->name('preview');
            Route::post('/',               [StorageHandlingController::class, 'store'])->name('store');
            Route::get('/{storageHandlingInvoice}',                  [StorageHandlingController::class, 'show'])->name('show');
            Route::delete('/{storageHandlingInvoice}',               [StorageHandlingController::class, 'destroy'])->name('destroy');
            Route::patch('/{storageHandlingInvoice}/issue',          [StorageHandlingController::class, 'markIssued'])->name('issue');
            Route::patch('/{storageHandlingInvoice}/pay',            [StorageHandlingController::class, 'markPaid'])->name('pay');
            Route::patch('/{storageHandlingInvoice}/cancel',         [StorageHandlingController::class, 'cancel'])->name('cancel');
            Route::get('/{storageHandlingInvoice}/pdf',              [StorageHandlingController::class, 'pdf'])->name('pdf');
            Route::get('/{storageHandlingInvoice}/ird-print',        [StorageHandlingController::class, 'irdPrint'])->name('ird-print');
        });

        // Periodic (consolidated) Repair Billing — must come BEFORE the /{invoice} wildcard
        Route::prefix('repair')->name('repair.')->group(function () {
            Route::post('/preview', [\App\Http\Controllers\Billing\RepairBillingController::class, 'preview'])->name('preview');
            Route::post('/',        [\App\Http\Controllers\Billing\RepairBillingController::class, 'store'])->name('store');
        });

        // Wildcard /{invoice} routes — must come AFTER all named sub-paths
        Route::get('/{invoice}',              [StorageBillingController::class, 'show'])->name('show');
        Route::delete('/{invoice}',           [StorageBillingController::class, 'destroy'])->name('destroy');
        Route::patch('/{invoice}/issue',      [StorageBillingController::class, 'markIssued'])->name('issue');
        Route::patch('/{invoice}/pay',        [StorageBillingController::class, 'markPaid'])->name('pay');
        Route::patch('/{invoice}/cancel',     [StorageBillingController::class, 'cancel'])->name('cancel');
        Route::get('/{invoice}/pdf',          [StorageBillingController::class, 'pdf'])->name('pdf');
        Route::get('/{invoice}/ird-print',    [StorageBillingController::class, 'irdPrint'])->name('ird-print');
        Route::post('/{invoice}/email',       [StorageBillingController::class, 'sendEmail'])->name('email');
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

    // Access Control — roles and user permission management
    Route::prefix('access-control')->name('access-control.')->group(function () {
        // Roles
        Route::get('/roles',           [AccessController::class, 'roles'])->name('roles.index');
        Route::get('/roles/create',    [AccessController::class, 'createRole'])->name('roles.create');
        Route::post('/roles',          [AccessController::class, 'storeRole'])->name('roles.store');
        Route::get('/roles/{role}',    [AccessController::class, 'editRole'])->name('roles.edit');
        Route::patch('/roles/{role}',  [AccessController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{role}', [AccessController::class, 'destroyRole'])->name('roles.destroy');

        // Users
        Route::get('/users',                          [AccessController::class, 'users'])->name('users.index');
        Route::get('/users/{user}',                   [AccessController::class, 'userAccess'])->name('users.show');
        Route::patch('/users/{user}/roles',           [AccessController::class, 'updateUserRoles'])->name('users.update-roles');
        Route::patch('/users/{user}/permissions',     [AccessController::class, 'updateUserPermissions'])->name('users.update-permissions');
    });

    // Approval Workflows — System Administrator only
    // Audit Log
    Route::prefix('audit-log')->name('audit-log.')->group(function () {
        Route::get('/',           [AuditLogController::class, 'index'])->name('index');
        Route::get('/{auditLog}', [AuditLogController::class, 'detail'])->name('detail');
    });

    Route::prefix('settings/approval-workflows')->name('settings.approval-workflows.')->group(function () {
        Route::get('/',                              [ApprovalWorkflowController::class, 'index'])->name('index');
        Route::post('/',                             [ApprovalWorkflowController::class, 'store'])->name('store');
        Route::patch('{approvalWorkflow}',           [ApprovalWorkflowController::class, 'update'])->name('update');
        Route::patch('{approvalWorkflow}/toggle',    [ApprovalWorkflowController::class, 'toggleActive'])->name('toggle');
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

    // ── Finance ──────────────────────────────────────────────────────────────
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::prefix('setup')->name('setup.')->group(function () {
            // Fiscal Years & Periods
            Route::get('fiscal-years',                                              [FinancialYearController::class, 'index'])->name('fiscal-years.index');
            Route::get('fiscal-years/create',                                       [FinancialYearController::class, 'create'])->name('fiscal-years.create');
            Route::post('fiscal-years',                                             [FinancialYearController::class, 'store'])->name('fiscal-years.store');
            Route::get('fiscal-years/{fiscalYear}',                                 [FinancialYearController::class, 'show'])->name('fiscal-years.show');
            Route::patch('fiscal-years/{fiscalYear}',                               [FinancialYearController::class, 'update'])->name('fiscal-years.update');
            Route::post('fiscal-years/{fiscalYear}/periods/{period}/close',         [FinancialYearController::class, 'closePeriod'])->name('fiscal-years.period.close');
            Route::post('fiscal-years/{fiscalYear}/periods/{period}/reopen',        [FinancialYearController::class, 'reopenPeriod'])->name('fiscal-years.period.reopen');
            Route::post('fiscal-years/{fiscalYear}/periods/{period}/close-pl',      [FinancialYearController::class, 'closePeriodPL'])->name('fiscal-years.period.close-pl');
            Route::post('fiscal-years/{fiscalYear}/periods/{period}/reverse-pl',    [FinancialYearController::class, 'reversePeriodPL'])->name('fiscal-years.period.reverse-pl');

            // Chart of Accounts
            Route::get('accounts',                [ChartOfAccountsController::class, 'index'])->name('accounts.index');
            Route::post('accounts',               [ChartOfAccountsController::class, 'store'])->name('accounts.store');
            Route::patch('accounts/{account}',    [ChartOfAccountsController::class, 'update'])->name('accounts.update');
            Route::delete('accounts/{account}',   [ChartOfAccountsController::class, 'destroy'])->name('accounts.destroy');
            Route::patch('accounts/{account}/toggle', [ChartOfAccountsController::class, 'toggleActive'])->name('accounts.toggle');

            // Account Mappings
            Route::get('mappings',              [AccountMappingController::class, 'index'])->name('mappings.index');
            Route::post('mappings',             [AccountMappingController::class, 'store'])->name('mappings.store');
            Route::delete('mappings/{mapping}', [AccountMappingController::class, 'destroy'])->name('mappings.destroy');
        });

        // General Ledger
        Route::prefix('gl')->name('gl.')->group(function () {
            Route::get('journals',                    [GeneralLedgerController::class, 'journals'])->name('journals.index');
            Route::get('journals/create',             [GeneralLedgerController::class, 'createJournal'])->name('journals.create');
            Route::post('journals',                   [GeneralLedgerController::class, 'storeJournal'])->name('journals.store');
            Route::get('journals/{journal}',          [GeneralLedgerController::class, 'showJournal'])->name('journals.show');
            Route::post('journals/{journal}/post',    [GeneralLedgerController::class, 'postJournal'])->name('journals.post');
            Route::post('journals/{journal}/void',    [GeneralLedgerController::class, 'voidJournal'])->name('journals.void');
            Route::get('account-ledger',              [GeneralLedgerController::class, 'accountLedger'])->name('account-ledger');
            Route::get('trial-balance',               [GeneralLedgerController::class, 'trialBalance'])->name('trial-balance');
        });

        // Financial Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('income-statement', [GeneralLedgerController::class, 'incomeStatement'])->name('income-statement');
            Route::get('balance-sheet',    [GeneralLedgerController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('fx-gain-loss',      [GeneralLedgerController::class, 'fxGainLoss'])->name('fx-gain-loss');
            Route::get('fx-revaluation',    [GeneralLedgerController::class, 'fxRevaluation'])->name('fx-revaluation');
            Route::post('fx-revaluation',   [GeneralLedgerController::class, 'postFxRevaluation'])->name('fx-revaluation.post');
            Route::post('fx-revaluation/void', [GeneralLedgerController::class, 'voidFxRevaluation'])->name('fx-revaluation.void');
            Route::get('customer-statement', [\App\Http\Controllers\Finance\StatementController::class, 'customer'])->name('customer-statement');
            Route::get('supplier-statement', [\App\Http\Controllers\Finance\StatementController::class, 'supplier'])->name('supplier-statement');
            Route::get('vat-sscl-return',    [\App\Http\Controllers\Finance\TaxReturnController::class, 'vatSscl'])->name('vat-sscl-return');
            Route::get('wht-report',         [\App\Http\Controllers\Finance\TaxReturnController::class, 'wht'])->name('wht-report');
            Route::get('job-margin',         [\App\Http\Controllers\Finance\JobMarginReportController::class, 'index'])->name('job-margin');
        });

        // AR / Invoice Postings
        Route::prefix('ar')->name('ar.')->group(function () {
            Route::get('postings',                  [InvoicePostingController::class, 'index'])->name('postings.index');
            Route::post('postings',                 [InvoicePostingController::class, 'store'])->name('postings.store');
            Route::post('postings/{posting}/void',  [InvoicePostingController::class, 'void'])->name('postings.void');
            Route::get('aging',                     [GeneralLedgerController::class, 'arAging'])->name('aging');
        });

        // AP / Supplier Invoices — the supplier/contact master is the unified
        // Customer (Contact) model; AP simply tags & bills the same parties.
        Route::prefix('ap')->name('ap.')->group(function () {
            Route::get('invoices',                          [SupplierInvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/create',                   [SupplierInvoiceController::class, 'create'])->name('invoices.create');
            Route::post('invoices',                         [SupplierInvoiceController::class, 'store'])->name('invoices.store');
            Route::get('charge-code/{chargeCode}',          [SupplierInvoiceController::class, 'chargeCodeDetails'])->name('charge-code.details');
            Route::get('invoices/{supplierInvoice}',        [SupplierInvoiceController::class, 'show'])->name('invoices.show');
            Route::get('invoices/{supplierInvoice}/edit',  [SupplierInvoiceController::class, 'edit'])->name('invoices.edit');
            Route::put('invoices/{supplierInvoice}',       [SupplierInvoiceController::class, 'update'])->name('invoices.update');
            Route::delete('invoices/{supplierInvoice}',     [SupplierInvoiceController::class, 'destroy'])->name('invoices.destroy');
            Route::post('invoices/{supplierInvoice}/approve',    [SupplierInvoiceController::class, 'approve'])->name('invoices.approve');
            Route::post('invoices/{supplierInvoice}/retry-post', [SupplierInvoiceController::class, 'retryPost'])->name('invoices.retry-post');
            Route::post('invoices/{supplierInvoice}/cancel',     [SupplierInvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::get('aging',                             [GeneralLedgerController::class, 'apAging'])->name('aging');
        });

        // Bank Accounts
        Route::prefix('bank-accounts')->name('bank-accounts.')->group(function () {
            Route::get('/',               [BankAccountController::class, 'index'])->name('index');
            Route::get('/create',         [BankAccountController::class, 'create'])->name('create');
            Route::post('/',              [BankAccountController::class, 'store'])->name('store');
            Route::get('/{bankAccount}',  [BankAccountController::class, 'edit'])->name('edit');
            Route::patch('/{bankAccount}', [BankAccountController::class, 'update'])->name('update');
            Route::delete('/{bankAccount}', [BankAccountController::class, 'destroy'])->name('destroy');
        });

        // Bank Reconciliation
        Route::prefix('bank-reconciliation')->name('bank-reconciliation.')->group(function () {
            $c = \App\Http\Controllers\Finance\BankReconciliationController::class;
            Route::get('/',                                [$c, 'index'])->name('index');
            Route::get('/create',                          [$c, 'create'])->name('create');
            Route::post('/',                               [$c, 'store'])->name('store');
            Route::get('/{bankReconciliation}',            [$c, 'show'])->name('show');
            Route::post('/{bankReconciliation}/toggle-clear', [$c, 'toggleClear'])->name('toggle-clear');
            Route::post('/{bankReconciliation}/import',    [$c, 'importStatement'])->name('import');
            Route::post('/{bankReconciliation}/auto-match', [$c, 'autoMatch'])->name('auto-match');
            Route::post('/{bankReconciliation}/lines/{line}/match',   [$c, 'matchLine'])->name('lines.match');
            Route::post('/{bankReconciliation}/lines/{line}/unmatch', [$c, 'unmatchLine'])->name('lines.unmatch');
            Route::post('/{bankReconciliation}/lines/{line}/adjust',  [$c, 'bookAdjustment'])->name('lines.adjust');
            Route::delete('/{bankReconciliation}/lines/{line}',       [$c, 'deleteStatementLine'])->name('lines.destroy');
            Route::post('/{bankReconciliation}/complete', [$c, 'complete'])->name('complete');
            Route::post('/{bankReconciliation}/reopen',   [$c, 'reopen'])->name('reopen');
            Route::delete('/{bankReconciliation}',        [$c, 'destroy'])->name('destroy');
        });

        // Exchange-rate lookup for receipt/voucher entry (returns base-currency rate)
        Route::get('fx-rate', [\App\Http\Controllers\Finance\FxRateController::class, 'show'])->name('fx-rate');

        // Receipts
        Route::prefix('receipts')->name('receipts.')->group(function () {
            Route::get('/',                             [ReceiptController::class, 'index'])->name('index');
            Route::get('/create',                       [ReceiptController::class, 'create'])->name('create');
            Route::post('/',                            [ReceiptController::class, 'store'])->name('store');
            // Invoice-first cashier flow: pick customer → select open invoices → receipt
            Route::get('/receive',                      [ReceiptController::class, 'receive'])->name('receive');
            Route::post('/receive',                     [ReceiptController::class, 'storeReceivePayment'])->name('receive.store');
            Route::get('/{receipt}',                    [ReceiptController::class, 'show'])->name('show');
            Route::get('/{receipt}/pdf',                [ReceiptController::class, 'pdf'])->name('pdf');
            Route::post('/{receipt}/email',             [ReceiptController::class, 'email'])->name('email');
            Route::post('/{receipt}/confirm',           [ReceiptController::class, 'confirm'])->name('confirm');
            Route::post('/{receipt}/void',              [ReceiptController::class, 'void'])->name('void');
            Route::post('/{receipt}/allocations',                            [ReceiptController::class, 'storeAllocation'])->name('allocations.store');
            Route::delete('/{receipt}/allocations/{allocation}',             [ReceiptController::class, 'deleteAllocation'])->name('allocations.destroy');
        });

        // AR Credit Notes (issued to customers)
        Route::prefix('ar-credit-notes')->name('ar-credit-notes.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'index'])->name('index');
            Route::get('/create',            [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'create'])->name('create');
            Route::post('/',                 [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'store'])->name('store');
            Route::get('/{arCreditNote}',    [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'show'])->name('show');
            Route::get('/{arCreditNote}/pdf',      [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'pdf'])->name('pdf');
            Route::post('/{arCreditNote}/email',   [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'email'])->name('email');
            Route::post('/{arCreditNote}/approve', [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'approve'])->name('approve');
            Route::post('/{arCreditNote}/cancel',  [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'cancel'])->name('cancel');
            Route::post('/{arCreditNote}/applications',                  [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'storeApplication'])->name('applications.store');
            Route::delete('/{arCreditNote}/applications/{application}',  [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'deleteApplication'])->name('applications.destroy');
            Route::delete('/{arCreditNote}',                             [\App\Http\Controllers\Finance\ArCreditNoteController::class, 'destroy'])->name('destroy');
        });

        // AP Credit Notes (received from vendors)
        Route::prefix('ap-credit-notes')->name('ap-credit-notes.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'index'])->name('index');
            Route::get('/create',            [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'create'])->name('create');
            Route::post('/',                 [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'store'])->name('store');
            Route::get('/{apCreditNote}',    [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'show'])->name('show');
            Route::get('/{apCreditNote}/pdf',      [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'pdf'])->name('pdf');
            Route::post('/{apCreditNote}/email',   [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'email'])->name('email');
            Route::post('/{apCreditNote}/approve', [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'approve'])->name('approve');
            Route::post('/{apCreditNote}/cancel',  [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'cancel'])->name('cancel');
            Route::post('/{apCreditNote}/applications',                  [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'storeApplication'])->name('applications.store');
            Route::delete('/{apCreditNote}/applications/{application}',  [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'deleteApplication'])->name('applications.destroy');
            Route::delete('/{apCreditNote}',                             [\App\Http\Controllers\Finance\ApCreditNoteController::class, 'destroy'])->name('destroy');
        });

        // Payment Vouchers
        Route::prefix('vouchers')->name('vouchers.')->group(function () {
            Route::get('/',                             [PaymentVoucherController::class, 'index'])->name('index');
            Route::get('/create',                       [PaymentVoucherController::class, 'create'])->name('create');
            Route::post('/',                            [PaymentVoucherController::class, 'store'])->name('store');
            // Invoice-first cashier flow: pick supplier → select open bills → voucher
            Route::get('/pay',                          [PaymentVoucherController::class, 'payBills'])->name('pay');
            Route::post('/pay',                         [PaymentVoucherController::class, 'storePayBills'])->name('pay.store');
            Route::get('/{voucher}',                    [PaymentVoucherController::class, 'show'])->name('show');
            Route::get('/{voucher}/pdf',                [PaymentVoucherController::class, 'pdf'])->name('pdf');
            Route::post('/{voucher}/email',             [PaymentVoucherController::class, 'email'])->name('email');
            Route::post('/{voucher}/confirm',           [PaymentVoucherController::class, 'confirm'])->name('confirm');
            Route::post('/{voucher}/void',              [PaymentVoucherController::class, 'void'])->name('void');
            Route::post('/{voucher}/allocations',                    [PaymentVoucherController::class, 'storeAllocation'])->name('allocations.store');
            Route::delete('/{voucher}/allocations/{allocation}',     [PaymentVoucherController::class, 'deleteAllocation'])->name('allocations.destroy');
        });
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

        // Internal Notification Email Recipients
        Route::prefix('internal-emails')->name('internal-emails.')->group(function () {
            Route::post('/',              [InternalNotificationEmailController::class, 'store'])->name('store');
            Route::patch('/{internalEmail}', [InternalNotificationEmailController::class, 'update'])->name('update');
            Route::delete('/{internalEmail}', [InternalNotificationEmailController::class, 'destroy'])->name('destroy');
        });

        // Number Sequences
        Route::prefix('number-sequences')->name('number-sequences.')->group(function () {
            Route::get('/',                                   [NumberSequenceController::class, 'index'])->name('index');
            Route::put('/{numberSequence}',                   [NumberSequenceController::class, 'update'])->name('update');
            Route::get('/{numberSequence}/preview',           [NumberSequenceController::class, 'preview'])->name('preview');
            Route::post('/{numberSequence}/reset-counter',    [NumberSequenceController::class, 'resetCounter'])->name('reset');
        });
    });

});
