<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\GeneralVisitApiController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthTokenController::class, 'issue']);

Route::middleware('internal.api.token')->group(function (): void {
    Route::get('/report/general-visit', [GeneralVisitApiController::class, 'index']);
});
