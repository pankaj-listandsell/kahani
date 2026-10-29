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
    public function index()
    {
        $query = Story::withCount('parts')->where('type', 'salah')->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view('admin.salah.index', ['collections' => $query->get()]);
    }

    public function show(Story $story)
    {
        abort_unless($story->type === 'salah', 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        return view('admin.salah.show', compact('story', 'cards'));
    }

    public function destroy(Story $story, InstagramService $instagram)
    {
        abort_unless($story->type === 'salah', 404);
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

        return redirect()->route('admin.salah.index')->with('success', 'Salah collection delete ho gayi.');
    }

    public function generate(Request $request, ViralStudioAiService $ai)
    {
        $data = $request->validate([
            'count'    => ['required', 'integer', 'min:1', 'max:10'],
            'language' => ['required', 'in:gujarati,hindi,hinglish'],
            'category' => ['nullable', 'string', 'max:150'],
            'topic'    => ['nullable', 'string', 'max:200'],
            'style'    => ['nullable', 'in:normal,confession'],
        ]);

        try {
            $items = $ai->generateSalah(
                (int) $data['count'],
                $data['language'],
                $data['category'] ?? 'Parenting',
                trim((string) ($data['topic'] ?? '')),
                $data['style'] ?? 'normal'
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
            'category'        => ['nullable', 'string', 'max:150'],
            'cards'           => ['required', 'array', 'min:1', 'max:30'],
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
                'title'    => 'Salah — ' . ($data['category'] ?? 'Advice') . ' — ' . now()->format('d M'),
                'type'     => 'salah',
                'category' => $data['category'] ?? null,
                'language' => $data['language'] ?? 'gujarati',
                'status'   => 'published',
            ]);
            $part = $story->parts()->create(['sort_order' => 1, 'body' => $cardsInput[0]['text'] ?? 'Salah']);

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
                'redirect'   => route('admin.salah.show', $story),
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
