<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Story;
use App\Services\ImageService;
use App\Services\InstagramService;
use App\Services\PixabayService;
use App\Services\YogaAiService;
use App\Services\YogaPoseLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Kids Yoga studio — bachchon ke liye safe aasan ke cards.
 *
 * Flow: curated list se aasan chuno → AI steps/fayde likhta hai → har aasan ki
 * vector illustration banti hai (Pollinations, fail ho to local SVG) → browser
 * canvas 1080x1920 card banata hai → collection (Story type=yoga) save.
 * Aage ka reel + IG/YouTube/Facebook auto-post maujooda pipeline hi karta hai.
 */
class YogaController extends Controller
{
    /** Har caption ke saath jaane wali safety line. */
    private const DISCLAIMER = '⚠️ बच्चे यह योग बड़ों की देखरेख में ही करें।';

    public function __construct(private InstagramService $instagram)
    {
    }

    public function index()
    {
        $query = Story::withCount('parts')->where('type', 'yoga')->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view('admin.yoga.index', [
            'collections' => $query->get(),
            'poses'       => YogaPoseLibrary::all(),
        ]);
    }

    /** Chune hue aasan ke steps/fayde AI se. */
    public function generate(Request $request, YogaAiService $ai)
    {
        $data = $request->validate([
            'poses'    => ['required', 'array', 'min:1', 'max:20'],
            'poses.*'  => ['string', 'max:50'],
            'language' => ['nullable', 'in:hindi,gujarati,hinglish'],
        ]);

        try {
            $items = $ai->generatePoses($data['poses'], $data['language'] ?? 'hindi');

            // Jo aasan pehle se approve ho chuke hain unki image seedhi lag jaati
            // hai — na AI call, na intezaar.
            foreach ($items as &$item) {
                $item['approved'] = $this->approvedUrl($item['key']);
            }
            unset($item);

            return response()->json([
                'ok'      => true,
                'items'   => $items,
                'pixabay' => app(PixabayService::class)->isConfigured(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Ek aasan ki illustration banao. AI fail ho to local SVG ka path do —
     * card phir bhi ban jaata hai (`ok` true hi rehta hai, bas `fallback` flag).
     *
     * URL hamesha APNI site ka hota hai (storage ya /img) — cross-origin image
     * canvas ko taint kar deti hai aur `toDataURL()` fail ho jaata, yaani card
     * save hi nahi hota. Isliye image pehle server par save karte hain.
     */
    public function image(Request $request, ImageService $images)
    {
        $data = $request->validate([
            'pose' => ['required', 'string', 'max:50'],
        ]);

        if (YogaPoseLibrary::get($data['pose']) === null) {
            return response()->json(['ok' => false, 'error' => 'Aasan nahi mila'], 422);
        }

        try {
            $path = $images->generate(YogaPoseLibrary::imagePrompt($data['pose']), 768, 768, 'yoga');

            return response()->json([
                'ok'       => true,
                'url'      => asset('storage/' . $path),
                'path'     => $path,
                'fallback' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Yoga image fail, SVG fallback', ['pose' => $data['pose'], 'error' => $e->getMessage()]);

            return response()->json([
                'ok'       => true,
                'url'      => asset(ltrim(YogaPoseLibrary::fallbackSvg($data['pose']), '/')),
                'path'     => null,
                'fallback' => true,
            ]);
        }
    }

    /** Pixabay par free illustrations dhoondo (pose ka English naam default query). */
    public function search(Request $request, PixabayService $pixabay)
    {
        $data = $request->validate([
            'pose'  => ['required', 'string', 'max:50'],
            'query' => ['nullable', 'string', 'max:80'],
        ]);

        $pose = YogaPoseLibrary::get($data['pose']);
        if ($pose === null) {
            return response()->json(['ok' => false, 'error' => 'Aasan nahi mila'], 422);
        }

        $query = trim((string) ($data['query'] ?? '')) ?: ($pose['en'] . ' yoga kids cartoon');

        try {
            return response()->json([
                'ok'      => true,
                'query'   => $query,
                'results' => $pixabay->searchIllustrations($query),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Pixabay se chuni hui image apne server par le aao (canvas ke liye zaroori). */
    public function pick(Request $request, PixabayService $pixabay)
    {
        $data = $request->validate([
            'pose' => ['required', 'string', 'max:50'],
            'url'  => ['required', 'url', 'max:500'],
        ]);

        if (YogaPoseLibrary::get($data['pose']) === null) {
            return response()->json(['ok' => false, 'error' => 'Aasan nahi mila'], 422);
        }

        try {
            $path = $pixabay->download($data['url']);

            return response()->json(['ok' => true, 'url' => asset('storage/' . $path), 'path' => $path]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Apni image upload karo (koi bhi free-stock vector jo aapko pasand ho). */
    public function upload(Request $request)
    {
        $data = $request->validate([
            'pose'  => ['required', 'string', 'max:50'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (YogaPoseLibrary::get($data['pose']) === null) {
            return response()->json(['ok' => false, 'error' => 'Aasan nahi mila'], 422);
        }

        $path = $request->file('image')->store('yoga', 'public');

        return response()->json(['ok' => true, 'url' => asset('storage/' . $path), 'path' => $path]);
    }

    /**
     * Is image ko is aasan ki "official" image bana do — aage har collection me
     * yahi lagegi (na AI call, na intezaar, na galat pose).
     */
    public function approve(Request $request)
    {
        $data = $request->validate([
            'pose' => ['required', 'string', 'max:50'],
            'path' => ['nullable', 'string', 'max:255'],
        ]);

        if (YogaPoseLibrary::get($data['pose']) === null) {
            return response()->json(['ok' => false, 'error' => 'Aasan nahi mila'], 422);
        }

        // Khaali path = approval hata do
        if (blank($data['path'])) {
            Setting::removeFor(auth()->id(), self::approvalKey($data['pose']));

            return response()->json(['ok' => true, 'approved' => false]);
        }

        // Sirf apne yoga folder ki image approve ho sakti hai
        $path = ltrim($data['path'], '/');
        if (! Str::startsWith($path, 'yoga/') || ! Storage::disk('public')->exists($path)) {
            return response()->json(['ok' => false, 'error' => 'Image nahi mili'], 422);
        }

        Setting::putFor(auth()->id(), self::approvalKey($data['pose']), $path);

        return response()->json(['ok' => true, 'approved' => true, 'url' => asset('storage/' . $path)]);
    }

    /** Us aasan ki approved image ka URL — na ho (ya file gayab ho) to null. */
    private function approvedUrl(string $poseKey): ?string
    {
        $path = Setting::getFor(auth()->id(), self::approvalKey($poseKey));

        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return asset('storage/' . $path);
    }

    private static function approvalKey(string $poseKey): string
    {
        return 'yoga_img_' . $poseKey;
    }

    /**
     * Browser se ek rendered card save karo. Pehli card nayi collection banati
     * hai; baaki `collection` id ke saath usi me jud jaate hain.
     */
    public function save(Request $request)
    {
        $data = $request->validate([
            'category'   => ['nullable', 'string', 'max:100'],
            'language'   => ['nullable', 'in:hindi,gujarati,hinglish'],
            'collection' => ['nullable', 'integer', 'exists:stories,id'],
            'order'      => ['required', 'integer', 'min:1'],
            'text'       => ['required', 'string'],
            'hashtags'   => ['nullable', 'string', 'max:1000'],
            'image'      => ['required', 'string'], // data:image/png;base64,...
        ]);

        if (! empty($data['collection'])) {
            $story = Story::findOrFail($data['collection']);
            abort_unless($story->user_id === auth()->id() || auth()->user()->isAdmin(), 403);
            $part = $story->parts()->orderBy('sort_order')->first()
                ?? $story->parts()->create(['sort_order' => 1, 'body' => $data['text']]);
        } else {
            $cat   = trim((string) ($data['category'] ?? ''));
            $title = trim(($cat !== '' ? Str::title($cat) . ' — ' : '') . 'Kids Yoga');

            $story = Story::create([
                'title'    => $title,
                'type'     => 'yoga',
                'category' => $cat !== '' ? $cat : null,
                'language' => $data['language'] ?? 'hindi',
                'status'   => 'published',
            ]);
            $part = $story->parts()->create(['sort_order' => 1, 'body' => $data['text']]);
        }

        $binary = $this->decodeDataUrl($data['image']);
        if ($binary === null) {
            return response()->json(['ok' => false, 'error' => 'Invalid image data'], 422);
        }

        $path = 'cards/' . Str::uuid() . '.png';
        Storage::disk('public')->put($path, $binary);

        // Caption me safety line hamesha jaati hai — bachchon ka content hai.
        $tags    = trim((string) ($data['hashtags'] ?? ''));
        $caption = trim(
            $data['text']
            . "\n\n" . self::DISCLAIMER
            . ($tags !== '' ? "\n\n" . $tags : '')
        );

        $part->cards()->create([
            'sort_order' => $data['order'],
            'image_path' => $path,
            'text'       => $data['text'], // voice: naam + steps + fayda
            'ig_caption' => $caption,
        ]);

        return response()->json([
            'ok'         => true,
            'collection' => $story->id,
            'redirect'   => route('admin.yoga.show', $story),
        ]);
    }

    /** Ek yoga collection ke saare cards (gallery). */
    public function show(Story $story)
    {
        abort_unless($story->type === 'yoga', 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        return view('admin.yoga.show', compact('story', 'cards'));
    }

    /** Poori yoga collection delete. */
    public function destroy(Story $story)
    {
        abort_unless($story->type === 'yoga', 404);
        $this->authorize('delete', $story);

        $story->load('parts.cards');

        foreach ($story->parts as $part) {
            if ($part->image_path) {
                Storage::disk('public')->delete($part->image_path);
            }
            foreach ($part->cards as $card) {
                $this->instagram->deleteMediaFiles($card);
            }
        }

        if ($story->cover_image) {
            Storage::disk('public')->delete($story->cover_image);
            $coverJpeg = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $story->cover_image);
            if ($coverJpeg && $coverJpeg !== $story->cover_image) {
                Storage::disk('public')->delete($coverJpeg);
            }
        }

        $story->delete();

        return redirect()->route('admin.yoga.index')->with('success', 'Yoga collection delete ho gayi.');
    }

    private function decodeDataUrl(string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/png;base64,/', $dataUrl)) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        return $binary === false ? null : $binary;
    }
}
