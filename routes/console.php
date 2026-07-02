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
Schedule::command('instagram:auto-post')->everyMinute()->withoutOverlapping();
