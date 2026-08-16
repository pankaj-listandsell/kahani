<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\InstagramService;
use App\Services\ViralStudioAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PuzzleController extends Controller
{
    public function index()
    {
        $query = Story::withCount('parts')
            ->where(function ($q) {
                $q->where('type', 'puzzle')
                  ->orWhere(function ($sq) {
                      $sq->where('type', 'quiz')->where('category', 'Puzzle');
                  });
            })
            ->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view('admin.puzzle.index', ['collections' => $query->get()]);
    }

    public function show(Story $story)
    {
        abort_unless($story->type === 'puzzle' || ($story->type === 'quiz' && $story->category === 'Puzzle'), 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        return view('admin.puzzle.show', compact('story', 'cards'));
    }

    public function destroy(Story $story, InstagramService $instagram)
    {
        abort_unless($story->type === 'puzzle' || ($story->type === 'quiz' && $story->category === 'Puzzle'), 404);
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

        return redirect()->route('admin.puzzle.index')->with('success', 'Puzzle collection delete ho gayi.');
    }

    public function generate(Request $request, ViralStudioAiService $ai)
    {
        $data = $request->validate([
            'count'    => ['required', 'integer', 'min:1', 'max:20'],
            'language' => ['required', 'in:gujarati,hindi,hinglish'],
        ]);

        try {
            $items = $ai->generatePuzzles((int) $data['count'], $data['language']);

            return response()->json(['ok' => true, 'items' => $items]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'category'             => ['nullable', 'string', 'max:100'],
            'language'             => ['nullable', 'in:hindi,gujarati,hinglish'],
            'cards'                => ['required', 'array', 'min:1', 'max:60'],
            'cards.*.order'        => ['required', 'integer', 'min:1'],
            'cards.*.text'         => ['required', 'string'],
            'cards.*.answer'       => ['nullable', 'string', 'max:600'],
            'cards.*.caption'      => ['nullable', 'string', 'max:500'],
            'cards.*.hashtags'     => ['nullable', 'string', 'max:1000'],
            'cards.*.image'        => ['required', 'string'],
            'cards.*.answer_image' => ['nullable', 'string'],
        ]);

        try {
            $cardsInput = $data['cards'];
            $cat = trim((string) ($data['category'] ?? 'Puzzle'));
            $title = trim($cat . ' Challenge — ' . now()->format('d M'));

            $story = Story::create([
                'user_id'  => auth()->id(),
                'title'    => $title,
                'type'     => 'puzzle',
                'category' => 'Puzzle',
                'language' => $data['language'] ?? 'gujarati',
                'status'   => 'published',
            ]);
            $part = $story->parts()->create(['sort_order' => 1, 'body' => $cardsInput[0]['text'] ?? 'Puzzle']);

            foreach ($cardsInput as $cardItem) {
                $decoded = $this->decodeDataUrl($cardItem['image']);
                if (! $decoded) continue;

                $path = 'cards/' . Str::uuid() . '.' . $decoded['ext'];
                Storage::disk('public')->put($path, $decoded['binary']);

                $answerPath = null;
                if (filled($cardItem['answer_image'] ?? null)) {
                    $ansDecoded = $this->decodeDataUrl($cardItem['answer_image']);
                    if ($ansDecoded) {
                        $answerPath = 'cards/' . Str::uuid() . '-a.' . $ansDecoded['ext'];
                        Storage::disk('public')->put($answerPath, $ansDecoded['binary']);
                    }
                }

                $tags = trim((string) ($cardItem['hashtags'] ?? ''));
                $caption = trim(($cardItem['caption'] ?? '') . ($tags !== '' ? "\n\n" . $tags : ''));

                $part->cards()->create([
                    'sort_order'        => $cardItem['order'],
                    'image_path'        => $path,
                    'answer_image_path' => $answerPath,
                    'text'              => $cardItem['text'],
                    'answer_text'       => $cardItem['answer'] ?? null,
                    'ig_caption'        => $caption !== '' ? $caption : null,
                ]);
            }

            return response()->json([
                'ok'         => true,
                'collection' => $story->id,
                'redirect'   => route('admin.puzzle.show', $story),
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
