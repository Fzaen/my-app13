<?php

use App\Http\Controllers\PbAllController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesExportController;
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
    Route::get('/data/export/sales', [SalesExportController::class, 'index'])->name('sales.export');
    Route::post('/data/export/sales/download', [SalesExportController::class, 'export'])
        ->name('sales.export.download');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

require __DIR__.'/auth.php';
