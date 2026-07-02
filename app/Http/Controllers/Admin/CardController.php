<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CardController extends Controller
{
    /**
     * Card editor page — yahan text cards preview aur generate hote hain.
     */
    public function editor(Part $part)
    {
        $part->load('cards', 'story');

        return view('admin.cards.editor', compact('part'));
    }

    /**
     * Browser se bana hua ek card (PNG base64) save karo.
     * Pehle card (reset=1) ke saath purane saare cards delete ho jaate hain.
     */
    public function store(Request $request, Part $part)
    {
        $data = $request->validate([
            'image'  => ['required', 'string'],   // data:image/png;base64,....
            'order'  => ['required', 'integer', 'min:1'],
            'reset'  => ['nullable', 'boolean'],
        ]);

        // Naya set shuru — purane cards + files hata do
        if ($request->boolean('reset')) {
            foreach ($part->cards as $old) {
                Storage::disk('public')->delete($old->image_path);
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
        foreach ($part->cards as $card) {
            Storage::disk('public')->delete($card->image_path);
        }
        $part->cards()->delete();

        return back()->with('success', 'All cards deleted.');
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
