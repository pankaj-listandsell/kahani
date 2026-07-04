<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\GeminiImageService;
use App\Services\ImageService;
use App\Services\InstagramService;
use App\Services\StoryAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function __construct(
        private ImageService $imageService,
        private InstagramService $instagram,
    ) {
    }

    /**
     * Admin dashboard — saari kahaniyon ki list.
     */
    public function index()
    {
        $query = Story::withCount('parts')->latest();

        // Regular user sirf apni stories dekhe; admin sabki
        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        $stories = $query->get();

        return view('admin.stories.index', compact('stories'));
    }

    public function create()
    {
        return view('admin.stories.create');
    }

    /**
     * Ek topic se AI (Gemini/Pollinations) se poori Hindi kahani generate karo.
     * JSON return karta hai — create form ke title/description/body fields bharne
     * ke liye (user review karke Save karta hai).
     */
    public function generateFromTopic(Request $request, StoryAiService $ai)
    {
        $data = $request->validate([
            'topic'  => ['required', 'string', 'max:500'],
            'length' => ['nullable', 'in:short,medium,long'],
        ]);

        try {
            $story = $ai->generate($data['topic'], $data['length'] ?? 'short');

            return response()->json(['ok' => true] + $story);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $body = $data['body'];
        unset($data['body']);

        $story = Story::create($data);

        // Har story ka 1 hi part — poori kahani isi Part 1 me
        $part = $story->parts()->create([
            'sort_order' => 1,
            'body'       => $body,
        ]);

        return redirect()
            ->route('admin.parts.cards', $part)
            ->with('success', 'Story ban gayi! Ab neeche cards banao. 🖼️');
    }

    /**
     * Ek kahani ke saare parts (admin view).
     */
    public function show(Story $story)
    {
        $this->authorize('view', $story);

        $story->load('parts.cards');

        return view('admin.stories.show', compact('story'));
    }

    public function edit(Story $story)
    {
        $this->authorize('update', $story);

        return view('admin.stories.edit', compact('story'));
    }

    public function update(Request $request, Story $story)
    {
        $this->authorize('update', $story);

        $data = $this->validated($request);
        $body = $data['body'];
        unset($data['body']);

        $story->update($data);

        // Single part ka text update (na ho to bana do)
        $part = $story->parts()->orderBy('sort_order')->first();
        if ($part) {
            $part->update(['body' => $body]);
        } else {
            $story->parts()->create(['sort_order' => 1, 'body' => $body]);
        }

        return redirect()
            ->route('admin.stories.show', $story)
            ->with('success', 'Story updated.');
    }

    /**
     * Pollinations.ai se story-related 9:16 (1080x1920 ratio) cover image banao.
     * Yahi image Instagram reel ka cover (thumbnail) banegi.
     */
    public function generateCover(Request $request, Story $story)
    {
        $this->authorize('update', $story);

        $request->validate([
            'cover_prompt' => ['required', 'string', 'max:1000'],
        ]);

        try {
            // Purani cover hata do
            if ($story->cover_image) {
                Storage::disk('public')->delete($story->cover_image);
            }

            // 720x1280 = exact 9:16 (reel/cover ke saath match)
            $path = $this->imageService->generate(
                $request->input('cover_prompt'),
                720,
                1280,
                'covers',
            );

            $story->update(['cover_image' => $path]);

            return back()->with('success', 'AI cover image ban gayi! 🎨');
        } catch (\Throwable $e) {
            return back()->with('error', 'Cover image nahi bani: ' . $e->getMessage());
        }
    }

    /**
     * Kahani ke hisab se AI cover — ek click. Gemini text se image-prompt banao,
     * phir Gemini image se cover (fail ho to Pollinations par fallback).
     */
    public function generateCoverAi(Story $story, StoryAiService $ai, GeminiImageService $gemini)
    {
        $this->authorize('update', $story);

        $part = $story->parts()->orderBy('sort_order')->first();
        $body = $part?->body ?: ($story->description ?: $story->title);

        try {
            // 1) Kahani se image prompt
            $prompt = $ai->coverPrompt($story->title, $body);

            // Purani cover hata do
            if ($story->cover_image) {
                Storage::disk('public')->delete($story->cover_image);
            }

            // 2) Gemini image, warna Pollinations fallback
            try {
                $path = $gemini->isConfigured()
                    ? $gemini->generate($prompt, 'covers')
                    : $this->imageService->generate($prompt, 720, 1280, 'covers');
            } catch (\Throwable $e) {
                Log::warning('Gemini cover fail, Pollinations fallback', ['error' => $e->getMessage()]);
                $path = $this->imageService->generate($prompt, 720, 1280, 'covers');
            }

            $story->update(['cover_image' => $path]);

            return back()->with('success', 'AI cover kahani ke hisab se ban gayi! 🎨');
        } catch (\Throwable $e) {
            return back()->with('error', 'Cover nahi bani: ' . $e->getMessage());
        }
    }

    /**
     * Cover image khud se (file) upload karo — AI ka alternative.
     */
    public function uploadCover(Request $request, Story $story)
    {
        $this->authorize('update', $story);

        $request->validate([
            'cover_file' => ['required', 'image', 'max:5120'],
        ]);

        // Purani cover hata do
        if ($story->cover_image) {
            Storage::disk('public')->delete($story->cover_image);
        }

        $path = $request->file('cover_file')->store('covers', 'public');
        $story->update(['cover_image' => $path]);

        return back()->with('success', 'Cover image upload ho gayi! 🖼️');
    }

    public function destroy(Story $story)
    {
        $this->authorize('delete', $story);

        $story->load('parts.cards');

        // Har part ki AI image + uske saare cards ki image/JPEG/reel MP4 delete karo
        foreach ($story->parts as $part) {
            $this->imageService->delete($part->image_path);

            foreach ($part->cards as $card) {
                $this->instagram->deleteMediaFiles($card);
            }
        }

        // Cover image + uska JPEG cache (reel cover ke liye banaya gaya)
        if ($story->cover_image) {
            Storage::disk('public')->delete($story->cover_image);

            $coverJpeg = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $story->cover_image);
            if ($coverJpeg && $coverJpeg !== $story->cover_image) {
                Storage::disk('public')->delete($coverJpeg);
            }
        }

        $story->delete(); // parts + cards cascade delete ho jaenge

        return redirect()
            ->route('admin.stories.index')
            ->with('success', 'Story deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'body'        => ['required', 'string'],
            'status'      => ['required', 'in:draft,published'],
        ]);
    }
}
