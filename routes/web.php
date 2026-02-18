<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/export/provider-revenue', [ExportController::class, 'exportProviderRevenueCsv'])->name('export.provider_revenue');
Route::get('/export/general-visit', [ExportController::class, 'exportGeneralVisitCsv'])->name('export.general_visit');
Route::get('/export/demographics', [ExportController::class, 'exportDemographicsCsv'])->name('export.demographics');
Route::get('/export/patient-report', [ExportController::class, 'exportPatientReportCsv'])->name('export.patient_report');
