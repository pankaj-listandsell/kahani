<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartCard;
use App\Models\Setting;
use App\Services\AiCaptionService;
use App\Services\GeminiTtsService;
use App\Services\InstagramService;
use App\Services\YoutubeService;
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
            'text'   => ['nullable', 'string'],   // card ka raw text (voice-over ke liye)
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

        $card = $part->cards()->create([
            'sort_order' => $data['order'],
            'image_path' => $path,
            'text'       => $data['text'] ?? null,
        ]);

        // Auto Instagram caption + hashtags (Studio jaisa) — pehle card (reset) par
        // ek baar AI se banao, baaki cards me wahi reuse karo (quota-friendly).
        // Fail ho to chhod do — card fir bhi save (post ke time default caption banega).
        try {
            $caption = $request->boolean('reset')
                ? app(AiCaptionService::class)->forCard($card)
                : $part->cards()->whereNotNull('ig_caption')->value('ig_caption');

            if (filled($caption)) {
                $card->update(['ig_caption' => $caption]);
            }
        } catch (\Throwable $e) {
            // caption optional — card save rahega
        }

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
     * Ek card ka reel (720x1280 mp4, voice-mode ON ho to voice-over ke saath)
     * bana ke uska URL do — page par preview/check karne ke liye.
     */
    public function reel(PartCard $card, YoutubeService $youtube, GeminiTtsService $tts)
    {
        $story = $card->part->story;
        $this->authorize('update', $story);

        @set_time_limit(300);

        // Voice mode ON + text ho to pehle voice prime/detect karo — taaki agar
        // Gemini TTS ka quota/limit khatam ho to user ko clear warning mile
        // (warna chup-chaap silent video ban jaata hai). Success par cache ho jaata
        // hai, to neeche mp4ForCard wahi reuse karega (dobara API call nahi).
        $warning = null;
        $mode = Setting::getFor($story->user_id, 'tts_audio_mode', 'music');
        if ($mode !== 'music' && filled($card->text) && $tts->isConfigured()) {
            $style = in_array($story->type, ['shayari', 'quote', 'joke'], true) ? $story->type : 'story';
            try {
                $tts->speak($card->text, Setting::getFor($story->user_id, 'tts_voice') ?: null, $style);
            } catch (\Throwable $e) {
                $warning = 'Voice add nahi hui — Gemini TTS ka limit/quota khatam (free tier: 10 voice/din). '
                    . 'Video bina voice ke bana hai.';
            }
        }

        try {
            $mp4 = $youtube->forUser($story->user_id)->mp4ForCard($card);

            return response()->json(['ok' => true, 'url' => asset('storage/' . $mp4), 'warning' => $warning]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
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
