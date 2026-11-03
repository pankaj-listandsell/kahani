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
 * 💌 Prem Patra Studio — har card ek love letter, har letter alag love theme me
 * (pehlo prem, pati-patni, judaai…). Reel normal card→reel pipeline se banti hai.
 */
class PremPatraController extends Controller
{
    public function index()
    {
        $query = Story::withCount('parts')->where('type', 'prem_patra')->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view('admin.patra.index', ['collections' => $query->get()]);
    }

    public function show(Story $story)
    {
        abort_unless($story->type === 'prem_patra', 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        return view('admin.patra.show', compact('story', 'cards'));
    }

    public function destroy(Story $story, InstagramService $instagram)
    {
        abort_unless($story->type === 'prem_patra', 404);
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

        return redirect()->route('admin.patra.index')->with('success', 'Prem Patra collection delete ho gayi.');
    }

    public function generate(Request $request, ViralStudioAiService $ai)
    {
        $data = $request->validate([
            'count'    => ['required', 'integer', 'min:1', 'max:12'],
            'language' => ['required', 'in:gujarati,hindi,hinglish'],
            'theme'    => ['nullable', 'string', 'in:mix,' . implode(',', array_keys(ViralStudioAiService::PATRA_THEMES))],
            'topic'    => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $items = $ai->generatePremPatra(
                (int) $data['count'],
                $data['language'],
                $data['theme'] ?? 'mix',
                trim((string) ($data['topic'] ?? ''))
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
                'title'    => ($data['category'] ?? 'Prem Patra') . ' — ' . now()->format('d M'),
                'type'     => 'prem_patra',
                'category' => $data['category'] ?? null,
                'language' => $data['language'] ?? 'gujarati',
                'status'   => 'published',
                'tts_mode' => 'music', // naye cards: Reel Audio pehle se "Music only" (page par badal sakte ho)
            ]);
            $part = $story->parts()->create(['sort_order' => 1, 'body' => $cardsInput[0]['text'] ?? 'Prem Patra']);

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
                'redirect'   => route('admin.patra.show', $story),
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
