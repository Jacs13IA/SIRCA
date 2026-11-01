<?php

use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['mcp.api', 'throttle:60,1'])
    ->name('api.v1.')
    ->group(function () {
        Route::get('/report-types', [ReportTypeController::class, 'index'])->name('report-types.index');
        Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/report-status/{folio}', [ReportController::class, 'status'])->name('reports.status');
        Route::get('/reports/{folio}', [ReportController::class, 'show'])->name('reports.show');
        Route::post('/reports/{folio}/evidence', [EvidenceController::class, 'store'])->name('reports.evidence.store');
        Route::post('/reports/{folio}/evidence/reference', [EvidenceController::class, 'storeReference'])->name('reports.evidence.reference.store');
    });