<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Instagram auto-post — har minute chalta hai; command khud check karta hai ki
// auto-post ON hai, abhi kisi time-window me hai, aur interval beet gaya.
// Chalane ke liye ye background me chahiye:  php artisan schedule:work
// withoutOverlapping(30): hosting process ko beech me kill kare to lock 30 min me khud hat jaaye
// (default 24 ghante tha — tab tak auto-post chup-chaap skip hota rehta tha)
Schedule::command('instagram:auto-post')->everyMinute()->withoutOverlapping(30);

// YouTube Shorts auto-post — IG jaisa hi. Command khud check karta hai ki
// auto-post ON hai, time-window me hai, aur slot due hai.
Schedule::command('youtube:auto-post')->everyMinute()->withoutOverlapping(30);

// Facebook Page auto-post (photo/reel) — IG jaisa hi.
Schedule::command('facebook:auto-post')->everyMinute()->withoutOverlapping(30);

// Background queue (bulk "All as Reels / Posts") — har minute worker chalao, jobs khatam
// hote hi ruk jaata hai. Lambe reel jobs ke liye overlap lock 60 min.
Schedule::command('queue:work --stop-when-empty --tries=1 --timeout=900 --max-time=3300')
    ->everyMinute()->withoutOverlapping(60)->runInBackground();

// Har 6 ghante Instagram / YouTube se posts ke views, likes, comments laao (Analytics page)
Schedule::command('analytics:fetch')->everySixHours()->withoutOverlapping(60)->runInBackground();

// Roz raat purani reels / voice cache / temp files hatao (disk na bhare).
// Card images aur music kabhi nahi hatte — dekho PruneMedia command.
Schedule::command('media:prune')->dailyAt('03:30')->withoutOverlapping(30);

// Followers ki ginti har ghante — Dashboard ka "aaj kitne follow aaye"
Schedule::command('followers:fetch')->hourly()->withoutOverlapping(30)->runInBackground();

// Scheduler ki dhadkan — YouTube page ka "Auto-post status" isse batata hai ki server ka cron chal raha hai ya nahi
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever('scheduler:last_run', now()->toIso8601String()))
    ->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping(2);
