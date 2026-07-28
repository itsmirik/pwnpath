<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ChallengeController;
use App\Http\Controllers\Admin\CommentReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SyncController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WriteupModerationController;
use Illuminate\Support\Facades\Route;

/*
 * Admin dashboard (plan §8). Coarse gate: any staff role reaches /admin; finer
 * per-capability gates apply inside. Suspended accounts are rejected by the
 * `role` middleware.
 */
Route::middleware(['auth', 'verified', 'role:author,moderator,admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Challenge authoring — authors manage their own, admins manage all.
        Route::middleware('role:author,admin')->group(function () {
            Route::get('challenges', [ChallengeController::class, 'index'])->name('challenges.index');
            Route::get('challenges/create', [ChallengeController::class, 'create'])->name('challenges.create');
            Route::post('challenges', [ChallengeController::class, 'store'])->name('challenges.store');
            Route::get('challenges/{challenge}/edit', [ChallengeController::class, 'edit'])->name('challenges.edit');
            Route::put('challenges/{challenge}', [ChallengeController::class, 'update'])->name('challenges.update');

            Route::post('challenges/{challenge}/files', [ChallengeController::class, 'uploadFile'])
                ->name('challenges.files.store');
            Route::delete('challenges/{challenge}/files/{file}', [ChallengeController::class, 'destroyFile'])
                ->name('challenges.files.destroy');

            Route::post('challenges/{challenge}/submit', [ChallengeController::class, 'submitForReview'])
                ->name('challenges.submit');
        });

        // Challenge lifecycle + repo sync — admin only.
        Route::middleware('role:admin')->group(function () {
            Route::post('challenges/{challenge}/publish', [ChallengeController::class, 'publish'])
                ->name('challenges.publish');
            Route::post('challenges/{challenge}/unpublish', [ChallengeController::class, 'unpublish'])
                ->name('challenges.unpublish');
            Route::post('challenges/{challenge}/archive', [ChallengeController::class, 'archive'])
                ->name('challenges.archive');

            Route::post('sync', [SyncController::class, 'store'])->name('sync');
        });

        // Content moderation — moderators + admins.
        Route::middleware('role:moderator,admin')->group(function () {
            Route::get('writeups', [WriteupModerationController::class, 'index'])->name('writeups.index');
            Route::post('writeups/{writeup}/approve', [WriteupModerationController::class, 'approve'])
                ->name('writeups.approve');
            Route::post('writeups/{writeup}/reject', [WriteupModerationController::class, 'reject'])
                ->name('writeups.reject');

            Route::get('reports', [CommentReportController::class, 'index'])->name('reports.index');
            Route::post('comments/{comment}/hide', [CommentReportController::class, 'hide'])
                ->name('comments.hide');
            Route::post('reports/{report}/dismiss', [CommentReportController::class, 'dismiss'])
                ->name('reports.dismiss');
        });

        // Users + audit log — admin only.
        Route::middleware('role:admin')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
            Route::post('users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
            Route::post('users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');

            Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        });
    });
