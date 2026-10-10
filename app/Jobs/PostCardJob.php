<?php

namespace App\Jobs;

use App\Exceptions\CardBusyException;
use App\Exceptions\FacebookRateLimitException;
use App\Models\PartCard;
use App\Services\FacebookService;
use App\Services\InstagramService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ek card ko Instagram / Facebook par post karo — background me (queue).
 * "All as Reels / Posts" button ab request me ruk kar ffmpeg + upload nahi karta,
 * bas har card ka ye job daal deta hai; worker ek-ek karke post karta hai.
 *
 * Card ka status (posted / failed + error) service khud set karti hai — page
 * refresh karke dikh jaata hai.
 */
class PostCardJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /** Ek card-platform ka ek hi job queue me (button do baar dabaya to bhi). */
    public int $uniqueFor = 3600;

    public function __construct(
        public int $cardId,
        public string $platform, // ig | fb
        public string $type,     // reel | image
    ) {
    }

    public function uniqueId(): string
    {
        return "{$this->platform}.{$this->cardId}";
    }

    public function handle(InstagramService $instagram, FacebookService $facebook): void
    {
        $card = PartCard::with('part.story')->find($this->cardId);
        if (! $card) {
            return; // card delete ho gaya
        }

        $userId = $card->part?->story?->user_id;

        try {
            if ($this->platform === 'fb') {
                if ($card->isFbPosted()) {
                    return;
                }
                // FB ne is user par rate-limit lagaya hai — baaki cards mat bhejo (block badhega)
                if (Cache::has("fb_rate_limited.{$userId}")) {
                    Log::info('PostCardJob: FB rate-limit — card skip', ['card' => $card->id]);

                    return;
                }
                $this->type === 'reel' ? $facebook->postReel($card) : $facebook->postPhoto($card);
            } else {
                if ($card->isPosted()) {
                    return;
                }
                $this->type === 'reel' ? $instagram->postReel($card) : $instagram->postCard($card);
            }

            Log::info('PostCardJob: posted', ['card' => $card->id, 'platform' => $this->platform, 'type' => $this->type]);
        } catch (FacebookRateLimitException $e) {
            Cache::put("fb_rate_limited.{$userId}", true, now()->addHours(2));
            Log::warning('PostCardJob: FB rate-limit, 2 ghante FB jobs skip', ['card' => $card->id, 'error' => $e->getMessage()]);
        } catch (CardBusyException $e) {
            Log::info('PostCardJob: card abhi kahin aur se post ho raha hai — skip', ['card' => $card->id]);
        } catch (\Throwable $e) {
            // Service ne card ko 'failed' + error mark kar diya hai — page par dikhega
            Log::warning('PostCardJob: failed', ['card' => $card->id, 'platform' => $this->platform, 'error' => $e->getMessage()]);
        }
    }
}
