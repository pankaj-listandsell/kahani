<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function __construct(private ImageService $imageService)
    {
    }

    /**
     * Admin dashboard — saari kahaniyon ki list.
     */
    public function index()
    {
        $stories = Story::withCount('parts')->latest()->get();

        return view('admin.stories.index', compact('stories'));
    }

    public function create()
    {
        return view('admin.stories.create');
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
        $story->load('parts.cards');

        return view('admin.stories.show', compact('story'));
    }

    public function edit(Story $story)
    {
        return view('admin.stories.edit', compact('story'));
    }

    public function update(Request $request, Story $story)
    {
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
     * Cover image khud se (file) upload karo — AI ka alternative.
     */
    public function uploadCover(Request $request, Story $story)
    {
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
        // Parts ki images bhi delete karo
        foreach ($story->parts as $part) {
            $this->imageService->delete($part->image_path);
        }
        if ($story->cover_image) {
            Storage::disk('public')->delete($story->cover_image);
        }

        $story->delete(); // parts cascade delete ho jaenge

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
