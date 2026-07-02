<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartCard;
use App\Services\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CardController extends Controller
{
    public function __construct(private InstagramService $instagram)
    {
    }

    /**
     * Card editor page — yahan text cards preview aur generate hote hain.
     */
    public function editor(Part $part)
    {
        $this->authorize('update', $part->story);

        $part->load('cards', 'story');

        return view('admin.cards.editor', compact('part'));
    }

    /**
     * Browser se bana hua ek card (PNG base64) save karo.
     * Pehle card (reset=1) ke saath purane saare cards delete ho jaate hain.
     */
    public function store(Request $request, Part $part)
    {
        $this->authorize('update', $part->story);

        $data = $request->validate([
            'image'  => ['required', 'string'],   // data:image/png;base64,....
            'order'  => ['required', 'integer', 'min:1'],
            'reset'  => ['nullable', 'boolean'],
        ]);

        // Naya set shuru — purane cards + unki saari files (image/JPEG/MP4) hata do
        if ($request->boolean('reset')) {
            foreach ($part->cards as $old) {
                $this->instagram->deleteMediaFiles($old);
            }
            $part->cards()->delete();
        }

        $binary = $this->decodeDataUrl($data['image']);
        if ($binary === null) {
            return response()->json(['ok' => false, 'error' => 'Invalid image data'], 422);
        }

        $path = 'cards/' . Str::uuid() . '.png';
        Storage::disk('public')->put($path, $binary);

        $part->cards()->create([
            'sort_order' => $data['order'],
            'image_path' => $path,
        ]);

        return response()->json([
            'ok'    => true,
            'count' => $part->cards()->count(),
        ]);
    }

    /**
     * Is part ke saare cards delete karo.
     */
    public function clear(Part $part)
    {
        $this->authorize('update', $part->story);

        foreach ($part->cards as $card) {
            $this->instagram->deleteMediaFiles($card);
        }
        $part->cards()->delete();

        return back()->with('success', 'All cards deleted.');
    }

    /**
     * Ek particular card delete karo (uski saari media files ke saath).
     */
    public function destroy(PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        $this->instagram->deleteMediaFiles($card);
        $card->delete();

        return back()->with('success', 'Card deleted.');
    }

    /**
     * "data:image/png;base64,xxxx" ko binary mein badlo.
     */
    private function decodeDataUrl(string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/png;base64,/', $dataUrl)) {
            return null;
        }

        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($base64, true);

        return $binary === false ? null : $binary;
    }
}
