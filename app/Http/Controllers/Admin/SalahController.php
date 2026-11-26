<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\InstagramService;
use App\Services\ViralStudioAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 💬 Salah Studio — "Prashna / Jawab / Samadhan" wale text-heavy advice cards
 * (parchment background, highlighted title). Reel normal card→reel pipeline se banti hai.
 */
class SalahController extends Controller
{
    /** Story type, route-name prefix, view folder aur label — subclass (jaise RomanceController) inhe badalti hai. */
    protected string $type = 'salah';
    protected string $route = 'admin.salah';
    protected string $view = 'admin.salah';
    protected string $label = 'Salah';
    /** Set ho to request ka style ignore karke hamesha yahi style (e.g. 'kahani'). */
    protected ?string $forcedStyle = null;

    public function index()
    {
        $query = Story::withCount('parts')->where('type', $this->type)->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view($this->view . '.index', ['collections' => $query->get()]);
    }

    public function show(Story $story)
    {
        abort_unless($story->type === $this->type, 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        // Show page sab subclasses ke liye ek hi (routeBase se links badalte hain)
        return view('admin.salah.show', compact('story', 'cards') + ['routeBase' => $this->route]);
    }

    public function destroy(Story $story, InstagramService $instagram)
    {
        abort_unless($story->type === $this->type, 404);
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

        if ($story->cover_image) {
            Storage::disk('public')->delete($story->cover_image);
        }

        $story->delete();

        return redirect()->route($this->route . '.index')->with('success', $this->label . ' collection delete ho gayi.');
    }

    public function generate(Request $request, ViralStudioAiService $ai)
    {
        $data = $request->validate([
            'count'    => ['required', 'integer', 'min:1', 'max:10'],
            'language' => ['required', 'in:gujarati,hindi,hinglish'],
            'category'     => ['nullable', 'string', 'max:300'],
            // 🏷️ Kai category ek saath (checkbox dropdown)
            'categories'   => ['nullable', 'array', 'max:40'],
            'categories.*' => ['nullable', 'string', 'max:300'],
            'topic'        => ['nullable', 'string', 'max:200'],
            'style'        => ['nullable', 'in:normal,confession,kahani'],
            // Adult Kahani: ek kahani kitne card me (1 / 2 / 3)
            'parts'        => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        $cats = array_values(array_filter(array_map('trim', (array) ($data['categories'] ?? []))));
        if (! $cats && filled($data['category'] ?? null)) {
            $cats = [trim($data['category'])];
        }

        // Category khaali ho to topic zaroori — warna AI ko pata hi nahi kis par likhna hai
        if (! $cats && blank($data['topic'] ?? null)) {
            return response()->json(['ok' => false, 'error' => 'Category khaali hai — to Topic lakho (jem ke: "college ma pehlo prem").'], 422);
        }

        try {
            $items = $ai->generateSalah(
                (int) $data['count'],
                $data['language'],
                $this->categoryPlan($cats, (int) $data['count']),
                trim((string) ($data['topic'] ?? '')),
                $this->forcedStyle ?? ($data['style'] ?? 'normal'),
                (int) ($data['parts'] ?? 1)
            );

            return response()->json(['ok' => true, 'items' => $items]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'language'        => ['nullable', 'in:hindi,gujarati,hinglish'],
            'category'        => ['nullable', 'string', 'max:300'],
            'cards'           => ['required', 'array', 'min:1', 'max:30'],
            'cards.*.order'   => ['required', 'integer', 'min:1'],
            'cards.*.text'    => ['required', 'string'],
            'cards.*.caption' => ['nullable', 'string', 'max:2200'], // Instagram caption limit
            'cards.*.hashtags'=> ['nullable', 'string', 'max:2000'],
            'cards.*.image'   => ['required', 'string'],
            // 2-3 card ki kahani: ek kahani ke saare bhaag ka same group (1, 2, …)
            'cards.*.group'   => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $cardsInput = $data['cards'];

            $story = Story::create([
                'user_id'  => auth()->id(),
                'title'    => $this->label . ' — ' . Str::limit($data['category'] ?? 'Mix', 40) . ' — ' . now()->format('d M'),
                'type'     => $this->type,
                'category' => $data['category'] ?? null,
                'language' => $data['language'] ?? 'gujarati',
                'status'   => 'published',
                'tts_mode' => 'music', // naye cards: Reel Audio pehle se "Music only" (page par badal sakte ho)
            ]);
            // Group ho to har kahani = alag part (body "series:N") — auto-post uske bhaag kram me bhejta hai.
            // Warna purana tareeka: sab cards ek part me (random order).
            $groups = collect($cardsInput)->groupBy(fn ($c) => (int) ($c['group'] ?? 0));
            $series = $groups->count() > 1 || $groups->keys()->first() !== 0;
            $parts = [];
            $partFor = function (array $cardItem) use ($story, $groups, $series, &$parts, $cardsInput) {
                $g = $series ? (int) ($cardItem['group'] ?? 0) : 0;
                if (! isset($parts[$g])) {
                    $n = $groups->get($g)?->count() ?? 1;
                    $parts[$g] = $story->parts()->create([
                        'sort_order' => count($parts) + 1,
                        'body'       => $series && $n > 1 ? 'series:' . $n : ($cardsInput[0]['text'] ?? 'Salah'),
                    ]);
                }

                return $parts[$g];
            };

            foreach ($cardsInput as $cardItem) {
                $decoded = $this->decodeDataUrl($cardItem['image']);
                if (! $decoded) continue;

                $path = 'cards/' . Str::uuid() . '.' . $decoded['ext'];
                Storage::disk('public')->put($path, $decoded['binary']);

                $tags = trim((string) ($cardItem['hashtags'] ?? ''));
                $caption = trim(($cardItem['caption'] ?? '') . ($tags !== '' ? "\n\n" . $tags : ''));

                $partFor($cardItem)->cards()->create([
                    'sort_order' => $cardItem['order'],
                    'image_path' => $path,
                    'text'       => $cardItem['text'],
                    'ig_caption' => $caption !== '' ? $caption : null,
                ]);
            }

            return response()->json([
                'ok'         => true,
                'collection' => $story->id,
                'redirect'   => route($this->route . '.show', $story),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ek category → wahi. Kai category → har card ko alag category (random kram me, card zyada hon
     * to dobara ghoomti hai): "Card 1: X; Card 2: Y …" — AI prompt ki "Category:" line me jaata hai.
     */
    protected function categoryPlan(array $cats, int $count): string
    {
        if (count($cats) <= 1) {
            return $cats[0] ?? '';
        }

        shuffle($cats);
        $plan = [];
        for ($i = 0; $i < max(1, $count); $i++) {
            $plan[] = 'Card ' . ($i + 1) . ': ' . $cats[$i % count($cats)];
        }

        return 'Different category for each card — use exactly this one per card, in this order: ' . implode('; ', $plan) . '.';
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
