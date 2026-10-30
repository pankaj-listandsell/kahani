<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\InstagramService;
use App\Services\ViralStudioAiService;
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
        $query = Story::withCount('parts')->where('type', 'rashifal')->orderByDesc('category')->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view('admin.rashifal.index', ['collections' => $query->get()]);
    }

    public function show(Story $story)
    {
        abort_unless($story->type === 'rashifal', 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        return view('admin.rashifal.show', compact('story', 'cards'));
    }

    public function destroy(Story $story, InstagramService $instagram)
    {
        abort_unless($story->type === 'rashifal', 404);
        $this->authorize('delete', $story);

        $story->load('parts.cards');
        foreach ($story->parts as $part) {
            if ($part->image_path) {
                Storage::disk('public')->delete($part->image_path);
            }
            foreach ($part->cards as $card) {
                $instagram->deleteMediaFiles($card);
            }
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
                'language' => $data['language'] ?? 'gujarati',
                'status'   => 'published',
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
