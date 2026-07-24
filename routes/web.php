<?php

use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ChallengeFileController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicProfileController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('locale', LocaleController::class)->name('locale.switch');

Route::get('u/{user:username}', [PublicProfileController::class, 'show'])->name('profile.show');

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
});

require __DIR__.'/settings.php';
