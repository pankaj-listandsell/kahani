<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\ImageService;
use App\Services\InstagramService;
use App\Services\StoryAiService;
use Illuminate\Http\Request;
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
        // Sirf normal kahaniyan — shayari/joke/quote collections "Shayari & Jokes"
        // section me dikhte hain (purani rows type=story hoti hain).
        $query = Story::withCount('parts')
            ->where(fn ($q) => $q->where('type', 'story')->orWhereNull('type'))
            ->latest();

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
            'length' => ['nullable', 'in:short,medium,long,1000,1500,8000,20000'],
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
    /**
     * Is story/collection ke reels ka audio mode set karo (voice / voice_music /
     * music). null = user ka global setting use ho. Reel generate + auto-post
     * dono me yahi use hota hai.
     */
    public function audioMode(Request $request, Story $story)
    {
        $this->authorize('update', $story);

        $data = $request->validate([
            'tts_mode'  => ['nullable', 'in:voice,voice_music,music'],
            'tts_voice' => ['nullable', 'in:Kore,Aoede,Leda,Zephyr,Puck,Charon,Fenrir,Orus'],
        ]);

        $story->update([
            'tts_mode'  => $data['tts_mode'] ?: null,
            'tts_voice' => $data['tts_voice'] ?: null,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Is story/collection ko kaunse platforms par auto-post karna hai
     * (instagram / youtube / facebook). Khaali = sab platforms (default).
     */
    public function platforms(Request $request, Story $story)
    {
        $this->authorize('update', $story);

        $data = $request->validate([
            'platforms'   => ['nullable', 'array'],
            'platforms.*' => ['in:instagram,youtube,facebook'],
        ]);

        $list = array_values(array_unique($data['platforms'] ?? []));
        $story->update(['platforms' => $list ?: null]);

        return response()->json(['ok' => true]);
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
