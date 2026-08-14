<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AskedQuestion;
use App\Models\Setting;
use App\Models\Story;
use App\Services\InstagramService;
use App\Services\PixabayService;
use App\Services\QuizReelService;
use App\Services\ShayariStudioAiService;
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
            'category'  => ['nullable', 'string', 'max:100'],
            'count'     => ['required', 'integer', 'min:1', 'max:30'],
            'language'  => ['nullable', 'in:hindi,gujarati,hinglish'],
            // Q&A List design kai batch me sawaal maangta hai — jo mil chuke hain
            // wo yahan bhejta hai taaki AI unhe dobara na likhe.
            'exclude'   => ['nullable', 'array', 'max:60'],
            'exclude.*' => ['string', 'max:300'],
        ]);

        $topic = trim((string) ($data['category'] ?? ''));

        try {
            // Pehle post ho chuke sawaal AI ko dikha do — warna wahi sawaal
            // baar-baar aate hain (ye record collection delete hone par bhi rehta hai)
            $asked   = AskedQuestion::recentFor(auth()->id(), $topic);
            $exclude = array_merge($data['exclude'] ?? [], $asked);

            $items = $ai->generateQuiz(
                $topic,
                $data['count'],
                $data['language'] ?? 'gujarati',
                $exclude,
            );

            // AI ko mana karne ke baad bhi wo repeat kar deta hai — isliye yahan
            // hash se pakka check. Topic koi bhi ho, ek sawaal ek hi baar.
            $before = count($items);
            $items  = AskedQuestion::filterNew(auth()->id(), $items);
            $dropped = $before - count($items);

            return response()->json([
                'ok'      => true,
                'items'   => array_values($items),
                'dropped' => $dropped,           // repeat nikal diye
                'asked'   => count($asked),      // is topic par pehle se kitne
            ]);
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
            'category'             => ['nullable', 'string', 'max:100'],
            'language'             => ['nullable', 'in:hindi,gujarati,hinglish'],
            'collection'           => ['nullable', 'integer', 'exists:stories,id'],
            'cards'                => ['nullable', 'array', 'min:1', 'max:60'],
            'cards.*.order'        => ['required_with:cards', 'integer', 'min:1'],
            'cards.*.text'         => ['required_with:cards', 'string'],
            'cards.*.answer'       => ['nullable', 'string', 'max:600'],
            'cards.*.caption'      => ['nullable', 'string', 'max:300'],
            'cards.*.questions'    => ['nullable', 'array', 'max:30'],
            'cards.*.questions.*'  => ['string', 'max:500'],
            'cards.*.hashtags'     => ['nullable', 'string', 'max:1000'],
            'cards.*.image'        => ['required_with:cards', 'string'],
            'cards.*.answer_image' => ['nullable', 'string'],

            // Single card fallback
            'order'                => ['required_without:cards', 'integer', 'min:1'],
            'text'                 => ['required_without:cards', 'string'],
            'answer'               => ['nullable', 'string', 'max:600'],
            'caption'              => ['nullable', 'string', 'max:300'],
            'questions'            => ['nullable', 'array', 'max:30'],
            'questions.*'          => ['string', 'max:500'],
            'hashtags'             => ['nullable', 'string', 'max:1000'],
            'image'                => ['required_without:cards', 'string'],
            'answer_image'         => ['nullable', 'string'],
        ]);

        $cardsInput = $data['cards'] ?? [
            [
                'order'        => $data['order'],
                'text'         => $data['text'],
                'answer'       => $data['answer'] ?? null,
                'caption'      => $data['caption'] ?? null,
                'questions'    => $data['questions'] ?? [],
                'hashtags'     => $data['hashtags'] ?? null,
                'image'        => $data['image'],
                'answer_image' => $data['answer_image'] ?? null,
            ],
        ];

        if (! empty($data['collection'])) {
            $story = Story::findOrFail($data['collection']);
            abort_unless($story->user_id === auth()->id() || auth()->user()->isAdmin(), 403);
            $part = $story->parts()->orderBy('sort_order')->first()
                ?? $story->parts()->create(['sort_order' => 1, 'body' => $cardsInput[0]['text'] ?? 'Quiz']);
        } else {
            $cat   = trim((string) ($data['category'] ?? ''));
            $title = trim(($cat !== '' ? Str::title($cat) . ' — ' : '') . 'Quiz');

            $story = Story::create([
                'title'    => $title,
                'type'     => 'quiz',
                'category' => $cat !== '' ? $cat : null,
                'language' => $data['language'] ?? 'gujarati',
                'status'   => 'published',
            ]);
            $part = $story->parts()->create(['sort_order' => 1, 'body' => $cardsInput[0]['text'] ?? 'Quiz']);
        }

        foreach ($cardsInput as $cardItem) {
            $decoded = $this->decodeDataUrl($cardItem['image']);
            if ($decoded === null) {
                continue;
            }

            $path = 'cards/' . Str::uuid() . '.' . $decoded['ext'];
            Storage::disk('public')->put($path, $decoded['binary']);

            $answerPath = null;
            if (filled($cardItem['answer_image'] ?? null)) {
                $ansDecoded = $this->decodeDataUrl($cardItem['answer_image']);
                if ($ansDecoded !== null) {
                    $answerPath = 'cards/' . Str::uuid() . '-a.' . $ansDecoded['ext'];
                    Storage::disk('public')->put($answerPath, $ansDecoded['binary']);
                }
            }

            $answer = trim((string) ($cardItem['answer'] ?? ''));
            $tags   = trim((string) ($cardItem['hashtags'] ?? ''));
            $caption = trim(
                $this->captionBody($cardItem['caption'] ?? '', $data['language'] ?? 'gujarati')
                . ($tags !== '' ? "\n\n" . $tags : '')
            );

            foreach ($cardItem['questions'] ?? [] as $q) {
                AskedQuestion::remember(
                    auth()->id(),
                    trim((string) ($data['category'] ?? '')),
                    $data['language'] ?? 'gujarati',
                    $q,
                );
            }

            $part->cards()->create([
                'sort_order'        => $cardItem['order'],
                'image_path'        => $path,
                'answer_image_path' => $answerPath,
                'text'              => $cardItem['text'],
                'answer_text'       => $answer !== '' ? $answer : null,
                'ig_caption'        => $caption !== '' ? $caption : null,
            ]);
        }

        return response()->json([
            'ok'         => true,
            'collection' => $story->id,
            'redirect'   => route('admin.quiz.show', $story),
        ]);
    }

    /** "Jawab comment me likho" — content ki bhasha me. */
    private const CTA = [
        'hindi'    => '👇 अपना जवाब कमेंट में लिखो',
        'gujarati' => '👇 તમારો જવાબ કોમેન્ટમાં લખો',
        'hinglish' => '👇 Apna answer comment me likho',
    ];

    /**
     * Caption ka text hissa (hashtags ke bina).
     *
     * Settings me default caption set ho to wahi — jaisa hai waisa, taaki user
     * ka apna CTA/branding na tute. Warna AI ki hook line + comment wali CTA.
     */
    private function captionBody(string $aiHook, string $language): string
    {
        $default = trim((string) Setting::getFor(auth()->id(), 'quiz_caption', ''));

        if ($default !== '') {
            return $default;
        }

        $hook = trim($aiHook);
        $cta  = self::CTA[$language] ?? self::CTA['hindi'];

        return $hook !== '' ? $hook . "\n\n" . $cta : $cta;
    }



    /**
     * Timer reel ka countdown kitne second ka ho — per-user setting.
     * Reel har baar naye sire se banti hai, isliye badalne ke baad "Generate
     * Reel" dabate hi naya timer lag jaata hai.
     */
    public function timer(Request $request)
    {
        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:' . QuizReelService::MIN_TIMER, 'max:' . QuizReelService::MAX_TIMER],
        ]);

        Setting::putFor(auth()->id(), 'quiz_timer_seconds', (string) $data['seconds']);

        return response()->json(['ok' => true, 'seconds' => $data['seconds']]);
    }

    /** Ek quiz collection ke saare cards (gallery). */
    public function show(Story $story)
    {
        abort_unless($story->type === 'quiz', 404);
        $this->authorize('view', $story);

        $story->load(['parts.cards']);
        $cards = $story->parts->flatMap->cards->values();

        // Timer reel tabhi banti hai jab card me answer image ho (naye quiz)
        $hasTimer = $cards->contains(fn ($c) => filled($c->answer_image_path));
        $timer    = app(QuizReelService::class)->timerSeconds($story->user_id);

        return view('admin.quiz.show', compact('story', 'cards', 'hasTimer', 'timer'));
    }

    /** Poori quiz collection delete. */
    public function destroy(Story $story)
    {
        abort_unless($story->type === 'quiz', 404);
        $this->authorize('delete', $story);

        $story->load('parts.cards');

        // Har part ki AI image + uske saare cards ki image/JPEG/reel MP4 delete karo
        foreach ($story->parts as $part) {
            if ($part->image_path) {
                Storage::disk('public')->delete($part->image_path);
            }
            foreach ($part->cards as $card) {
                $this->instagram->deleteMediaFiles($card);
            }
        }

        // Cover image + uska JPEG cache (agar ho)
        if ($story->cover_image) {
            Storage::disk('public')->delete($story->cover_image);
            $coverJpeg = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $story->cover_image);
            if ($coverJpeg && $coverJpeg !== $story->cover_image) {
                Storage::disk('public')->delete($coverJpeg);
            }
        }

        $story->delete();

        return redirect()->route('admin.quiz.index')->with('success', 'Quiz collection delete ho gayi.');
    }

    private function decodeDataUrl(string $dataUrl): ?array
    {
        if (! preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $dataUrl, $m)) {
            return null;
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false) {
            return null;
        }

        return ['binary' => $binary, 'ext' => $ext];
    }
}
