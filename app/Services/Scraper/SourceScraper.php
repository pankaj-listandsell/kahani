<?php

namespace App\Services\Scraper;

/**
 * Kisi bhi kahani-source website ke liye common interface.
 *
 *   supports() → ye scraper is URL ko handle kar sakta hai?
 *   listStories() → index/listing page se saare story URLs
 *   scrape() → ek story page ka normalized ScrapedStory
 *
 * Har naye source ke liye ek naya class banao jo ye interface implement kare.
 */
interface SourceScraper
{
    /** Kya ye scraper is URL (domain/path) ko handle karta hai? */
    public function supports(string $url): bool;

    /** Kya ye URL ek single story page hai (index/listing nahi)? */
    public function isStoryUrl(string $url): bool;

    /**
     * Index/listing page se saari story URLs (absolute) nikaalo.
     *
     * @return list<string>
     */
    public function listStories(string $indexUrl): array;

    /** Ek story page ko fetch + parse + normalize karke ScrapedStory do. */
    public function scrape(string $url): ScrapedStory;
}
