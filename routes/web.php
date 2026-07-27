<?php

use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ChallengeFileController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommentReportController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\WriteupController;
use App\Http\Controllers\WriteupImageController;
use App\Http\Controllers\WriteupVoteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('locale', LocaleController::class)->name('locale.switch');

Route::get('u/{user:username}', [PublicProfileController::class, 'show'])->name('profile.show');

Route::get('leaderboard', [LeaderboardController::class, 'global'])->name('leaderboard.global');
Route::get('leaderboard/weekly', [LeaderboardController::class, 'weekly'])->name('leaderboard.weekly');
Route::get('leaderboard/category/{category}', [LeaderboardController::class, 'category'])
    ->name('leaderboard.category');

Route::get('challenges', [ChallengeController::class, 'index'])->name('challenges.index');
Route::get('challenges/{challenge:slug}', [ChallengeController::class, 'show'])->name('challenges.show');

Route::middleware('signed')->get(
    'challenges/{challenge:slug}/files/{file}',
    [ChallengeFileController::class, 'download'],
)->name('challenges.files.download');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::post('challenges/{challenge:slug}/submit', [ChallengeController::class, 'submit'])
        ->name('challenges.submit');

    // Writeups (solved-gated in controllers)
    Route::get('challenges/{challenge:slug}/writeups/create', [WriteupController::class, 'create'])
        ->name('writeups.create');
    Route::post('challenges/{challenge:slug}/writeups', [WriteupController::class, 'store'])
        ->name('writeups.store');
    Route::post('writeups/images', [WriteupImageController::class, 'store'])
        ->name('writeups.images.store');
    Route::post('writeups/{writeup}/upvote', [WriteupVoteController::class, 'store'])
        ->name('writeups.upvote');
    Route::delete('writeups/{writeup}/upvote', [WriteupVoteController::class, 'destroy'])
        ->name('writeups.upvote.destroy');

    // Comments (solved-gated in controllers)
    Route::post('challenges/{challenge:slug}/comments', [CommentController::class, 'store'])
        ->name('comments.store');
    Route::post('comments/{comment}/report', [CommentReportController::class, 'store'])
        ->name('comments.report');
});

require __DIR__.'/settings.php';
