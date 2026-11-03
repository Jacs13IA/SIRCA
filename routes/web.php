<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\ReportTypeController;
use App\Http\Controllers\Admin\CitizenController;
use App\Http\Controllers\Admin\CitizenMatchReviewController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'chat.index')->name('chat.home');

Route::post('/chat/messages', [ChatController::class, 'send'])
    ->middleware('throttle:20,1')
    ->name('chat.messages.store');

Route::post('/evidence-staging', [ChatController::class, 'storeEvidence'])
    ->middleware('throttle:10,1')
    ->name('evidence-staging.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('/reports/{report}/assignments', [ReportController::class, 'assign'])->name('reports.assign');
    Route::patch('/reports/{report}/status', [ReportController::class, 'updateStatus'])->name('reports.status.update');
    Route::post('/reports/{report}/activities', [ReportController::class, 'storeActivity'])->name('reports.activities.store');
    Route::post('/reports/{report}/evidence', [EvidenceController::class, 'store'])->name('reports.evidence.store');
    Route::get('/reports/{report}/evidence/{evidence}', [EvidenceController::class, 'download'])->name('reports.evidence.download');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::patch('users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');
        Route::resource('citizens', CitizenController::class);
        Route::patch('citizens/{citizen}/restore', [CitizenController::class, 'restore'])->withTrashed()->name('citizens.restore');
        Route::get('citizen-match-reviews', [CitizenMatchReviewController::class, 'index'])->name('citizen-match-reviews.index');
        Route::patch('citizen-match-reviews/{review}/resolve', [CitizenMatchReviewController::class, 'resolve'])->name('citizen-match-reviews.resolve');
        Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
        Route::patch('departments/{department}/active', [DepartmentController::class, 'toggleActive'])->name('departments.toggle-active');
        Route::resource('report-types', ReportTypeController::class)->except(['show', 'destroy']);
        Route::patch('report-types/{report_type}/active', [ReportTypeController::class, 'toggleActive'])->name('report-types.toggle-active');
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
