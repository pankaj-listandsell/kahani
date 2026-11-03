<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\FollowerTracker;
use Illuminate\Console\Command;

/**
 * Har user ke Instagram followers / YouTube subscribers / Facebook page followers ki
 * ginti laakar save karo — Dashboard ka "aaj kitne follow aaye" isi se banta hai.
 */
class FetchFollowers extends Command
{
    protected $signature = 'followers:fetch {--user= : Sirf is user id ke liye}';

    protected $description = 'Instagram / YouTube / Facebook ke followers ki ginti save karo (roz ka badhaav)';

    public function handle(FollowerTracker $tracker): int
    {
        $ids = Setting::whereIn('key', ['ig_access_token', 'yt_refresh_token', 'fb_page_token'])
            ->whereNotNull('value')->where('value', '!=', '')
            ->pluck('user_id')->unique()->values();

        if ($only = $this->option('user')) {
            $ids = $ids->filter(fn ($id) => (int) $id === (int) $only);
        }

        foreach ($ids as $uid) {
            $counts = $tracker->fetch((int) $uid);
            $this->line("user {$uid}: " . collect($counts)->map(fn ($v, $k) => $k . '=' . ($v ?? '-'))->implode(' '));
        }

        return self::SUCCESS;
    }
}
