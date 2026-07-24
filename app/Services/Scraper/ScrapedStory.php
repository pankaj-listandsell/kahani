<?php

namespace App\Services\Scraper;

/**
 * Ek scrape ki gayi kahani ka normalized data — kisi bhi source scraper ka
 * output isi shape me aata hai, taaki ImportStories command sabhi sources ko
 * ek jaisa handle kar sake.
 */
class ScrapedStory
{
    public function __construct(
        public string $title,
        public string $body,
        public string $sourceUrl,
        public ?string $description = null,
        public string $language = 'hindi',
    ) {
    }

    /** Title + body dono me kuch valid content hai? */
    public function isValid(): bool
    {
        return trim($this->title) !== '' && trim($this->body) !== '';
    }
}
