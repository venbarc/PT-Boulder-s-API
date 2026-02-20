<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/pull-history', [DashboardController::class, 'pullHistory'])->name('pull-history');
Route::get('/export/provider-revenue', [ExportController::class, 'exportProviderRevenueCsv'])->name('export.provider_revenue');
Route::get('/export/general-visit', [ExportController::class, 'exportGeneralVisitCsv'])->name('export.general_visit');
Route::get('/export/patient-report', [ExportController::class, 'exportPatientReportCsv'])->name('export.patient_report');
Route::get('/export/demographics', [ExportController::class, 'exportDemographicsCsv'])->name('export.demographics');
Route::get('/export/therapists', [ExportController::class, 'exportTherapistsCsv'])->name('export.therapists');
Route::get('/export/locations', [ExportController::class, 'exportLocationsCsv'])->name('export.locations');
Route::get('/export/services', [ExportController::class, 'exportServicesCsv'])->name('export.services');
Route::get('/export/master-patients', [ExportController::class, 'exportMasterPatientsCsv'])->name('export.master_patients');
Route::get('/export/master-users', [ExportController::class, 'exportMasterUsersCsv'])->name('export.master_users');
