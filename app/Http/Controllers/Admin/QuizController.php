<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartCard;
use App\Models\Story;
use App\Services\InstagramService;
use App\Services\ShayariStudioAiService;
use App\Services\YoutubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quiz (MCQ) studio — competitive-exam style questions. AI se question + 4
 * options + answer + reason generate, browser canvas se 2 cards (Question,
 * phir Answer) banao, collection (Story type=quiz) ke roop me save. Quiz
 * auto-post me SEQUENCE me jaata hai (Q pehle, phir A) — random nahi.
 */
class QuizController extends Controller
{
    public function __construct(private InstagramService $instagram)
    {
    }

    public function index()
    {
        $query = Story::withCount('parts')->where('type', 'quiz')->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view('admin.quiz.index', ['collections' => $query->get()]);
    }

    /** AI se quiz (question + options + answer + reason) generate. */
    public function generate(Request $request, ShayariStudioAiService $ai)
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'count'    => ['required', 'integer', 'min:1', 'max:30'],
            'language' => ['nullable', 'in:hindi,gujarati,hinglish'],
        ]);

        try {
            $items = $ai->generateQuiz($data['category'] ?? '', $data['count'], $data['language'] ?? 'hindi');

            return response()->json(['ok' => true, 'items' => $items]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Browser se ek rendered card (Question ya Answer) save karo. Pehli card ke
     * saath nayi collection banti hai; baaki cards usi me order-wise add hote hain.
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
            $title = trim(($cat !== '' ? Str::title($cat) . ' — ' : '') . 'Quiz');

            $story = Story::create([
                'title'    => $title,
                'type'     => 'quiz',
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

        // Instagram caption = card text + hashtags (sirf IG ke liye)
        $tags    = trim((string) ($data['hashtags'] ?? ''));
        $caption = trim($data['text'] . ($tags !== '' ? "\n\n" . $tags : ''));

        $part->cards()->create([
            'sort_order' => $data['order'],
            'image_path' => $path,
            'text'       => $data['text'],
            'ig_caption' => $caption !== '' ? $caption : null,
        ]);

        return response()->json([
            'ok'         => true,
            'collection' => $story->id,
            'redirect'   => route('admin.quiz.show', $story),
        ]);
    }

    /** Ek quiz collection ke saare cards (gallery). */
    public function show(Story $story)
    {
        abort_unless($story->type === 'quiz', 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        return view('admin.quiz.show', compact('story', 'cards'));
    }

    /**
     * Combined "Quiz Reel" — Question card + uske baad wala Answer card ko ek hi
     * video me jodo (Question → pause → Answer reveal).
     */
    public function quizReel(PartCard $card, YoutubeService $youtube)
    {
        $story = $card->part?->story;
        abort_unless($story && $story->type === 'quiz', 404);
        $this->authorize('update', $story);

        @set_time_limit(300);

        // Is Question card ke baad wala (Answer) card
        $answer = $card->part->cards()
            ->where('sort_order', '>', $card->sort_order)
            ->orderBy('sort_order')
            ->first();

        $cards = $answer ? [$card, $answer] : [$card];

        try {
            // Question ke baad 3 sec pause (viewer sochne ka time)
            $mp4 = $youtube->forUser($story->user_id)
                ->mp4ForCards($cards, 'quiz-' . $card->id, [0 => 3.0]);

            return response()->json(['ok' => true, 'url' => asset('storage/' . $mp4)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Poori quiz collection delete. */
    public function destroy(Story $story)
    {
        abort_unless($story->type === 'quiz', 404);
        $this->authorize('delete', $story);

        $story->load('parts.cards');
        foreach ($story->parts as $part) {
            foreach ($part->cards as $card) {
                $this->instagram->deleteMediaFiles($card);
            }
        }
        $story->delete();

        return redirect()->route('admin.quiz.index')->with('success', 'Quiz collection delete ho gayi.');
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
