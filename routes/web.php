<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InstagramController;
use App\Http\Controllers\Admin\PartController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\YoutubeController;
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
    Route::post('instagram/music', [InstagramController::class, 'saveReelMusic'])->name('instagram.music');
    Route::delete('instagram/music', [InstagramController::class, 'removeReelMusic'])->name('instagram.music.remove');
    Route::get('instagram/cards/{card}/caption', [InstagramController::class, 'getCaption'])->name('instagram.card.caption.get');
    Route::post('instagram/cards/{card}/caption/generate', [InstagramController::class, 'generateCaption'])->name('instagram.card.caption.generate');
    Route::put('instagram/cards/{card}/caption', [InstagramController::class, 'saveCaption'])->name('instagram.card.caption.save');
    Route::post('instagram/cards/{card}/post', [InstagramController::class, 'postCard'])->name('instagram.card.post');
    Route::post('instagram/cards/{card}/reel', [InstagramController::class, 'postReel'])->name('instagram.card.reel');
    Route::post('instagram/parts/{part}/post', [InstagramController::class, 'postPart'])->name('instagram.part.post');
    Route::post('instagram/parts/{part}/reels', [InstagramController::class, 'postPartReels'])->name('instagram.part.reels');
    Route::post('instagram/parts/{part}/captions', [InstagramController::class, 'generatePartCaptions'])->name('instagram.part.captions');

    // YouTube (Shorts)
    Route::get('youtube', [YoutubeController::class, 'index'])->name('youtube.index');
    Route::get('youtube/connect', [YoutubeController::class, 'connect'])->name('youtube.connect');
    Route::get('youtube/callback', [YoutubeController::class, 'callback'])->name('youtube.callback');
    Route::post('youtube/disconnect', [YoutubeController::class, 'disconnect'])->name('youtube.disconnect');
    Route::post('youtube/test', [YoutubeController::class, 'test'])->name('youtube.test');
    Route::put('youtube/auto-post', [YoutubeController::class, 'saveAutoPost'])->name('youtube.autopost');
    Route::post('youtube/music', [YoutubeController::class, 'saveMusic'])->name('youtube.music');
    Route::delete('youtube/music', [YoutubeController::class, 'removeMusic'])->name('youtube.music.remove');
    Route::get('youtube/cards/{card}/caption', [YoutubeController::class, 'getCaption'])->name('youtube.card.caption.get');
    Route::post('youtube/cards/{card}/caption/generate', [YoutubeController::class, 'generateCaption'])->name('youtube.card.caption.generate');
    Route::put('youtube/cards/{card}/caption', [YoutubeController::class, 'saveCaption'])->name('youtube.card.caption.save');
    Route::post('youtube/cards/{card}/short', [YoutubeController::class, 'postCard'])->name('youtube.card.short');
    Route::post('youtube/parts/{part}/short', [YoutubeController::class, 'postPart'])->name('youtube.part.short');

    // Topic se AI kahani generate (create form bharne ke liye) — resource se pehle
    Route::post('stories/generate', [StoryController::class, 'generateFromTopic'])->name('stories.generate');

    // Story CRUD
    Route::resource('stories', StoryController::class);

    // Story ke liye AI (Pollinations) se 9:16 cover image banao
    Route::post('stories/{story}/cover', [StoryController::class, 'generateCover'])->name('stories.cover.generate');
    // Kahani ke hisab se AI cover (Gemini image, ek click)
    Route::post('stories/{story}/cover/ai', [StoryController::class, 'generateCoverAi'])->name('stories.cover.ai');
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
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    // User management — sirf admin
    Route::middleware('role:admin')->group(function () {
        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/login-as', [UserManagementController::class, 'loginAs'])->name('users.loginAs');
    });

    // Impersonation ke dauraan hi chalega — isliye role:admin ke bahar
    Route::post('return-to-admin', [UserManagementController::class, 'returnToAdmin'])->name('users.returnToAdmin');
});
