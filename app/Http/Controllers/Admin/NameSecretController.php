<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\ViralStudioAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NameSecretController extends Controller
{
    public function index()
    {
        return view('admin.name_secret.index');
    }

    public function generate(Request $request, ViralStudioAiService $ai)
    {
        $data = $request->validate([
            'letters'  => ['required', 'string', 'max:100'],
            'language' => ['required', 'in:gujarati,hindi,hinglish'],
        ]);

        $lettersArray = array_values(array_filter(array_map('trim', explode(',', $data['letters']))));
        if (empty($lettersArray)) {
            $lettersArray = ['A', 'S', 'P', 'R', 'K'];
        }

        try {
            $items = $ai->generateNameSecrets($lettersArray, $data['language']);

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
            'cards.*.caption'      => ['nullable', 'string', 'max:500'],
            'cards.*.hashtags'     => ['nullable', 'string', 'max:1000'],
            'cards.*.image'        => ['required', 'string'],
        ]);

        $cardsInput = $data['cards'];
        $title = 'Name Secrets — ' . now()->format('d M');

        $story = Story::create([
            'user_id'  => auth()->id(),
            'title'    => $title,
            'type'     => 'quote',
            'category' => 'Name Secrets',
            'language' => $data['language'] ?? 'gujarati',
            'status'   => 'published',
        ]);
        $part = $story->parts()->create(['sort_order' => 1, 'body' => $cardsInput[0]['text'] ?? 'Name Secret']);

        foreach ($cardsInput as $cardItem) {
            $decoded = $this->decodeDataUrl($cardItem['image']);
            if (! $decoded) continue;

            $path = 'cards/' . Str::uuid() . '.' . $decoded['ext'];
            Storage::disk('public')->put($path, $decoded['binary']);

            $tags = trim((string) ($cardItem['hashtags'] ?? ''));
            $caption = trim(($cardItem['caption'] ?? '') . ($tags !== '' ? "\n\n" . $tags : ''));

            $part->cards()->create([
                'sort_order'        => $cardItem['order'],
                'image_path'        => $path,
                'text'              => $cardItem['text'],
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
