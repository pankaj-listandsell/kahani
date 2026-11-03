<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FacebookController;
use App\Http\Controllers\Admin\InstagramController;
use App\Http\Controllers\Admin\NuskhaController;
use App\Http\Controllers\Admin\PremPatraController;
use App\Http\Controllers\Admin\PartController;
use App\Http\Controllers\Admin\RashifalController;
use App\Http\Controllers\Admin\SalahController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Admin\QuizController;
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

Route::get('/privacy-policy', function () {
    return view('privacy');
})->name('privacy');

/*
|--------------------------------------------------------------------------
| Admin routes — sirf logged-in admin (middleware 'auth')
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // Dashboard & Analytics
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::post('analytics/refresh', [AnalyticsController::class, 'refresh'])->name('analytics.refresh');
    Route::post('followers/refresh', [AnalyticsController::class, 'refreshFollowers'])->name('followers.refresh');

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('settings/reel-motion', [SettingsController::class, 'updateReelMotion'])->name('settings.reelmotion');
    Route::post('settings/reel-audio', [SettingsController::class, 'updateReelAudio'])->name('settings.reelaudio');
    Route::post('settings/reel-outro', [SettingsController::class, 'updateReelOutro'])->name('settings.reeloutro');
    Route::put('settings/quiz-caption', [SettingsController::class, 'updateQuizCaption'])->name('settings.quizcaption');

    // Instagram
    Route::get('instagram', [InstagramController::class, 'index'])->name('instagram.index');
    Route::put('instagram/settings', [InstagramController::class, 'saveSettings'])->name('instagram.settings');
    Route::put('instagram/auto-post', [InstagramController::class, 'saveAutoPost'])->name('instagram.autopost');
    Route::post('instagram/test', [InstagramController::class, 'test'])->name('instagram.test');
    Route::get('instagram/limit', [InstagramController::class, 'limit'])->name('instagram.limit');
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
    Route::post('youtube/parts/{part}/captions', [YoutubeController::class, 'generatePartCaptions'])->name('youtube.part.captions');

    // Facebook (Page photo + Reel)
    Route::get('facebook', [FacebookController::class, 'index'])->name('facebook.index');
    Route::put('facebook/settings', [FacebookController::class, 'saveSettings'])->name('facebook.settings');
    Route::put('facebook/auto-post', [FacebookController::class, 'saveAutoPost'])->name('facebook.autopost');
    Route::post('facebook/test', [FacebookController::class, 'test'])->name('facebook.test');
    Route::post('facebook/cards/{card}/photo', [FacebookController::class, 'postCard'])->name('facebook.card.photo');
    Route::post('facebook/cards/{card}/reel', [FacebookController::class, 'postReel'])->name('facebook.card.reel');
    Route::post('facebook/parts/{part}/photos', [FacebookController::class, 'postPart'])->name('facebook.part.photos');
    Route::post('facebook/parts/{part}/reels', [FacebookController::class, 'postPartReels'])->name('facebook.part.reels');

    // Quiz (MCQ) — alag section
    Route::get('quiz', [QuizController::class, 'index'])->name('quiz.index');
    Route::post('quiz/generate', [QuizController::class, 'generate'])->name('quiz.generate');
    Route::post('quiz/save', [QuizController::class, 'save'])->name('quiz.save');
    Route::post('quiz/timer', [QuizController::class, 'timer'])->name('quiz.timer');
    Route::get('quiz/{story}', [QuizController::class, 'show'])->name('quiz.show');
    Route::delete('quiz/{story}', [QuizController::class, 'destroy'])->name('quiz.destroy');

    // 💬 Salah Studio (Reader ka sawaal + expert jawab — advice text cards)
    Route::get('salah', [SalahController::class, 'index'])->name('salah.index');
    Route::post('salah/generate', [SalahController::class, 'generate'])->name('salah.generate');
    Route::post('salah/save', [SalahController::class, 'save'])->name('salah.save');
    Route::get('salah/{story}', [SalahController::class, 'show'])->name('salah.show');
    Route::delete('salah/{story}', [SalahController::class, 'destroy'])->name('salah.destroy');

    // 🔯 Aaj nu Rashifal — 12 rashi cards, sirf apne din auto-post
    Route::get('rashifal', [RashifalController::class, 'index'])->name('rashifal.index');
    Route::post('rashifal/generate', [RashifalController::class, 'generate'])->name('rashifal.generate');
    Route::post('rashifal/save', [RashifalController::class, 'save'])->name('rashifal.save');
    Route::get('rashifal/{story}', [RashifalController::class, 'show'])->name('rashifal.show');
    Route::delete('rashifal/{story}', [RashifalController::class, 'destroy'])->name('rashifal.destroy');

    // 🌿 Gharelu Nuskhe & Recipe cards
    Route::get('nuskha', [NuskhaController::class, 'index'])->name('nuskha.index');
    Route::post('nuskha/generate', [NuskhaController::class, 'generate'])->name('nuskha.generate');
    Route::post('nuskha/save', [NuskhaController::class, 'save'])->name('nuskha.save');
    Route::get('nuskha/{story}', [NuskhaController::class, 'show'])->name('nuskha.show');
    Route::delete('nuskha/{story}', [NuskhaController::class, 'destroy'])->name('nuskha.destroy');

    // 💌 Prem Patra (love letters — har card alag love theme)
    Route::get('patra', [PremPatraController::class, 'index'])->name('patra.index');
    Route::post('patra/generate', [PremPatraController::class, 'generate'])->name('patra.generate');
    Route::post('patra/save', [PremPatraController::class, 'save'])->name('patra.save');
    Route::get('patra/{story}', [PremPatraController::class, 'show'])->name('patra.show');
    Route::delete('patra/{story}', [PremPatraController::class, 'destroy'])->name('patra.destroy');

    // Topic se AI kahani generate (create form bharne ke liye) — resource se pehle
    Route::post('stories/generate', [StoryController::class, 'generateFromTopic'])->name('stories.generate');

    // Dusri website se kahani(yan) import (URL se) — resource se pehle
    Route::post('stories/import', [StoryController::class, 'import'])->name('stories.import');

    // Bulk delete for stories/collections
    Route::post('stories/bulk-delete', [StoryController::class, 'bulkDestroy'])->name('stories.bulk_destroy');

    // Story CRUD
    Route::resource('stories', StoryController::class);

    // Cover image khud se upload karo (9:16)
    Route::post('stories/{story}/cover/upload', [StoryController::class, 'uploadCover'])->name('stories.cover.upload');
    // Pixabay se free cover photo (AI cover ka alternative)
    Route::post('stories/{story}/cover/search', [StoryController::class, 'coverSearch'])->name('stories.cover.search');
    Route::post('stories/{story}/cover/pick', [StoryController::class, 'coverPick'])->name('stories.cover.pick');

    // Per-story/collection reel audio mode (voice / voice_music / music)
    Route::put('stories/{story}/audio-mode', [StoryController::class, 'audioMode'])->name('stories.audiomode');
    // Per-story/collection auto-post target platforms
    Route::put('stories/{story}/platforms', [StoryController::class, 'platforms'])->name('stories.platforms');

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
    Route::post('cards/bulk-delete', [CardController::class, 'bulkDestroy'])->name('cards.bulk_destroy');
    Route::post('cards/{card}/reel', [CardController::class, 'reel'])->name('cards.reel');
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

        // Nayi files upload ke baad config/route/view cache saaf karo
        Route::post('cache/clear', [SettingsController::class, 'clearCache'])->name('cache.clear');
    });

    // Impersonation ke dauraan hi chalega — isliye role:admin ke bahar
    Route::post('return-to-admin', [UserManagementController::class, 'returnToAdmin'])->name('users.returnToAdmin');
});

// Instagram Meta Webhook for Comment Auto-Reply (Public verification + listener)
Route::get('webhook/instagram', [\App\Http\Controllers\Admin\InstagramWebhookController::class, 'verify'])->name('instagram.webhook.verify');
Route::post('webhook/instagram', [\App\Http\Controllers\Admin\InstagramWebhookController::class, 'handle'])->name('instagram.webhook.handle');
