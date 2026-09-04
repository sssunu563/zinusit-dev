<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PeminjamanController;
use App\Http\Controllers\StbController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\HelpdeskController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthLogController;
use App\Http\Controllers\SnipeItController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChangePasswordController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::get('stb/share/{stb}', [StbController::class, 'sharedShow'])
    ->middleware('signed')
    ->name('stb.share');
Route::post('stb/share/{stb}/sign/{role}', [StbController::class, 'sharedSign'])
    ->middleware('signed')
    ->name('stb.share.sign');

// Inspection public share routes (no auth required, signed URL)
Route::get('inspection/share/{inspection}', [InspectionController::class, 'sharedShow'])
    ->middleware('signed')
    ->name('inspection.share');
Route::post('inspection/share/{inspection}/sign/{role}', [InspectionController::class, 'sharedSign'])
    ->middleware('signed')
    ->name('inspection.share.sign');
Route::get('peminjaman/share/{peminjaman}', [PeminjamanController::class, 'sharedShow'])
    ->middleware('signed')
    ->name('peminjaman.share');
Route::post('peminjaman/share/{peminjaman}/sign/{role}', [PeminjamanController::class, 'sharedSign'])
    ->middleware('signed')
    ->name('peminjaman.share.sign');

Route::get('a/{serial}', [\App\Http\Controllers\PublicAssetController::class, 'show'])
    ->name('public.asset.show');
Route::post('a/{id}/verify', [\App\Http\Controllers\PublicAssetController::class, 'verify'])
    ->name('public.verify');

Route::get('check-assets', [\App\Http\Controllers\PublicAssetController::class, 'checkAssets'])
    ->name('public.check-assets');
Route::post('check-assets', [\App\Http\Controllers\PublicAssetController::class, 'fetchAssetsByEmail']);

