<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Scraper\StoryImporter;
use Illuminate\Console\Command;

/**
 * Kisi dusri website se kahaniyan import karo (Story + Part draft).
 *
 *   php artisan story:import "https://.../index.php?artifact=4"      # ek kahani
 *   php artisan story:import "https://.../stories/index.php" --all   # poora index
 *   php artisan story:import "https://.../stories/index.php" --all --limit=10
 *
 * Import hamesha DRAFT me jaati hai — aap review karke publish karein.
 * Duplicate (same source_url) apne aap skip ho jaate hain.
 */
class ImportStories extends Command
{
    protected $signature = 'story:import
        {url : Story page URL ya (--all ke saath) index/listing URL}
        {--all : URL ko index maan kar us par ki saari kahaniyan import karo}
        {--limit=0 : --all ke saath kitni max kahaniyan (0 = sabhi)}
        {--language=hindi : hindi|gujarati|hinglish}
        {--user= : Kis user ki stories banein (default: pehla admin)}';

    protected $description = 'Dusri website se kahaniyan import karke Story+Part (draft) banao';

    public function handle(StoryImporter $importer): int
    {
        $url = $this->argument('url');

        if (! $importer->scraperFor($url)) {
            $this->error("Is URL ke liye koi scraper nahi hai: {$url}");
            $this->line('Abhi support: hindikibindi.com');

            return self::FAILURE;
        }

        $userId = $this->resolveUserId();
        if (! $userId) {
            $this->error('Koi user nahi mila — pehle ek user banao (ya --user=ID do).');

            return self::FAILURE;
        }

        $this->info('Import ho rahi hai…');

        $r = $importer->import(
            url: $url,
            userId: $userId,
            language: $this->option('language'),
            all: (bool) $this->option('all'),
            limit: (int) $this->option('limit'),
        );

        foreach ($r['titles'] as $t) {
            $this->line("  ✓ {$t}");
        }
        foreach ($r['errors'] as $e) {
            $this->warn("  ✗ {$e}");
        }

        $this->newLine();
        $this->info("Done. Imported: {$r['imported']}, Skipped: {$r['skipped']}, Failed: {$r['failed']}");
        $this->line('Sab draft me hain — admin me review karke publish karein.');

        return self::SUCCESS;
    }

    /** --user option, warna pehla admin, warna pehla user. */
    private function resolveUserId(): ?int
    {
        if ($id = $this->option('user')) {
            return User::whereKey($id)->value('id');
        }

        return User::where('role', 'admin')->value('id')
            ?? User::query()->value('id');
    }
}
