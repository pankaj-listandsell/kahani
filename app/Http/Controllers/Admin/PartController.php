<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\Story;
use App\Services\ImageService;
use Illuminate\Http\Request;

class PartController extends Controller
{
    public function __construct(private ImageService $imageService)
    {
    }

    public function create(Story $story)
    {
        $nextOrder = ($story->parts()->max('sort_order') ?? 0) + 1;

        return view('admin.parts.create', compact('story', 'nextOrder'));
    }

    public function store(Request $request, Story $story)
    {
        $data = $this->validated($request);
        $data['story_id'] = $story->id;

        $part = $story->parts()->create($data);

        // Part bante hi seedha card editor par bhej do
        return redirect()
            ->route('admin.parts.cards', $part)
            ->with('success', 'Part added! Now create its text cards.');
    }

    public function edit(Part $part)
    {
        return view('admin.parts.edit', compact('part'));
    }

    public function update(Request $request, Part $part)
    {
        $data = $this->validated($request);
        $part->update($data);

        return redirect()
            ->route('admin.stories.show', $part->story)
            ->with('success', 'Part updated.');
    }

    public function destroy(Part $part)
    {
        $story = $part->story;
        $this->imageService->delete($part->image_path);
        $part->delete();

        return redirect()
            ->route('admin.stories.show', $story)
            ->with('success', 'Part deleted.');
    }

    /**
     * Is part ke liye AI se image banao (button se call hota hai).
     */
    public function generateImage(Request $request, Part $part)
    {
        $request->validate([
            'image_prompt' => ['required', 'string', 'max:1000'],
        ]);

        try {
            // Purani image delete karke nayi banao
            $this->imageService->delete($part->image_path);

            $path = $this->imageService->generate($request->input('image_prompt'));

            $part->update([
                'image_path'   => $path,
                'image_prompt' => $request->input('image_prompt'),
            ]);

            return back()->with('success', 'Nayi image ban gayi! 🎨');
        } catch (\Throwable $e) {
            return back()->with('error', 'Image nahi bani: ' . $e->getMessage());
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'sort_order'   => ['required', 'integer', 'min:1'],
            'title'        => ['nullable', 'string', 'max:255'],
            'body'         => ['required', 'string'],
        ]);
    }
}
