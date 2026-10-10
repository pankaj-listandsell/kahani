<?php

namespace App\Console\Commands\Concerns;

use App\Models\PartCard;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Auto-post commands (ig / yt / fb) ka failure handling.
 *
 * Pehle: koi bhi error aaye (token expire, quota khatam…) to slot khula rehta tha,
 * aur scheduler har minute AGLA card utha kar use bhi 'failed' kar deta tha —
 * ek window me dozens cards bekaar ho jaate the.
 *
 * Ab:
 *  - fail hone par bhi slot "use" ho jaata hai → agla try agle slot par,
 *  - account-level error (token/quota/permission/host) → card wapas pending,
 *    aur user ke liye auto-post 1 ghanta ruk jaata hai,
 *  - lagaatar N card-level failures → 2 ghante ka cooldown.
 *
 * $prefix = 'ig' | 'yt' | 'fb' — settings keys aur PartCard status column dono isi se.
 */
trait HandlesAutoPostFailures
{
    protected int $maxConsecutiveFailures = 3;

    /** Cooldown chal raha ho to uska end-time, warna null. */
    protected function cooldownUntil(int $uid, string $prefix, Carbon $now): ?string
    {
        $until = Setting::getFor($uid, "{$prefix}_auto_cooldown_until");

        return $until && Carbon::parse($until)->greaterThan($now) ? $until : null;
    }

    protected function recordSuccess(int $uid, string $prefix, Carbon $now): void
    {
        Setting::putFor($uid, "{$prefix}_auto_last_post_at", $now->toIso8601String());

        if ((int) Setting::getFor($uid, "{$prefix}_auto_fail_count", '0') !== 0) {
            Setting::putFor($uid, "{$prefix}_auto_fail_count", '0');
        }
    }

    protected function recordFailure(int $uid, string $prefix, PartCard $card, \Throwable $e, Carbon $now): void
    {
        // Ye slot use ho gaya — har minute naya card try (aur fail) nahi hoga
        Setting::putFor($uid, "{$prefix}_auto_last_post_at", $now->toIso8601String());

        if ($this->isAccountError($e)) {
            // Card ki galti nahi — wapas pending, account theek hone par post hoga
            $card->update(["{$prefix}_status" => null]);
            $this->startCooldown($uid, $prefix, $now->copy()->addHour(), 'account/quota error: ' . $e->getMessage());
            Setting::putFor($uid, "{$prefix}_auto_fail_count", '0');

            return;
        }

        $fails = (int) Setting::getFor($uid, "{$prefix}_auto_fail_count", '0') + 1;

        if ($fails >= $this->maxConsecutiveFailures) {
            $this->startCooldown($uid, $prefix, $now->copy()->addHours(2), "{$fails} lagaatar failures");
            $fails = 0;
        }

        Setting::putFor($uid, "{$prefix}_auto_fail_count", (string) $fails);
    }

    protected function startCooldown(int $uid, string $prefix, Carbon $until, string $reason): void
    {
        Setting::putFor($uid, "{$prefix}_auto_cooldown_until", $until->toIso8601String());
        $this->warn("User #{$uid}: {$prefix} auto-post {$until->format('d M H:i')} tak ruka — {$reason}");
    }

    /** Token / quota / permission / hosting jaisi problem — card nahi, account ya setup kharab hai. */
    protected function isAccountError(\Throwable $e): bool
    {
        return (bool) preg_match(
            '/access.?token|oauth|session has expired|invalid_grant|unauthori[sz]ed|quota|rate.?limit|request limit|'
            . 'permission|not configured|configured nahi|public host|connection refused|could not resolve host|timed out/i',
            $e->getMessage()
        );
    }
}
