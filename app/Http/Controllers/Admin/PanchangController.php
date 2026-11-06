<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\InstagramService;
use App\Services\PanchangService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 📅 Aaj nu Panchang — ek din ke 2 cards (Panchang + Choghadiya).
 * Story.category me din ('Y-m-d'); auto-post (Story::nextRashifalCard) inhe sirf
 * usi din, rashifal se bhi pehle post karta hai.
 */
class PanchangController extends Controller
{
    public function index()
    {
        $query = Story::withCount('parts')->where('type', 'panchang')->orderByDesc('category')->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view('admin.panchang.index', [
            'collections' => $query->get(),
            'cities'      => PanchangService::CITIES,
        ]);
    }

    /** Sunrise/sunset, choghadiya, rahu kaal, abhijit — ganit se. */
    public function compute(Request $request, PanchangService $panchang)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'city'     => ['nullable', 'in:' . implode(',', array_keys(PanchangService::CITIES))],
            'language' => ['nullable', 'in:gujarati,hindi'],
        ]);
        $city = $data['city'] ?? 'ahmedabad';

        return response()->json(['ok' => true] + $panchang->compute($data['date'], $city) + [
            // 🔮 Tithi, nakshatra, yog, karan, chandra rashi, maas, samvat — ganit se
            'astro' => $panchang->astro($data['date'], $city, $data['language'] ?? 'gujarati'),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'date'            => ['required', 'date_format:Y-m-d'],
            'language'        => ['nullable', 'in:gujarati,hindi'],
            'cards'           => ['required', 'array', 'min:1', 'max:3'],
            'cards.*.order'   => ['required', 'integer', 'min:1'],
            'cards.*.text'    => ['required', 'string'],
            'cards.*.caption' => ['nullable', 'string', 'max:2200'],
            'cards.*.image'   => ['required', 'string'],
        ]);

        $story = Story::create([
            'user_id'  => auth()->id(),
            'title'    => 'Panchang — ' . Carbon::parse($data['date'])->format('d M Y'),
            'type'     => 'panchang',
            'category' => $data['date'],
            'language' => $data['language'] ?? 'gujarati',
            'status'   => 'published',
            'tts_mode' => 'music',
        ]);
        $part = $story->parts()->create(['sort_order' => 1, 'body' => $data['cards'][0]['text'] ?? 'Panchang']);

        foreach ($data['cards'] as $cardItem) {
            $decoded = $this->decodeDataUrl($cardItem['image']);
            if (! $decoded) {
                continue;
            }

            $path = 'cards/' . Str::uuid() . '.' . $decoded['ext'];
            Storage::disk('public')->put($path, $decoded['binary']);

            $part->cards()->create([
                'sort_order' => $cardItem['order'],
                'image_path' => $path,
                'text'       => $cardItem['text'],
                'ig_caption' => trim((string) ($cardItem['caption'] ?? '')) ?: null,
            ]);
        }

        return response()->json(['ok' => true, 'redirect' => route('admin.panchang.show', $story)]);
    }

    public function show(Story $story)
    {
        abort_unless($story->type === 'panchang', 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        return view('admin.panchang.show', compact('story', 'cards'));
    }

    public function destroy(Story $story, InstagramService $instagram)
    {
        abort_unless($story->type === 'panchang', 404);
        $this->authorize('delete', $story);

        $story->load('parts.cards');
        foreach ($story->parts as $part) {
            foreach ($part->cards as $card) {
                $instagram->deleteMediaFiles($card);
            }
        }
        $story->delete();

        return redirect()->route('admin.panchang.index')->with('success', 'Panchang delete ho gaya.');
    }

    protected function decodeDataUrl(string $dataUrl): ?array
    {
        if (! preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $dataUrl, $m)) {
            return null;
        }
        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        return $binary === false ? null : ['ext' => strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]), 'binary' => $binary];
    }
}