Route::middleware(['auth', 'verified'])->group(function () {
    // Force password change (must run before any other auth page)
    Route::get('password/change', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::post('password/change', [ChangePasswordController::class, 'update'])->name('password.change.update');

    Route::middleware('password.change')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::post('users/sync-ldap', [UserController::class, 'syncLdap'])->name('users.sync-ldap');
    Route::post('users/sync', [UserController::class, 'sync'])->name('users.sync');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/upload', [UserController::class, 'uploadFile'])->name('users.upload');
    Route::delete('users/{user}/files/{fileId}', [UserController::class, 'deleteFile'])->name('users.files.delete');
    Route::post('users/{user}/checkin/asset/{assetId}', [UserController::class, 'checkinAsset'])->name('users.checkin.asset');
    Route::post('users/{user}/checkin/license/{licenseId}', [UserController::class, 'checkinLicense'])->name('users.checkin.license');
    Route::post('users/{user}/checkin/accessory/{accessoryId}', [UserController::class, 'checkinAccessory'])->name('users.checkin.accessory');
    Route::post('users/{user}/checkin/consumable/{consumableId}', [UserController::class, 'checkinConsumable'])->name('users.checkin.consumable');
    Route::get('users/{user}/edit-data', [UserController::class, 'getEditData'])->name('users.edit.data');
    Route::post('users/{user}/avatar', [UserController::class, 'updateAvatar'])->name('users.avatar.update');
    Route::delete('users/{user}/avatar', [UserController::class, 'deleteAvatar'])->name('users.avatar.delete');
    Route::put('users/{user}/password', [UserController::class, 'updatePassword'])->name('users.password.update');


    Route::get('auth-logs', [AuthLogController::class, 'index'])->name('auth-logs.index');
    Route::get('auth-logs/export', [AuthLogController::class, 'export'])->name('auth-logs.export');
    Route::get('action-logs', [\App\Http\Controllers\ActionLogController::class, 'index'])->name('action-logs.index');
    Route::get('action-logs/export', [\App\Http\Controllers\ActionLogController::class, 'export'])->name('action-logs.export');
    Route::get('form-logs', [\App\Http\Controllers\FormLogController::class, 'index'])->name('form-logs.index');
    Route::get('form-logs/export', [\App\Http\Controllers\FormLogController::class, 'export'])->name('form-logs.export');
    Route::get('bank-documents', [\App\Http\Controllers\BankDocumentController::class, 'index'])->name('bank-documents.index');
    Route::get('bank-documents/export', [\App\Http\Controllers\BankDocumentController::class, 'export'])->name('bank-documents.export');
    Route::get('report-logs', [\App\Http\Controllers\ReportLogController::class, 'index'])->name('report-logs.index');
    Route::get('report-logs/export', [\App\Http\Controllers\ReportLogController::class, 'export'])->name('report-logs.export');
    Route::get('helpdesk/export', [HelpdeskController::class, 'export'])->name('helpdesk.export');
    Route::get('helpdesk/print-batch', [HelpdeskController::class, 'printBatch'])->name('helpdesk.print-batch');
    Route::get('helpdesk/{ticket}/print', [HelpdeskController::class, 'print'])->name('helpdesk.print');
    Route::resource('helpdesk', HelpdeskController::class)
        ->except(['create', 'edit'])
        ->parameters(['helpdesk' => 'ticket']);
    Route::get('asset/create', [AssetController::class, 'create'])->name('asset.create');
    Route::get('asset/api/{assetId}', [AssetController::class, 'apiShow'])->name('api.asset.show');
    Route::get('asset/api-by-tag/{tag}', [AssetController::class, 'apiShowByTag'])->name('api.asset.show.by-tag');
    Route::get('asset/item/{assetId}', [AssetController::class, 'show'])->name('asset.show');
    Route::delete('asset/item/{assetId}', [AssetController::class, 'destroy'])->name('asset.destroy');
    Route::post('asset/item/{assetId}/stock', [AssetController::class, 'addStock'])->name('asset.stock.add');
    Route::post('asset/item/{assetId}/document', [AssetController::class, 'uploadDocument'])->name('asset.document.upload');
    Route::get('asset/item/{assetId}/tab-data', [AssetController::class, 'tabData'])->name('asset.tab.data');
    Route::get('asset/{assetId}/edit', [AssetController::class, 'edit'])->name('asset.edit');
    Route::post('asset', [AssetController::class, 'store'])->name('asset.store');
    Route::put('asset/{assetId}', [AssetController::class, 'update'])->name('asset.update');
    Route::get('asset/{status?}', [AssetController::class, 'index'])->name('asset.index');
    Route::post('asset/bulk-checkout', [AssetController::class, 'bulkCheckout'])->name('asset.bulk-checkout');
    Route::get('asset/check-serial', [AssetController::class, 'checkSerial'])->name('asset.check-serial');
    Route::get('asset/timeline/{serial}', [\App\Http\Controllers\AssetTimelineController::class, 'show'])->name('asset.timeline');
    Route::redirect('asset-menu', 'asset');
    Route::get('asset-menu/{section}', function (string $section): RedirectResponse {
        $mappedType = match (strtolower($section)) {
            'assets', 'asset', 'hardware' => 'assets',
            'license', 'licenses' => 'license',
            'accessories', 'accessory' => 'accessories',
            'consumable', 'consumables' => 'consumable',
            'component', 'components' => 'component',
            default => 'assets',
        };

        return redirect()->route('asset.index', ['type' => $mappedType]);
    });

    // STB routes
    Route::resource('stb', StbController::class);
    Route::get('stb/{stb}/print', [StbController::class, 'print'])->name('stb.print');
    Route::post('stb/{stb}/sign/{role}', [StbController::class, 'sign'])->name('stb.sign');
    Route::delete('stb/{stb}/sign/{role}', [StbController::class, 'clearSign'])->name('stb.sign.clear');
    Route::post('stb/{stb}/cancel', [StbController::class, 'cancel'])->name('stb.cancel');
    Route::post('/stb/{stb}/complete', [StbController::class, 'complete'])->name('stb.complete');
    Route::get('/stb-next-id', [StbController::class, 'nextStbId'])->name('stb.next-id');
    Route::get('/stb/last-out/{userId}', [StbController::class, 'lastOutStb'])->name('stb.last-out');

    // Peminjaman routes
    Route::get('peminjaman/last-out/{userId}', [PeminjamanController::class, 'lastOutPeminjaman'])->name('peminjaman.last-out');
    Route::resource('peminjaman', PeminjamanController::class)->parameters(['peminjaman' => 'peminjaman']);
    Route::get('peminjaman/{peminjaman}/print', [PeminjamanController::class, 'print'])->name('peminjaman.print');
    Route::post('peminjaman/{peminjaman}/sign/{role}', [PeminjamanController::class, 'sign'])->name('peminjaman.sign');
    Route::delete('peminjaman/{peminjaman}/sign/{role}', [PeminjamanController::class, 'clearSign'])->name('peminjaman.sign.clear');
    Route::post('peminjaman/{peminjaman}/cancel', [PeminjamanController::class, 'cancel'])->name('peminjaman.cancel');
    Route::post('peminjaman/{peminjaman}/complete', [PeminjamanController::class, 'complete'])->name('peminjaman.complete');
    Route::post('peminjaman/{peminjaman}/quick-return', [PeminjamanController::class, 'quickReturn'])->name('peminjaman.quick-return');

    // Inspection routes
    Route::resource('inspection', InspectionController::class)->parameters(['inspection' => 'inspection']);
    Route::get('inspection/{inspection}/print', [InspectionController::class, 'print'])->name('inspection.print');
    Route::post('inspection/{inspection}/complete', [InspectionController::class, 'complete'])->name('inspection.complete');
    Route::post('inspection/{inspection}/sign/{role}', [InspectionController::class, 'sign'])->name('inspection.sign');
    Route::delete('inspection/{inspection}/sign/{role}', [InspectionController::class, 'clearSign'])->name('inspection.sign.clear');
    Route::post('inspection/{inspection}/reupload-pdf', [InspectionController::class, 'reuploadPdf'])->name('inspection.reupload-pdf');
    // Audit (Stock Opname) routes
    Route::get('audit', [\App\Http\Controllers\AuditController::class, 'index'])->name('audit.index');
    Route::post('audit', [\App\Http\Controllers\AuditController::class, 'store'])->name('audit.store');
    Route::get('audit/{session}', [\App\Http\Controllers\AuditController::class, 'show'])->name('audit.show');
    Route::post('audit/{session}/scan', [\App\Http\Controllers\AuditController::class, 'scan'])->name('audit.scan');
    Route::post('audit/{session}/verify', [\App\Http\Controllers\AuditController::class, 'verify'])->name('audit.verify');
    Route::post('audit/{session}/complete', [\App\Http\Controllers\AuditController::class, 'complete'])->name('audit.complete');
    Route::get('audit/{session}/export', [\App\Http\Controllers\AuditController::class, 'export'])->name('audit.export');
    Route::post('audit/{session}/sync-item/{item}', [\App\Http\Controllers\AuditController::class, 'syncItem'])->name('audit.sync-item');

    // Inspection menu route (different from resource)
    Route::inertia('inspection-menu', 'Inspection')->name('inspection.menu');
    // Tools routes
    Route::get('label-generator', [\App\Http\Controllers\LabelGeneratorController::class, 'index'])->name('label-generator.index');
    Route::get('label-generator/pdf', [\App\Http\Controllers\LabelGeneratorController::class, 'pdf'])->name('label-generator.pdf');
    Route::get('asset/label/{tag}', [\App\Http\Controllers\AssetController::class, 'printLabel'])->name('asset.label.print');
    Route::get('asset/label/{tag}/pdf', [\App\Http\Controllers\AssetController::class, 'printLabelPdf'])->name('asset.label.pdf');
    Route::get('asset/item/{assetId}/stock-history', [\App\Http\Controllers\AssetController::class, 'stockHistory'])->name('asset.stock.history');
    Route::get('search', \App\Http\Controllers\SearchController::class)->name('universal-search');

    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\AppNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/{id}/read', [\App\Http\Controllers\AppNotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/mark-all-read', [\App\Http\Controllers\AppNotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    });

    // Vendor routes
    Route::get('vendors', [\App\Http\Controllers\VendorController::class, 'index'])->name('vendors.index');
    Route::post('vendors', [\App\Http\Controllers\VendorController::class, 'store'])->name('vendors.store');
    Route::put('vendors/{vendor}', [\App\Http\Controllers\VendorController::class, 'update'])->name('vendors.update');
    Route::delete('vendors/{vendor}', [\App\Http\Controllers\VendorController::class, 'destroy'])->name('vendors.destroy');
    
    // Knowledge Base routes
    Route::get('kb', [\App\Http\Controllers\KnowledgeBaseController::class, 'index'])->name('kb.index');
    Route::get('kb/{article:slug}', [\App\Http\Controllers\KnowledgeBaseController::class, 'show'])->name('kb.show');
    Route::post('kb', [\App\Http\Controllers\KnowledgeBaseController::class, 'store'])->name('kb.store');

    // Procurement routes
    Route::get('procurement', [\App\Http\Controllers\ProcurementController::class, 'index'])->name('procurement.index');
    Route::post('procurement', [\App\Http\Controllers\ProcurementController::class, 'store'])->name('procurement.store');
    Route::put('procurement/{procurement}', [\App\Http\Controllers\ProcurementController::class, 'update'])->name('procurement.update');

    // Report routes
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/stats', [ReportController::class, 'getStats'])->name('reports.stats');
    Route::get('infra-report', [\App\Http\Controllers\Report\InfraReportController::class, 'index'])->name('infra-report.index');
    Route::post('/infra-report/data', [App\Http\Controllers\Report\InfraReportController::class, 'data'])->name('infra-report.data');
    Route::get('/infra-report/export', [App\Http\Controllers\Report\InfraReportController::class, 'export'])->name('infra-report.export');
    Route::post('/infra-report/bandwidth/remark', [App\Http\Controllers\Report\InfraReportController::class, 'updateBandwidthRemark'])->name('infra-report.bandwidth.remark');
    Route::post('/infra-report/helpdesk/remark', [App\Http\Controllers\Report\InfraReportController::class, 'updateHelpdeskRemark'])->name('infra-report.helpdesk.remark');

    // Network Operation (Bandwidth + Uptime + ISP SLA)
    Route::get('network-operation', [\App\Http\Controllers\Network\NetworkOperationController::class, 'index'])->name('network-operation.index');
    Route::get('network-operation/export', [\App\Http\Controllers\Network\NetworkOperationController::class, 'export'])->name('network-operation.export');
    Route::get('network-operation/data', [\App\Http\Controllers\Network\NetworkOperationController::class, 'data'])->name('network-operation.data');

    // Support Operation
    Route::get('support-operation', [\App\Http\Controllers\Support\SupportOperationController::class, 'index'])->name('support-operation.index');
    Route::get('support-operation/summary', [\App\Http\Controllers\Support\SupportOperationController::class, 'summary'])->name('support-operation.summary');
    Route::get('support-operation/data', [\App\Http\Controllers\Support\SupportOperationController::class, 'data'])->name('support-operation.data');
    Route::get('support-operation/export', [\App\Http\Controllers\Support\SupportOperationController::class, 'export'])->name('support-operation.export');

    // Bandwidth sub-routes (under network-operation)
    Route::prefix('network-operation/bandwidth')->name('network-operation.bandwidth.')->group(function () {
        Route::get('/data',    [\App\Http\Controllers\BandwidthController::class, 'data'])->name('data');
        Route::get('/summary', [\App\Http\Controllers\BandwidthController::class, 'summary'])->name('summary');
        Route::get('/logs',    [\App\Http\Controllers\BandwidthController::class, 'logs'])->name('logs');
        Route::get('/export',  [\App\Http\Controllers\BandwidthController::class, 'export'])->name('export');
        Route::post('/fetch',  [\App\Http\Controllers\BandwidthController::class, 'fetch'])->name('fetch');
    });

    // Keep old /bandwidth routes as redirect for backward compatibility
    Route::redirect('bandwidth', '/network-operation?tab=bandwidth')->name('bandwidth.index');
    Route::prefix('bandwidth')->name('bandwidth.')->group(function () {
        Route::get('/data',    [\App\Http\Controllers\BandwidthController::class, 'data'])->name('data');
        Route::get('/summary', [\App\Http\Controllers\BandwidthController::class, 'summary'])->name('summary');
        Route::get('/logs',    [\App\Http\Controllers\BandwidthController::class, 'logs'])->name('logs');
        Route::get('/export',  [\App\Http\Controllers\BandwidthController::class, 'export'])->name('export');
        Route::post('/fetch',  [\App\Http\Controllers\BandwidthController::class, 'fetch'])->name('fetch');
    });

    // ISP SLA
    Route::prefix('isp-sla')->name('isp-sla.')->group(function () {
        Route::get('/data',                [\App\Http\Controllers\Network\IspSlaController::class, 'data'])->name('data');
        Route::put('/monthly',             [\App\Http\Controllers\Network\IspSlaController::class, 'updateMonthly'])->name('monthly.update');
        Route::get('/down-history',        [\App\Http\Controllers\Network\IspSlaController::class, 'downHistory'])->name('down-history.index');
        Route::post('/down-history',       [\App\Http\Controllers\Network\IspSlaController::class, 'storeDownHistory'])->name('down-history.store');
        Route::delete('/down-history/{id}',[\App\Http\Controllers\Network\IspSlaController::class, 'destroyDownHistory'])->name('down-history.destroy');
        Route::get('/contracts',           [\App\Http\Controllers\Network\IspSlaController::class, 'contracts'])->name('contracts.index');
        Route::post('/contracts',          [\App\Http\Controllers\Network\IspSlaController::class, 'storeContract'])->name('contracts.store');
        Route::put('/contracts/{id}',      [\App\Http\Controllers\Network\IspSlaController::class, 'updateContract'])->name('contracts.update');
        Route::post('/contracts/reorder',  [\App\Http\Controllers\Network\IspSlaController::class, 'reorderContracts'])->name('contracts.reorder');
    });

    // Uptime & Backup
    Route::prefix('uptime')->name('uptime.')->group(function () {
        Route::get('/data',              [\App\Http\Controllers\Network\UptimeController::class, 'data'])->name('data');
        Route::get('/logs',              [\App\Http\Controllers\Network\UptimeController::class, 'logs'])->name('logs');
        Route::post('/fetch',            [\App\Http\Controllers\Network\UptimeController::class, 'fetch'])->name('fetch');
        Route::get('/backup',            [\App\Http\Controllers\Network\UptimeController::class, 'backupData'])->name('backup.data');
        Route::put('/backup',            [\App\Http\Controllers\Network\UptimeController::class, 'updateBackup'])->name('backup.update');
        Route::get('/backup-summary',    [\App\Http\Controllers\Network\UptimeController::class, 'backupSummary'])->name('backup.summary');
        Route::get('/backup-settings',   [\App\Http\Controllers\Network\UptimeController::class, 'backupSettings'])->name('backup-settings');
        Route::put('/backup-settings',   [\App\Http\Controllers\Network\UptimeController::class, 'updateBackupSettings'])->name('backup-settings.update');
        Route::put('/excluded',          [\App\Http\Controllers\Network\UptimeController::class, 'toggleExcluded'])->name('excluded.update');
        Route::put('/maintenance',       [\App\Http\Controllers\Network\UptimeController::class, 'updateMaintenance'])->name('maintenance.update');
        Route::get('/maintenance-logs',  [\App\Http\Controllers\Network\UptimeController::class, 'maintenanceLogs'])->name('maintenance-logs.index');
        Route::post('/maintenance-logs', [\App\Http\Controllers\Network\UptimeController::class, 'storeMaintenanceLog'])->name('maintenance-logs.store');
        Route::put('/maintenance-logs/{id}', [\App\Http\Controllers\Network\UptimeController::class, 'updateMaintenanceLog'])->name('maintenance-logs.update');
        Route::delete('/maintenance-logs/{id}', [\App\Http\Controllers\Network\UptimeController::class, 'destroyMaintenanceLog'])->name('maintenance-logs.destroy');
    });

    // Server Operation (CPU, Memory, Disk, Temperature)
    Route::get('server-operation', [\App\Http\Controllers\Server\ServerOperationController::class, 'index'])->name('server-operation.index');
    Route::get('server-operation/export', [\App\Http\Controllers\Server\ServerOperationController::class, 'export'])->name('server-operation.export');
    Route::prefix('server-operation')->name('server-operation.')->group(function () {
        Route::get('/data',         [\App\Http\Controllers\Server\ServerOperationController::class, 'data'])->name('data');
        Route::get('/temperature',  [\App\Http\Controllers\Server\ServerOperationController::class, 'temperature'])->name('temperature');
        Route::get('/summary',      [\App\Http\Controllers\Server\ServerOperationController::class, 'summary'])->name('summary');
        Route::get('/logs',         [\App\Http\Controllers\Server\ServerOperationController::class, 'logs'])->name('logs');
        Route::post('/fetch',       [\App\Http\Controllers\Server\ServerOperationController::class, 'fetch'])->name('fetch');
        Route::put('/excluded',     [\App\Http\Controllers\Server\ServerOperationController::class, 'toggleExcluded'])->name('excluded.update');
        Route::put('/maintenance',  [\App\Http\Controllers\Server\ServerOperationController::class, 'updateMaintenance'])->name('maintenance.update');
        Route::get('/maintenance-logs', [\App\Http\Controllers\Server\ServerOperationController::class, 'maintenanceLogs'])->name('maintenance-logs.index');
        Route::post('/maintenance-logs', [\App\Http\Controllers\Server\ServerOperationController::class, 'storeMaintenanceLog'])->name('maintenance-logs.store');
        Route::put('/maintenance-logs/{id}', [\App\Http\Controllers\Server\ServerOperationController::class, 'updateMaintenanceLog'])->name('maintenance-logs.update');
        Route::delete('/maintenance-logs/{id}', [\App\Http\Controllers\Server\ServerOperationController::class, 'destroyMaintenanceLog'])->name('maintenance-logs.destroy');
    });

    // CCTV Operation (NVR + CCTV + Fingerprint)
    Route::get('cctv-operation', [\App\Http\Controllers\Cctv\CctvOperationController::class, 'index'])->name('cctv-operation.index');
    Route::prefix('cctv-operation')->name('cctv-operation.')->group(function () {
        Route::get('/data',              [\App\Http\Controllers\Cctv\CctvOperationController::class, 'data'])->name('data');
        Route::get('/summary',           [\App\Http\Controllers\Cctv\CctvOperationController::class, 'summary'])->name('summary');
        Route::get('/logs',              [\App\Http\Controllers\Cctv\CctvOperationController::class, 'logs'])->name('logs');
        Route::get('/export',            [\App\Http\Controllers\Cctv\CctvOperationController::class, 'export'])->name('export');
        Route::post('/fetch',            [\App\Http\Controllers\Cctv\CctvOperationController::class, 'fetch'])->name('fetch');
        Route::put('/excluded',          [\App\Http\Controllers\Cctv\CctvOperationController::class, 'toggleExcluded'])->name('excluded.update');
        Route::put('/maintenance',       [\App\Http\Controllers\Cctv\CctvOperationController::class, 'updateMaintenance'])->name('maintenance.update');
        Route::get('/maintenance-logs',  [\App\Http\Controllers\Cctv\CctvOperationController::class, 'maintenanceLogs'])->name('maintenance-logs.index');
        Route::post('/maintenance-logs', [\App\Http\Controllers\Cctv\CctvOperationController::class, 'storeMaintenanceLog'])->name('maintenance-logs.store');
        Route::put('/maintenance-logs/{id}', [\App\Http\Controllers\Cctv\CctvOperationController::class, 'updateMaintenanceLog'])->name('maintenance-logs.update');
        Route::delete('/maintenance-logs/{id}', [\App\Http\Controllers\Cctv\CctvOperationController::class, 'destroyMaintenanceLog'])->name('maintenance-logs.destroy');
        Route::get('/nvr-records',  [\App\Http\Controllers\Cctv\CctvOperationController::class, 'nvrRecords'])->name('nvr-records.index');
        Route::put('/nvr-records',  [\App\Http\Controllers\Cctv\CctvOperationController::class, 'updateNvrRecord'])->name('nvr-records.update');
    });

    Route::prefix('cctv')->name('cctv.')->group(function () {
        Route::get('/',        [\App\Http\Controllers\CctvController::class, 'index'])->name('index');
        Route::get('/data',    [\App\Http\Controllers\CctvController::class, 'data'])->name('data');
        Route::get('/summary', [\App\Http\Controllers\CctvController::class, 'summary'])->name('summary');
        Route::get('/logs',    [\App\Http\Controllers\CctvController::class, 'logs'])->name('logs');
        Route::get('/export',  [\App\Http\Controllers\CctvController::class, 'export'])->name('export');
        Route::post('/fetch',  [\App\Http\Controllers\CctvController::class, 'fetch'])->name('fetch');
    });

    }); // end password.change middleware group
});
Route::prefix('api/snipeit')->group(function () {
    Route::get('/users', [SnipeItController::class, 'users']);
    Route::get('/groups', [SnipeItController::class, 'locations']);
    Route::get('/user-titles', [SnipeItController::class, 'userTitles']);
    Route::get('/asset-statuses', [SnipeItController::class, 'statuses']);
    Route::get('/assets/{type}', [SnipeItController::class, 'assetsByType']);
    Route::get('/models/{id}', [SnipeItController::class, 'modelDetail'])->middleware(['auth', 'verified']);
    Route::post('/models', [SnipeItController::class, 'createModel'])->middleware(['auth', 'verified']);
    Route::post('/categories', [SnipeItController::class, 'createCategory'])->middleware(['auth', 'verified']);
    Route::get('/suppliers', [SnipeItController::class, 'suppliers']);
    Route::post('/suppliers', [SnipeItController::class, 'createSupplier'])->middleware(['auth', 'verified']);
    Route::get('/manufacturers', [SnipeItController::class, 'manufacturers']);
    Route::post('/manufacturers', [SnipeItController::class, 'createManufacturer'])->middleware(['auth', 'verified']);
    Route::get('/users/{id}/assets/{type?}', [SnipeItController::class, 'userAssets'])->middleware(['auth', 'verified']);
});

require __DIR__.'/settings.php';
