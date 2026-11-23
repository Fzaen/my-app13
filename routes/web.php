<?php

use App\Http\Controllers\ManualBookController;
use App\Http\Controllers\PbActionController;
use App\Http\Controllers\PbAllController;
use App\Http\Controllers\PbItemController;
use App\Http\Controllers\PluInfoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderReportController;
use App\Http\Controllers\SalesExportController;
use App\Http\Middleware\EnsurePbWriteIp;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/pb/all', [PbAllController::class, 'index'])->name('pb.all');
    Route::get('/pb/all/export', [PbAllController::class, 'export'])->name('pb.all.export');
    Route::get('/pb/reports/{report}', [PurchaseOrderReportController::class, 'index'])
        ->name('pb.reports.show');
    Route::get('/pb/reports/{report}/export', [PurchaseOrderReportController::class, 'export'])
        ->name('pb.reports.export');
    Route::get('/pb/reports/{report}/copy', [PurchaseOrderReportController::class, 'copy'])
        ->name('pb.reports.copy');
    Route::get('/pb/items/details', [PbItemController::class, 'details'])
        ->name('pb.items.details');
    Route::get('/pb/items/location', [PbItemController::class, 'location'])
        ->name('pb.items.location');
    Route::get('/pb/actions/gift', [PbActionController::class, 'createGift'])
        ->name('pb.actions.gift.create');
    Route::post('/pb/actions/gift', [PbActionController::class, 'storeGift'])
        ->middleware(EnsurePbWriteIp::class)
        ->name('pb.actions.gift.store');
    Route::get('/pb/actions/driver-details', [PbActionController::class, 'driverDetails'])
        ->name('pb.actions.driver-details');
    Route::get('/pb/actions/loyalty', [PbActionController::class, 'loyalty'])
        ->name('pb.actions.loyalty.index');
    Route::get('/pb/actions/loyalty/details', [PbActionController::class, 'loyaltyDetails'])
        ->name('pb.actions.loyalty.details');
    Route::post('/pb/actions/loyalty', [PbActionController::class, 'updateLoyalty'])
        ->middleware(EnsurePbWriteIp::class)
        ->name('pb.actions.loyalty.update');
    Route::get('/pb/actions/request/{mode}', [PbActionController::class, 'requestForm'])
        ->whereIn('mode', ['create', 'edit'])
        ->name('pb.actions.request.form');
    Route::get('/manual-book/faq/ba-online', [ManualBookController::class, 'baOnline'])
        ->middleware('can:access-manual-book')
        ->name('manual-book.faq.ba-online');
    Route::post('/pb/actions/request', [PbActionController::class, 'saveRequest'])
        ->middleware(EnsurePbWriteIp::class)
        ->name('pb.actions.request.save');
    Route::get('/data/plu', [PluInfoController::class, 'index'])->name('plu.info');
    Route::get('/data/plu/suggestions', [PluInfoController::class, 'suggestions'])->name('plu.info.suggestions');
    Route::get('/data/plu/summary', [PluInfoController::class, 'summary'])->name('plu.info.summary');
    Route::get('/data/plu/panel', [PluInfoController::class, 'panel'])->name('plu.info.panel');
    Route::get('/data/export/sales', [SalesExportController::class, 'index'])->name('sales.export');
    Route::post('/data/export/sales/download', [SalesExportController::class, 'export'])
        ->name('sales.export.download');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

require __DIR__.'/auth.php';
