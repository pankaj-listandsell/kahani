<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Purani, dobara banne layak media files hatao taaki server ki disk na bhare.
 *
 * Sirf ye folders (sab regenerate ho jaate hain ya kahin use nahi hote):
 *   reels/, yt/   — reel/short MP4 (post karte waqt zaroorat ho to phir ban jaati hai)
 *   tts/tmp/      — TTS ke adhoore chunks
 *   tts/*.wav     — voice cache (hatane par dobara banegi → Gemini quota lagega, isliye lamba time)
 *   temp/, videos/ — purane code ki bachi files, ab koi use nahi karta
 *
 * cards/, audio/, bg/, covers/, yoga/ ko KABHI nahi chhoota — wo asli content hai.
 */
class PruneMedia extends Command
{
    protected $signature = 'media:prune
        {--dry-run : Sirf batao kya hatega, delete mat karo}
        {--reels-days=3 : Itne din se purani reel/short MP4 hatao}
        {--tts-days=30 : Itne din se purani voice (TTS) cache hatao}';

    protected $description = 'Purani reels, voice cache aur temp files hatao (disk khaali karne ke liye)';

    public function handle(): int
    {
        $now = time();
        $day = 86400;
        $reelsAge = max(1, (int) $this->option('reels-days')) * $day;

        $rules = [
            // [folder, recursive?, max age (seconds), extension filter|null]
            ['reels', false, $reelsAge, 'mp4'],
            ['yt', false, $reelsAge, 'mp4'],
            ['tts/tmp', false, 3600, null],
            ['tts', false, max(1, (int) $this->option('tts-days')) * $day, 'wav'],
            ['temp', true, $day, null],
            ['videos', true, $day, null],
        ];

        $disk = Storage::disk('public');
        $dry = (bool) $this->option('dry-run');
        $count = 0;
        $bytes = 0;

        foreach ($rules as [$dir, $recursive, $maxAge, $ext]) {
            if (! $disk->exists($dir)) {
                continue;
            }

            $files = $recursive ? $disk->allFiles($dir) : $disk->files($dir);

            foreach ($files as $file) {
                if (str_ends_with($file, '.gitignore')) {
                    continue;
                }
                if ($ext !== null && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== $ext) {
                    continue;
                }
                if ($now - $disk->lastModified($file) < $maxAge) {
                    continue;
                }

                $bytes += $disk->size($file);
                $count++;
                if (! $dry) {
                    $disk->delete($file);
                }
            }
        }

        $mb = round($bytes / 1048576, 1);
        $this->info(($dry ? '[dry-run] ' : '') . "{$count} file(s), {$mb} MB " . ($dry ? 'hategi' : 'hata di'));

        return self::SUCCESS;
    }
}
