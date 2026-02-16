<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/export/provider-revenue', [ExportController::class, 'exportProviderRevenueCsv'])->name('export.provider_revenue');
Route::get('/export/general-visit', [ExportController::class, 'exportGeneralVisitCsv'])->name('export.general_visit');
