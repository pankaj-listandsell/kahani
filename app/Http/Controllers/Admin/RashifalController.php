<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\Story;
use App\Services\InstagramService;
use App\Services\ViralStudioAiService;
use App\Services\YoutubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 🔯 Aaj nu Rashifal — ek din ke 12 rashi cards. Story.category me din ('Y-m-d') rehta hai;
 * auto-post (Story::nextRashifalCard) inhe sirf usi din, Mesh→Meen kram me post karta hai.
 */
class RashifalController extends Controller
{
    public function index()
    {
        $query = Story::withCount('parts')->whereIn('type', ['rashifal', 'rashifal_short'])->orderByDesc('category')->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view('admin.rashifal.index', ['collections' => $query->get()]);
    }

    public function show(Story $story)
    {
        abort_unless(in_array($story->type, ['rashifal', 'rashifal_short'], true), 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        return view('admin.rashifal.show', compact('story', 'cards'));
    }

    public function destroy(Story $story, InstagramService $instagram)
    {
        abort_unless(in_array($story->type, ['rashifal', 'rashifal_short'], true), 404);
        $this->authorize('delete', $story);

        $story->load('parts.cards');
        foreach ($story->parts as $part) {
            if ($part->image_path) {
                Storage::disk('public')->delete($part->image_path);
            }
            foreach ($part->cards as $card) {
                $instagram->deleteMediaFiles($card);
            }
            Storage::disk('public')->delete('yt/part-' . $part->id . '.mp4');
        }

        $story->delete();

        return redirect()->route('admin.rashifal.index')->with('success', 'Rashifal delete ho gaya.');
    }

    public function generate(Request $request, ViralStudioAiService $ai)
    {
        $data = $request->validate([
            'date'          => ['required', 'date_format:Y-m-d'],
            'language'      => ['required', 'in:gujarati,hindi,hinglish'],
            'skip_existing' => ['nullable', 'boolean'],
        ]);

        try {
            $exists = Story::where('type', 'rashifal')
                ->where('user_id', auth()->id())
                ->where('category', $data['date'])
                ->exists();

            // "7 din" button: jo din pehle se save hai uska AI call bachao
            if ($exists && ($data['skip_existing'] ?? false)) {
                return response()->json(['ok' => true, 'skipped' => true, 'items' => [], 'exists' => true]);
            }

            $items = $ai->generateRashifal($data['date'], $data['language']);

            return response()->json(['ok' => true, 'items' => $items, 'exists' => $exists]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'date'            => ['required', 'date_format:Y-m-d'],
            'language'        => ['nullable', 'in:hindi,gujarati,hinglish'],
            'cards'           => ['required', 'array', 'min:1', 'max:13'],
            'cards.*.order'   => ['required', 'integer', 'min:1'],
            'cards.*.text'    => ['required', 'string'],
            'cards.*.caption' => ['nullable', 'string', 'max:2200'], // Instagram caption limit
            'cards.*.hashtags'=> ['nullable', 'string', 'max:2000'],
            'cards.*.image'   => ['required', 'string'],
        ]);

        try {
            $cardsInput = $data['cards'];

            $story = Story::create([
                'user_id'  => auth()->id(),
                'title'    => 'Rashifal — ' . Carbon::parse($data['date'])->format('d M Y'),
                'type'     => 'rashifal',
                'category' => $data['date'],
                // YouTube ke liye alag Shorts (rashifal_short) bante hain — ye 12 cards sirf IG + FB
                'platforms' => ['instagram', 'facebook'],
                'language' => $data['language'] ?? 'gujarati',
                'status'   => 'published',
                'tts_mode' => 'music', // naye cards: Reel Audio pehle se "Music only" (page par badal sakte ho)
            ]);
            $part = $story->parts()->create(['sort_order' => 1, 'body' => $cardsInput[0]['text'] ?? 'Rashifal']);

            foreach ($cardsInput as $cardItem) {
                $decoded = $this->decodeDataUrl($cardItem['image']);
                if (! $decoded) continue;

                $path = 'cards/' . Str::uuid() . '.' . $decoded['ext'];
                Storage::disk('public')->put($path, $decoded['binary']);

                $tags = trim((string) ($cardItem['hashtags'] ?? ''));
                $caption = trim(($cardItem['caption'] ?? '') . ($tags !== '' ? "\n\n" . $tags : ''));

                $part->cards()->create([
                    'sort_order' => $cardItem['order'],
                    'image_path' => $path,
                    'text'       => $cardItem['text'],
                    'ig_caption' => $caption !== '' ? $caption : null,
                ]);
            }

            return response()->json([
                'ok'         => true,
                'collection' => $story->id,
                'redirect'   => route('admin.rashifal.show', $story),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 📺 YouTube-only Rashifal Shorts — ek din ke 2 parts: 'all' (12 rashi) aur 'top3'.
     * Har part ke frames (intro + rashi + Like/Share/Subscribe) ek Short bante hain.
     * Us din ke purane Shorts (jo abhi upload nahi hue) badal diye jaate hain.
     */
    public function saveShort(Request $request, InstagramService $instagram)
    {
        $data = $request->validate([
            'date'                  => ['required', 'date_format:Y-m-d'],
            'language'              => ['nullable', 'in:hindi,gujarati,hinglish'],
            'parts'                 => ['required', 'array', 'min:1', 'max:2'],
            'parts.*.kind'          => ['required', 'in:all,top3'],
            'parts.*.caption'       => ['nullable', 'string', 'max:5000'],
            'parts.*.frames'        => ['required', 'array', 'min:2', 'max:16'],
            'parts.*.frames.*.text' => ['required', 'string'],
            'parts.*.frames.*.image'=> ['required', 'string'],
        ]);

        try {
            $old = Story::where('type', 'rashifal_short')->where('user_id', auth()->id())
                ->where('category', $data['date'])->with('parts.cards')->get();
            foreach ($old as $s) {
                if ($s->parts->flatMap->cards->contains(fn ($c) => $c->yt_status === 'posted')) {
                    return response()->json(['ok' => true, 'skipped' => true]); // pehle hi upload ho chuka — mat chhedo
                }
                foreach ($s->parts as $part) {
                    foreach ($part->cards as $card) {
                        $instagram->deleteMediaFiles($card);
                    }
                    Storage::disk('public')->delete('yt/part-' . $part->id . '.mp4');
                }
                $s->delete();
            }

            $story = Story::create([
                'user_id'   => auth()->id(),
                'title'     => 'Rashifal Shorts — ' . Carbon::parse($data['date'])->format('d M Y'),
                'type'      => 'rashifal_short',
                'category'  => $data['date'],
                'language'  => $data['language'] ?? 'gujarati',
                'status'    => 'published',
                'platforms' => ['youtube'],
                'tts_mode'  => 'music',
            ]);

            foreach (array_values($data['parts']) as $pi => $p) {
                $part = $story->parts()->create(['sort_order' => $pi + 1, 'body' => 'short:' . $p['kind']]);
                foreach (array_values($p['frames']) as $i => $frame) {
                    $decoded = $this->decodeDataUrl($frame['image']);
                    if (! $decoded) continue;

                    $path = 'cards/' . Str::uuid() . '.' . $decoded['ext'];
                    Storage::disk('public')->put($path, $decoded['binary']);
                    $part->cards()->create([
                        'sort_order' => $i + 1,
                        'image_path' => $path,
                        'text'       => $frame['text'],
                        // Pehle frame par YouTube title + description (buildTitle/buildDescription isi se)
                        'yt_caption' => $i === 0 ? ($p['caption'] ?? null) : null,
                    ]);
                }
            }

            return response()->json(['ok' => true, 'story' => $story->id]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Short ka video banake dikhao (upload nahi) — check karne ke liye. */
    public function previewShort(Part $part, YoutubeService $youtube)
    {
        abort_unless($part->story?->type === 'rashifal_short', 404);
        $this->authorize('update', $part->story);
        @set_time_limit(600);

        try {
            $part->load(['story', 'cards' => fn ($q) => $q->orderBy('sort_order')]);
            $mp4 = $youtube->forUser($part->story->user_id)->mp4ForPart($part);

            return response()->json(['ok' => true, 'url' => asset('storage/' . $mp4) . '?v=' . time()]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Short abhi YouTube par upload karo (auto-post ka intezaar kiye bina). */
    public function uploadShort(Part $part, YoutubeService $youtube)
    {
        abort_unless($part->story?->type === 'rashifal_short', 404);
        $this->authorize('update', $part->story);
        @set_time_limit(900);

        try {
            $id = $youtube->postPart($part);

            return back()->with('success', "▶️ Short YouTube par upload ho gaya (video id: {$id}).");
        } catch (\Throwable $e) {
            return back()->with('error', 'Upload nahi hua: ' . $e->getMessage());
        }
    }

    protected function decodeDataUrl(string $dataUrl): ?array
    {
        if (preg_match('/^data:image\/(png|webp|jpeg|jpg);base64,(.+)$/s', trim($dataUrl), $m)) {
            $binary = base64_decode(str_replace(' ', '+', $m[2]), true);
            if ($binary === false) return null;
            $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            return ['ext' => $ext, 'binary' => $binary];
        }
        return null;
    }
}
