<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InstagramController;
use App\Http\Controllers\Admin\PartController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StoryController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Home — seedha admin dashboard / login par bhej do (koi public site nahi)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Auth routes — admin login/logout
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Admin routes — sirf logged-in admin (middleware 'auth')
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');

    // Instagram
    Route::get('instagram', [InstagramController::class, 'index'])->name('instagram.index');
    Route::put('instagram/settings', [InstagramController::class, 'saveSettings'])->name('instagram.settings');
    Route::put('instagram/auto-post', [InstagramController::class, 'saveAutoPost'])->name('instagram.autopost');
    Route::post('instagram/test', [InstagramController::class, 'test'])->name('instagram.test');
    Route::post('instagram/cards/{card}/post', [InstagramController::class, 'postCard'])->name('instagram.card.post');
    Route::post('instagram/cards/{card}/reel', [InstagramController::class, 'postReel'])->name('instagram.card.reel');
    Route::post('instagram/parts/{part}/post', [InstagramController::class, 'postPart'])->name('instagram.part.post');
    Route::post('instagram/parts/{part}/reels', [InstagramController::class, 'postPartReels'])->name('instagram.part.reels');

    // Story CRUD
    Route::resource('stories', StoryController::class);

    // Story ke liye AI (Pollinations) se 9:16 cover image banao
    Route::post('stories/{story}/cover', [StoryController::class, 'generateCover'])->name('stories.cover.generate');
    // Ya khud se cover image file upload karo
    Route::post('stories/{story}/cover/upload', [StoryController::class, 'uploadCover'])->name('stories.cover.upload');

    // Parts — kahani ke andar nested
    Route::get('stories/{story}/parts/create', [PartController::class, 'create'])->name('parts.create');
    Route::post('stories/{story}/parts', [PartController::class, 'store'])->name('parts.store');
    Route::get('parts/{part}/edit', [PartController::class, 'edit'])->name('parts.edit');
    Route::put('parts/{part}', [PartController::class, 'update'])->name('parts.update');
    Route::delete('parts/{part}', [PartController::class, 'destroy'])->name('parts.destroy');

    // Text cards — part ke text se images banao
    Route::get('parts/{part}/cards', [CardController::class, 'editor'])->name('parts.cards');
    Route::post('parts/{part}/cards', [CardController::class, 'store'])->name('parts.cards.store');
    Route::delete('parts/{part}/cards', [CardController::class, 'clear'])->name('parts.cards.clear');
});
