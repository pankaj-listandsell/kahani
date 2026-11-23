<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\WordFilter;
use Illuminate\Http\Request;

/**
 * 🚫 Stories → Shabd List: ashleel shabd ki list dekho / badlo / test karo.
 * List sab users ke liye ek hi hai — isliye badalna sirf admin kar sakta hai.
 */
class WordFilterController extends Controller
{
    public function index()
    {
        return view('admin.stories.words', [
            'words'   => WordFilter::words(),
            'learned' => WordFilter::learned(),
            'aiOn'    => (bool) config('word_filter.ai'),
            'canEdit' => auth()->user()->isAdmin(),
        ]);
    }

    /** list = 'words' (hamari) ya 'learned' (AI wali) — form ki saari rows se poori list dobara likho. */
    public function save(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'list'   => ['required', 'in:words,learned'],
            'bad'    => ['nullable', 'array'],
            'bad.*'  => ['nullable', 'string', 'max:60'],
            'good'   => ['nullable', 'array'],
            'good.*' => ['nullable', 'string', 'max:120'],
        ]);

        $map = [];
        foreach (($data['bad'] ?? []) as $i => $bad) {
            $map[(string) $bad] = (string) ($data['good'][$i] ?? '');
        }

        $data['list'] === 'words' ? WordFilter::saveWords($map) : WordFilter::saveLearned($map);

        return back()->with('success', '🚫 Shabd list save ho gayi.');
    }

    /** config/word_filter.php ki moti (default) list tamari list me jodo — pehle wale shabd jaise hain waise rehte hain. */
    public function importDefaults()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $current = WordFilter::words();
        $defaults = (array) config('word_filter.words', []);
        $new = array_diff_key($defaults, $current);
        WordFilter::saveWords($current + $defaults);

        return back()->with('success', '⬇ ' . count($new) . ' nava shabd tamari list ma umerya.');
    }

    /** AI ka dhoondha shabd "Tamari list" me le jao (wahan se hamesha upar maana jaata hai). */
    public function promote(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        // 'word' (nahi 'bad') — button AI list ke form ke andar hai jahan bad[] pehle se jaata hai
        $bad = (string) $request->validate(['word' => ['required', 'string', 'max:60']])['word'];

        $learned = WordFilter::learned();
        if (isset($learned[$bad])) {
            WordFilter::saveWords(WordFilter::words() + [$bad => $learned[$bad]]);
            unset($learned[$bad]);
            WordFilter::saveLearned($learned);
        }

        return back()->with('success', "'{$bad}' tamari list ma aavi gayo.");
    }

    /** Text daalo → badla hua text dikhao (AI ke saath ya bina). */
    public function test(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'ai'   => ['nullable', 'boolean'],
        ]);

        $withAi = ($data['ai'] ?? false) && config('word_filter.ai');
        $out = $withAi ? WordFilter::clean($data['text']) : WordFilter::cleanLocal($data['text']);

        return back()->withInput()->with('word_test', $out)
            ->with('success', $withAi ? WordFilter::lastSummary() : '🚫 Sirf list se badla (AI ke liye checkbox tick karo).');
    }
}
