<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

// Accounts
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Signed-in users: library, recorder, upload, manage
Route::middleware('auth')->group(function () {
    Route::get('/', [VideoController::class, 'index'])->name('videos.index');
    Route::get('/record', [VideoController::class, 'record'])->name('videos.record');

    // Upload flow: create -> chunks -> finish (-> thumbnail)
    Route::post('/videos', [VideoController::class, 'store'])->name('videos.store');
    Route::post('/videos/{video:slug}/chunk', [VideoController::class, 'chunk'])->name('videos.chunk');
    Route::post('/videos/{video:slug}/finish', [VideoController::class, 'finish'])->name('videos.finish');
    Route::post('/videos/{video:slug}/thumbnail', [VideoController::class, 'thumbnail'])->name('videos.thumbnail');
    Route::patch('/videos/{video:slug}', [VideoController::class, 'update'])->name('videos.update');
    Route::delete('/videos/{video:slug}', [VideoController::class, 'destroy'])->name('videos.destroy');
});

// Debug only: browser-side recorder step log, written to storage/logs/laravel.log
if (config('app.debug')) {
    Route::post('/debug-log', function (\Illuminate\Http\Request $r) {
        \Illuminate\Support\Facades\Log::info('[recorder] '.substr((string) $r->input('msg'), 0, 500), ['ua' => substr((string) $r->userAgent(), 0, 200)]);

        return response()->noContent();
    });
}

// Public: anyone with the link can watch
Route::get('/v/{video:slug}', [VideoController::class, 'show'])->name('videos.show');
Route::get('/v/{video:slug}/file', [VideoController::class, 'file'])->name('videos.file');
Route::get('/v/{video:slug}/download', [VideoController::class, 'download'])->name('videos.download');
Route::get('/v/{video:slug}/thumb', [VideoController::class, 'thumb'])->name('videos.thumb');
Route::post('/v/{video:slug}/view', [VideoController::class, 'view'])->middleware('throttle:30,1')->name('videos.view');
