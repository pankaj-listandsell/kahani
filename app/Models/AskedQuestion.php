<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Ek baar post ho chuke quiz sawaalon ka permanent record.
 *
 * Quiz collection delete ho jaye tab bhi ye rows rehti hain — isi se pata
 * chalta hai ki kaunsa sawaal pehle aa chuka hai aur dobara nahi aana chahiye.
 */
class AskedQuestion extends Model
{
    protected $fillable = ['user_id', 'topic', 'language', 'question', 'hash'];

    /**
     * Sawaal ko match karne layak banao — emoji, punctuation, extra space aur
     * chhota-bada farak hata do. "ગુજરાતની રાજધાની કઈ છે? 🗺️" aur
     * "ગુજરાતની રાજધાની કઈ છે." dono ek hi hash denge.
     */
    public static function normalize(string $question): string
    {
        // Emoji / pictographs
        $q = preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}'
            . '\x{FE00}-\x{FE0F}\x{200D}\x{20E3}\x{1F1E6}-\x{1F1FF}]/u',
            '',
            $question
        ) ?? $question;

        // Punctuation (Devanagari danda samet) aur symbols
        $q = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q) ?? $q;
        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;

        return Str::lower(trim($q));
    }

    public static function hashFor(string $question): string
    {
        return sha1(self::normalize($question));
    }

    /** Topic ko match karne layak banao (case/space ka farak na pade). */
    public static function topicKey(string $topic): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', $topic) ?? $topic));
    }

    /**
     * Is topic par pehle poochhe gaye sawaal (naye pehle) — AI ko "ye mat dohrao"
     * bhejne ke liye.
     *
     * @return list<string>
     */
    public static function recentFor(?int $userId, string $topic, int $limit = 200): array
    {
        $q = static::query()->where('user_id', $userId)->latest('id')->limit($limit);

        if (trim($topic) !== '') {
            $q->where('topic', self::topicKey($topic));
        }

        return $q->pluck('question')->all();
    }

    /**
     * Diye gaye items me se sirf wahi lauta jo pehle kabhi nahi aaye.
     * Topic chahe koi bhi ho — ek hi sawaal dobara post nahi hona chahiye.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function filterNew(?int $userId, array $items): array
    {
        $hashes = [];
        foreach ($items as $item) {
            $q = (string) ($item['question'] ?? '');
            if ($q !== '') {
                $hashes[] = self::hashFor($q);
            }
        }

        if (empty($hashes)) {
            return $items;
        }

        $seen = static::query()
            ->where('user_id', $userId)
            ->whereIn('hash', array_unique($hashes))
            ->pluck('hash')
            ->flip();

        $out  = [];
        $dupe = [];  // ek hi batch me bhi AI kabhi do baar wahi sawaal de deta hai

        foreach ($items as $item) {
            $q = (string) ($item['question'] ?? '');
            if ($q === '') {
                continue;
            }
            $h = self::hashFor($q);
            if (isset($seen[$h]) || isset($dupe[$h])) {
                continue;
            }
            $dupe[$h] = true;
            $out[]    = $item;
        }

        return $out;
    }

    /** Post ho chuke sawaal ko record karo (pehle se ho to kuch mat karo). */
    public static function remember(?int $userId, string $topic, string $language, string $question): void
    {
        $question = trim($question);
        if ($question === '') {
            return;
        }

        static::query()->updateOrCreate(
            ['user_id' => $userId, 'hash' => self::hashFor($question)],
            [
                'topic'    => self::topicKey($topic),
                'language' => $language,
                'question' => $question,
            ],
        );
    }
}
