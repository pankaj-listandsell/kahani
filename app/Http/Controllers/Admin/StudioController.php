<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\InstagramService;
use App\Services\ShayariStudioAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shayari / Jokes / Quotes "Studio" — AI se batch content generate karo, browser
 * canvas se sundar cards banao, aur ek collection (Story type=shayari/joke/quote)
 * ke roop me save karo. Har item = 1 Part + 1 Card → existing auto-post ise
 * apne-aap IG/YT/FB par drip kar deta hai.
 */
class StudioController extends Controller
{
    /** @var list<string> */
    private const TYPES = ['shayari', 'joke', 'quote'];

    private const LABELS = ['shayari' => 'Shayari', 'joke' => 'Jokes', 'quote' => 'Suvichar'];

    public function __construct(private InstagramService $instagram)
    {
    }

    public function index()
    {
        $query = Story::withCount('parts')
            ->whereIn('type', self::TYPES)
            ->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view('admin.studio.index', ['collections' => $query->get()]);
    }

    /** Ek collection ke saare cards (gallery grid). */
    public function show(Story $story)
    {
        abort_unless(in_array($story->type, self::TYPES, true), 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        // Saare parts ke cards ek saath (order preserve — parts+cards dono sorted)
        $cards = $story->parts->flatMap->cards->values();

        return view('admin.studio.show', compact('story', 'cards'));
    }

    /** Poori collection delete karo (cards + unki media files ke saath). */
    public function destroy(Story $story)
    {
        abort_unless(in_array($story->type, self::TYPES, true), 404);
        $this->authorize('delete', $story);

        $story->load('parts.cards');
        foreach ($story->parts as $part) {
            foreach ($part->cards as $card) {
                $this->instagram->deleteMediaFiles($card);
            }
        }

        $story->delete(); // parts + cards cascade

        return redirect()->route('admin.studio.index')->with('success', 'Collection delete ho gayi.');
    }

    /** AI se ek batch (N items) generate karke JSON me lauta do. */
    public function generate(Request $request, ShayariStudioAiService $ai)
    {
        $data = $request->validate([
            'type'     => ['required', 'in:shayari,joke,quote'],
            'category' => ['nullable', 'string', 'max:100'],
            'count'    => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        try {
            $items = $ai->generateBatch($data['type'], $data['category'] ?? '', $data['count']);

            return response()->json(['ok' => true, 'items' => $items]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Browser se ek rendered card (PNG base64) save karo. Pehli card ke saath nayi
     * collection banti hai; baaki cards `collection` id ke saath usi me add hote
     * hain. (Per-card save taaki bade PNG post_max_size cross na karein.)
     */
    public function save(Request $request)
    {
        $data = $request->validate([
            'type'       => ['required', 'in:shayari,joke,quote'],
            'category'   => ['nullable', 'string', 'max:100'],
            'collection' => ['nullable', 'integer', 'exists:stories,id'],
            'order'      => ['required', 'integer', 'min:1'],
            'text'       => ['required', 'string'],
            'image'      => ['required', 'string'], // data:image/png;base64,...
        ]);

        // Existing collection me add karo (owner check), warna nayi banao.
        // Ek collection = 1 Story + 1 Part; saare items usi part ke cards hote hain
        // (isse show page + card-by-card auto-post dono theek chalte hain).
        if (! empty($data['collection'])) {
            $story = Story::findOrFail($data['collection']);
            abort_unless(
                $story->user_id === auth()->id() || auth()->user()->isAdmin(),
                403
            );
            $part = $story->parts()->orderBy('sort_order')->first()
                ?? $story->parts()->create(['sort_order' => 1, 'body' => $data['text']]);
        } else {
            $cat   = trim((string) ($data['category'] ?? ''));
            $title = trim(($cat !== '' ? Str::title($cat) . ' — ' : '') . self::LABELS[$data['type']]);

            $story = Story::create([
                'title'    => $title,
                'type'     => $data['type'],
                'category' => $cat !== '' ? $cat : null,
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

        // Har item = ek card (usi part me) → auto-post ek-ek karke post karega
        $part->cards()->create([
            'sort_order' => $data['order'],
            'image_path' => $path,
            'text'       => $data['text'],
        ]);

        return response()->json([
            'ok'         => true,
            'collection' => $story->id,
            'redirect'   => route('admin.studio.show', $story),
        ]);
    }

    /** "data:image/png;base64,xxxx" → binary (warna null). */
    private function decodeDataUrl(string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/png;base64,/', $dataUrl)) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        return $binary === false ? null : $binary;
    }
}
