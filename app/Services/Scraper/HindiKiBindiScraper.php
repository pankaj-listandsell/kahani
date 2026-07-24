<?php

namespace App\Services\Scraper;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * hindikibindi.com (विद्यार्थी → कहानियां) ke liye scraper.
 *
 * Page structure (verified):
 *   section .col-lg-12 > p  ke andar poori kahani hai —
 *     - pehli line (pehle <br><br> se pehle) = story ka TITLE
 *     - baaki <br><br> se alag paragraphs = body (ant me "शिक्षा -..." moral)
 *     - <p> ke ant me Previous/Next nav <div> hote hain — unhe hata dete hain
 *   Index page par links: index.php?artifact=N
 */
class HindiKiBindiScraper implements SourceScraper
{
    private const HOST = 'hindikibindi.com';

    public function supports(string $url): bool
    {
        return str_contains(strtolower((string) parse_url($url, PHP_URL_HOST)), self::HOST);
    }

    public function isStoryUrl(string $url): bool
    {
        // Single story = artifact=N; iske bina URL ek index/listing page hai
        return (bool) preg_match('/artifact=\d+/', $url);
    }

    public function listStories(string $indexUrl): array
    {
        $html  = $this->fetch($indexUrl);
        $doc   = $this->loadDom($html);
        $xpath = new \DOMXPath($doc);

        $urls = [];
        foreach ($xpath->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            // Sirf story links: artifact=N (index/nav/footer links chhod do)
            if (preg_match('/artifact=\d+/', $href)) {
                $urls[$this->absolute($href, $indexUrl)] = true; // key se dedup
            }
        }

        return array_keys($urls);
    }

    public function scrape(string $url): ScrapedStory
    {
        $html = $this->fetch($url);
        $doc  = $this->loadDom($html);
        $xpath = new \DOMXPath($doc);

        // Content wala <p> — section ke andar .col-lg-12 ka pehla <p>
        $p = $xpath->query("//section//div[contains(@class,'col-lg-12')]/p")->item(0)
            ?? $xpath->query("//div[contains(@class,'col-lg-12')]/p")->item(0);

        if (! $p) {
            throw new \RuntimeException("Story content nahi mila is page par: {$url}");
        }

        // <p> ke children ghumao: <br> = newline, text = text.
        // Jaise hi koi <div> (Previous/Next nav) aaye — ruk jao.
        $buf = '';
        foreach ($p->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'div') {
                break;
            }
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'br') {
                $buf .= "\n";
                continue;
            }
            $buf .= $child->textContent;
        }

        [$title, $body] = $this->splitTitleBody($buf);

        return new ScrapedStory(
            title: $title,
            body: $body,
            sourceUrl: $url,
            language: 'hindi',
        );
    }

    /**
     * Raw text ko normalize karke [title, body] me todo.
     * Pehla paragraph = title; baaki = body.
     */
    private function splitTitleBody(string $raw): array
    {
        // BOM / zero-width chars hatao
        $text = str_replace(["\u{FEFF}", "\u{200B}", "\u{200C}", "\u{200D}"], '', $raw);
        // \r hatao, 3+ newlines ko 2 me collapse
        $text = str_replace("\r", '', $text);
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));

        // Paragraphs = 2+ newlines se alag
        $paras = array_values(array_filter(
            array_map('trim', preg_split('/\n{2,}/', $text)),
            fn ($p) => $p !== '',
        ));

        if (empty($paras)) {
            return ['', ''];
        }

        $title = Str::limit(array_shift($paras), 250, '');
        $body  = implode("\n\n", $paras);

        return [$title, $body];
    }

    /** URL fetch karo (UTF-8 HTML). */
    private function fetch(string $url): string
    {
        $res = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KahaniImporter/1.0)'])
            ->timeout(30)
            ->retry(2, 500)
            ->get($url);

        if (! $res->successful()) {
            throw new \RuntimeException("Fetch fail (HTTP {$res->status()}): {$url}");
        }

        return $res->body();
    }

    /** UTF-8 safe DOMDocument load. */
    private function loadDom(string $html): \DOMDocument
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        // encoding hint taaki Devanagari sahi decode ho
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        return $doc;
    }

    /** Relative href ko absolute URL me badlo. */
    private function absolute(string $href, string $baseUrl): string
    {
        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $host   = parse_url($baseUrl, PHP_URL_HOST);
        $path   = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
        $dir    = rtrim(str_replace('\\', '/', dirname($path)), '/');

        // "index.php?artifact=4" jaisa relative — base directory ke saath jodo
        return "{$scheme}://{$host}{$dir}/" . ltrim($href, '/');
    }
}
