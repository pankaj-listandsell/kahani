<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\ViralStudioAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MindReaderController extends Controller
{
    public function index()
    {
        return view('admin.mind_reader.index');
    }

    public function generate(Request $request, ViralStudioAiService $ai)
    {
        $data = $request->validate([
            'count'    => ['required', 'integer', 'min:1', 'max:10'],
            'language' => ['required', 'in:gujarati,hindi,hinglish'],
        ]);

        try {
            $items = $ai->generateMindReader((int) $data['count'], $data['language']);

            return response()->json(['ok' => true, 'items' => $items]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function save(Request $request)
    {
        $data = $request->validate([
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

        $cardsInput = $data['cards'];
        $title = 'Mind Reader Magic — ' . now()->format('d M');

        $story = Story::create([
            'user_id'  => auth()->id(),
            'title'    => $title,
            'type'     => 'quiz',
            'category' => 'Mind Reading',
            'language' => $data['language'] ?? 'gujarati',
            'status'   => 'published',
        ]);
        $part = $story->parts()->create(['sort_order' => 1, 'body' => $cardsInput[0]['text'] ?? 'Mind Reader']);

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
            'redirect'   => route('admin.quiz.show', $story),
        ]);
    }

    protected function decodeDataUrl(string $dataUrl): ?array
    {
        if (preg_match('/^data:image\/(png|webp|jpeg|jpg);base64,(.+)$/', $dataUrl, $m)) {
            $binary = base64_decode($m[2], true);
            if ($binary === false) return null;
            $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            return ['ext' => $ext, 'binary' => $binary];
        }
        return null;
    }
}
