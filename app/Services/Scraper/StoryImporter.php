<?php

namespace App\Services\Scraper;

use App\Models\Story;

/**
 * Kisi bhi source se kahaniyan import karke Story + Part (draft) banata hai.
 * Command (story:import) aur admin "Import from URL" button — dono isi ko use
 * karte hain, taaki logic ek hi jagah rahe.
 */
class StoryImporter
{
    /** @var list<class-string<SourceScraper>> */
    private const SCRAPERS = [
        HindiKiBindiScraper::class,
    ];

    /** URL ke liye pehla matching scraper (ya null). */
    public function scraperFor(string $url): ?SourceScraper
    {
        foreach (self::SCRAPERS as $class) {
            /** @var SourceScraper $s */
            $s = app($class);
            if ($s->supports($url)) {
                return $s;
            }
        }

        return null;
    }

    /**
     * Ek ya (index se) kai kahaniyan import karo.
     *
     * @return array{imported:int, skipped:int, failed:int, titles:list<string>, errors:list<string>}
     */
    public function import(string $url, int $userId, string $language = 'hindi', bool $all = false, int $limit = 0): array
    {
        $scraper = $this->scraperFor($url);
        if (! $scraper) {
            return $this->result(errors: ["Is URL ke liye koi scraper nahi hai: {$url}"]);
        }

        // Index/listing URL (koi single story nahi) ho to apne-aap saari import
        // karo — user ko "all" check karne ki zaroorat nahi.
        $treatAsIndex = $all || ! $scraper->isStoryUrl($url);

        $urls = $treatAsIndex ? $scraper->listStories($url) : [$url];
        if ($limit > 0) {
            $urls = array_slice($urls, 0, $limit);
        }

        if (empty($urls)) {
            return $this->result(errors: ['Is page par koi story link/content nahi mila.']);
        }

        $imported = 0;
        $skipped  = 0;
        $failed   = 0;
        $titles   = [];
        $errors   = [];

        foreach ($urls as $storyUrl) {
            if (Story::where('source_url', $storyUrl)->exists()) {
                $skipped++;
                continue;
            }

            try {
                $data = $scraper->scrape($storyUrl);
                $data->language = $language;

                if (! $data->isValid()) {
                    $failed++;
                    $errors[] = "Khaali content: {$storyUrl}";
                    continue;
                }

                $this->save($data, $userId);
                $titles[] = $data->title;
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "{$storyUrl} — {$e->getMessage()}";
            }
        }

        return $this->result($imported, $skipped, $failed, $titles, $errors);
    }

    /** ScrapedStory → Story + Part (draft). */
    private function save(ScrapedStory $data, int $userId): Story
    {
        $story = Story::create([
            'user_id'    => $userId,
            'title'      => $data->title,
            'source_url' => $data->sourceUrl,
            'type'       => 'story',
            'language'   => $data->language,
            'status'     => 'draft',
        ]);

        $story->parts()->create([
            'sort_order' => 1,
            'body'       => $data->body,
        ]);

        return $story;
    }

    /** @return array{imported:int, skipped:int, failed:int, titles:list<string>, errors:list<string>} */
    private function result(int $imported = 0, int $skipped = 0, int $failed = 0, array $titles = [], array $errors = []): array
    {
        return compact('imported', 'skipped', 'failed', 'titles', 'errors');
    }
}
