<?php

namespace App\Services;

use App\Models\PartCard;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quiz ka "timer reel" — ek hi video me:
 *
 *   [ Question + 4 options + countdown 5..4..3..2..1 ]  →  [ ✅ Answer reveal ]
 *
 * Yahi format viral quiz reels use karte hain — log answer comment karte hain,
 * isse engagement badhta hai. Do alag posts (Q phir A) me ye kaam nahi karta.
 *
 * Sirf un cards par chalta hai jinke paas `answer_image_path` ho (naye quiz).
 * Purane cards {@see InstagramService} / {@see YoutubeService} ke normal
 * single-image reel se hi bante rehte hain.
 */
class QuizReelService
{
    /** Countdown ke liye default seconds (Settings se override ho sakta hai). */
    public const DEFAULT_TIMER = 5;

    /** Timer ki allowed range. Lambe timer (20-25s) mushkil sawaalon ke liye —
     *  reel tab bhi Instagram/Shorts ki 90s limit ke aaram se andar rehti hai. */
    public const MIN_TIMER = 3;

    public const MAX_TIMER = 25;

    /** Timer dropdown me dikhne wale options. */
    public const TIMER_CHOICES = [3, 5, 7, 10, 15, 20, 25];

    /** Answer reveal kitni der screen par rahe (voice ho to uske hisab se badhta hai). */
    private const ANSWER_MIN_SECONDS = 3.0;

    /**
     * In studios ki reel me answer reveal NAHI — sirf sawaal + countdown, jawab
     * log comment me dete hain. (type = naya, category = purane type 'quiz' wale rows)
     */
    public const NO_ANSWER_TYPES = ['puzzle', 'mind_reader', 'this_or_that', 'name_secret'];

    public const NO_ANSWER_CATEGORIES = ['Puzzle', 'Mind Reading', 'This or That', 'Name Secrets'];

    /** Countdown khatam hone ke baad (answer na ho to) itni der aur dikhe — reel achanak na kate. */
    private const NO_ANSWER_TAIL_SECONDS = 1.0;

    public function __construct(private GeminiTtsService $tts)
    {
    }

    /** Is card ka timer-reel ban sakta hai ya nahi. */
    public function supports(?PartCard $card): bool
    {
        if (! $card || blank($card->image_path) || blank($card->answer_image_path)) {
            return false;
        }

        $disk = Storage::disk('public');

        return $disk->exists($card->image_path) && $disk->exists($card->answer_image_path);
    }

    /** Is card ki reel ke end me answer dikhana hai ya nahi. */
    public function showsAnswer(PartCard $card): bool
    {
        $story = $card->part?->story;

        return ! in_array($story?->type, self::NO_ANSWER_TYPES, true)
            && ! in_array($story?->category, self::NO_ANSWER_CATEGORIES, true);
    }

    /**
     * Timer reel banao (cache ke saath) aur storage path do.
     *
     * @throws \RuntimeException
     */
    public function mp4For(PartCard $card): string
    {
        // 🔥 Level Quiz — 3 sawaal (Easy → Medium → Hard) + score frame, ek reel
        if ($frames = self::levelFrames($card)) {
            return $this->levelMp4($card, $frames);
        }

        if (! $this->supports($card)) {
            throw new \RuntimeException('Is card me answer image nahi hai — timer reel nahi ban sakti.');
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('reels');
        $mp4 = 'reels/quiz-' . $card->id . '.mp4';

        $userId = $card->part?->story?->user_id;
        $timer  = $this->timerSeconds($userId);

        $withAnswer = $this->showsAnswer($card);

        // Voice (agar mode on ho) — question ke liye aur answer ke liye alag
        $qVoice = $this->voice($card, $card->text, $userId);
        $aVoice = $withAnswer ? $this->voice($card, $card->answer_text, $userId) : null;

        // Question segment kam se kam timer jitna lamba — warna countdown kat jaata.
        // Answer na ho to countdown ke baad thoda tail, taaki reel 0 par achanak na kate.
        $minQ = $withAnswer ? $timer : $timer + self::NO_ANSWER_TAIL_SECONDS;
        $qDur = round(max($minQ, $qVoice ? $qVoice['seconds'] + 0.8 : 0), 2);
        $aDur = $withAnswer ? round(max(self::ANSWER_MIN_SECONDS, $aVoice ? $aVoice['seconds'] + 0.8 : 0), 2) : 0.0;

        // Resolution tiers: pehle 720p, OOM/fail par halki res par retry.
        $tiers      = [[720, 1280], [540, 960], [480, 854]];
        $lastErr    = '';
        $sfxEnabled = (string) Setting::getFor($userId, 'quiz_sfx_enabled', '1') !== '0';
        $bgmTrack   = (string) Setting::getFor($userId, 'quiz_bgm_track', 'suspense');

        foreach ($tiers as [$w, $h]) {
            $cmd = $this->buildCommand(
                $disk->path($card->image_path),
                $withAnswer ? $disk->path($card->answer_image_path) : null,
                $qVoice ? $disk->path($qVoice['path']) : null,
                $aVoice ? $disk->path($aVoice['path']) : null,
                $qDur,
                $aDur,
                $timer,
                $disk->path($mp4),
                ReelMotion::enabled($userId),
                $w,
                $h,
                $sfxEnabled,
                $bgmTrack,
            );

            try {
                $result = Process::timeout(600)->run($cmd);
            } catch (\Throwable $e) {
                // Signal 9 (OOM kill) / timeout / process crash — agli chhoti res try karo
                $lastErr = $e->getMessage();
                Log::warning('Quiz ffmpeg process crash, lower-res retry', ['res' => "{$w}x{$h}", 'error' => $lastErr]);
                continue;
            }

            if ($result->successful() && $disk->exists($mp4)) {
                $story = $card->part?->story;

                return ReelOutro::append($mp4, $userId, $story?->language, $story?->tts_voice);
            }

            $lastErr = $result->errorOutput() ?: $result->output();
            Log::warning('Quiz ffmpeg failed, lower-res retry', ['res' => "{$w}x{$h}", 'err' => Str::limit($lastErr, 300)]);
        }

        Log::error('Quiz ffmpeg failed (all resolutions)', ['err' => $lastErr]);
        throw new \RuntimeException('Quiz reel nahi ban paayi (ffmpeg signal 9 / OOM / crash). ' . Str::limit($lastErr, 150));
    }

    /* ===================================================================
     |  🔥 Level Quiz — 3 sawaal (Easy → Medium → Hard) + "kitne sahi?" frame
     |
     |  Frames ek folder me: cards/level-{uuid}/q1,a1,q2,a2,q3,a3,end.{webp|png}
     |  Card ka image_path = q1 (photo post / thumbnail), answer_image_path = end.
     |  Isliye koi naya DB column nahi chahiye.
     * =================================================================== */

    public const LEVEL_FRAMES = ['q1', 'a1', 'q2', 'a2', 'q3', 'a3', 'end'];

    /** Har level ka jawab kitni der dikhe. */
    private const LEVEL_ANSWER_SECONDS = 2.5;

    /** Aakhri "kitne sahi hue?" frame kitni der. */
    private const LEVEL_END_SECONDS = 3.5;

    /** Level card hai to uske 7 frame paths (storage-relative), warna null. */
    public static function levelFrames(?PartCard $card): ?array
    {
        if (! $card || ! preg_match('#^(cards/level-[A-Za-z0-9-]+)/q1\.[a-z0-9]+$#i', (string) $card->image_path, $m)) {
            return null;
        }

        $disk = Storage::disk('public');
        $out  = [];
        foreach (self::LEVEL_FRAMES as $name) {
            $found = null;
            foreach (['webp', 'png', 'jpg'] as $ext) {
                if ($disk->exists("{$m[1]}/{$name}.{$ext}")) {
                    $found = "{$m[1]}/{$name}.{$ext}";
                    break;
                }
            }
            if ($found === null) {
                return null;
            }
            $out[$name] = $found;
        }

        return $out;
    }

    /** Level card ka folder (delete ke liye), warna null. */
    public static function levelDir(?PartCard $card): ?string
    {
        return $card && preg_match('#^(cards/level-[A-Za-z0-9-]+)/#i', (string) $card->image_path, $m) ? $m[1] : null;
    }

    /**
     * Level reel: har level ek chhoti clip (sawaal + countdown + jawab, tick/ding ke
     * saath), fir score frame — concat (-c copy), aakhir me BGM. Tukdon me isliye
     * ki shared hosting ek bade ffmpeg process ko signal 9 se maar deta hai.
     */
    protected function levelMp4(PartCard $card, array $frames): string
    {
        $disk   = Storage::disk('public');
        $userId = $card->part?->story?->user_id;
        $base   = $this->timerSeconds($userId);
        // Hard level ko thoda zyada time
        $timers = [$base, $base, min(self::MAX_TIMER, $base + 2)];

        $sfx      = (string) Setting::getFor($userId, 'quiz_sfx_enabled', '1') !== '0';
        $bgmTrack = (string) Setting::getFor($userId, 'quiz_bgm_track', 'suspense');
        $motion   = ReelMotion::enabled($userId);
        $ffmpeg   = config('services.ffmpeg.path', 'ffmpeg');

        $disk->makeDirectory('reels');
        $tmp = 'reels/level-' . $card->id . '-tmp';
        $out = 'reels/quiz-' . $card->id . '.mp4';
        $lastErr = '';

        try {
            foreach ([[720, 1280], [540, 960], [480, 854]] as [$w, $h]) {
                $disk->deleteDirectory($tmp);
                $disk->makeDirectory($tmp);

                try {
                    $clips = [];
                    foreach ([1, 2, 3] as $n) {
                        $clip = "{$tmp}/l{$n}.mp4";
                        $this->runFfmpeg($this->buildCommand(
                            $disk->path($frames["q{$n}"]),
                            $disk->path($frames["a{$n}"]),
                            null,
                            null,
                            (float) $timers[$n - 1],
                            self::LEVEL_ANSWER_SECONDS,
                            $timers[$n - 1],
                            $disk->path($clip),
                            $motion,
                            $w,
                            $h,
                            $sfx,
                            'none',            // BGM poori reel par ek saath, neeche
                        ));
                        $clips[] = $clip;
                    }

                    // Score frame — chup (BGM baad me)
                    $end = "{$tmp}/end.mp4";
                    $this->runFfmpeg(array_merge([
                        $ffmpeg, '-y',
                        '-loop', '1', '-t', $this->num(self::LEVEL_END_SECONDS), '-i', $disk->path($frames['end']),
                        '-f', 'lavfi', '-t', $this->num(self::LEVEL_END_SECONDS), '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100',
                        '-filter_complex', '[0:v]' . ReelMotion::chain($w, $h, self::LEVEL_END_SECONDS, 3, $motion) . '[v]',
                        '-map', '[v]', '-map', '1:a',
                    ], $this->encodeArgs(), [$disk->path($end)]));
                    $clips[] = $end;

                    // Jodo (sab clips same settings se bani hain → -c copy)
                    $list = "{$tmp}/list.txt";
                    $disk->put($list, collect($clips)
                        ->map(fn ($c) => "file '" . str_replace('\\', '/', $disk->path($c)) . "'")
                        ->implode("\n"));
                    $joined = "{$tmp}/joined.mp4";
                    $this->runFfmpeg([$ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $disk->path($list), '-c', 'copy', $disk->path($joined)]);

                    // BGM
                    $bgmFile = public_path("audio/bgm/{$bgmTrack}.mp3");
                    if ($bgmTrack !== 'none' && file_exists($bgmFile)) {
                        $this->runFfmpeg([
                            $ffmpeg, '-y', '-i', $disk->path($joined), '-stream_loop', '-1', '-i', $bgmFile,
                            '-filter_complex', '[1:a]volume=0.22,aformat=sample_rates=44100:channel_layouts=stereo[b];'
                                . '[0:a][b]amix=inputs=2:duration=first:dropout_transition=2,aformat=sample_rates=44100:channel_layouts=stereo[a]',
                            '-map', '0:v', '-map', '[a]', '-c:v', 'copy',
                            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
                            '-movflags', '+faststart', $disk->path($out),
                        ]);
                    } else {
                        $this->runFfmpeg([$ffmpeg, '-y', '-i', $disk->path($joined), '-c', 'copy', '-movflags', '+faststart', $disk->path($out)]);
                    }

                    $story = $card->part?->story;

                    return ReelOutro::append($out, $userId, $story?->language, $story?->tts_voice);
                } catch (\Throwable $e) {
                    $lastErr = $e->getMessage();
                    Log::warning('Level quiz reel fail, lower-res retry', ['res' => "{$w}x{$h}", 'err' => Str::limit($lastErr, 300)]);
                }
            }
        } finally {
            $disk->deleteDirectory($tmp);
        }

        throw new \RuntimeException('Level quiz reel nahi ban paayi. ' . Str::limit($lastErr, 150));
    }

    /** ffmpeg chalao; fail ho to exception (error ke saath). */
    protected function runFfmpeg(array $cmd): void
    {
        $result = Process::timeout(600)->run($cmd);
        if (! $result->successful()) {
            throw new \RuntimeException(Str::limit($result->errorOutput() ?: $result->output(), 400));
        }
    }

    /** Video/audio encode settings — buildCommand jaise hi (concat -c copy ke liye zaroori). */
    protected function encodeArgs(): array
    {
        return [
            '-c:v', 'libx264', '-preset', 'ultrafast', '-tune', 'stillimage', '-threads', '1',
            '-x264-params', 'ref=1:bframes=0:rc-lookahead=10:sync-lookahead=0',
            '-profile:v', 'high', '-level', '3.1',
            '-pix_fmt', 'yuv420p', '-color_range', 'tv', '-r', '25',
            '-g', '50', '-keyint_min', '50', '-sc_threshold', '0', '-flags', '+cgop',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
        ];
    }

    /** Countdown kitne second ka — Settings me badal sakte hain. */
    public function timerSeconds(?int $userId): int
    {
        $val = (int) Setting::getFor($userId, 'quiz_timer_seconds', self::DEFAULT_TIMER);

        return max(self::MIN_TIMER, min(self::MAX_TIMER, $val ?: self::DEFAULT_TIMER));
    }

    /**
     * Text → voice. Mode 'music' ho, text khaali ho ya TTS configured na ho to null.
     *
     * @return array{path:string,seconds:float}|null
     */
    protected function voice(PartCard $card, ?string $text, ?int $userId): ?array
    {
        $mode = Setting::getFor($userId, 'tts_audio_mode', 'music');
        $mode = $card->part?->story?->tts_mode ?: $mode;

        if ($mode === 'music' || blank($text) || ! $this->tts->isConfigured()) {
            return null;
        }

        try {
            return $this->tts->speak(
                $text,
                $card->part?->story?->tts_voice ?: (Setting::getFor($userId, 'tts_voice') ?: null),
                'quiz',
                $card->part?->story?->language ?: 'hindi',
            );
        } catch (\Throwable $e) {
            // Voice na bane to bhi reel banni chahiye (TTS quota aksar khatam hota hai)
            Log::warning('Quiz reel voice fail, bina voice ke bana rahe hain', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * ffmpeg command — question (+ countdown) aur answer segment concat, voiceover +
     * countdown tick/ding SFX + BGM mixing. $aImg null = answer segment nahi
     * (sirf question + countdown, bina ding).
     *
     * Instagram Reels spec ke hisab se: 720x1280, H.264 high, yuv420p, TV range,
     * AAC 44.1k stereo.
     *
     * @return list<string>
     */
    protected function buildCommand(
        string $qImg,
        ?string $aImg,
        ?string $qVoice,
        ?string $aVoice,
        float $qDur,
        float $aDur,
        int $timer,
        string $out,
        bool $motion = false,
        int $w = 720,
        int $h = 1280,
        bool $sfx = true,
        string $bgmTrack = 'suspense',
    ): array {
        $ffmpeg = config('services.ffmpeg.path', 'ffmpeg');
        $withAnswer = $aImg !== null;

        $cmd = [$ffmpeg, '-y', '-loop', '1', '-t', $this->num($qDur), '-i', $qImg];   // 0 = question
        if ($withAnswer) {
            $cmd = array_merge($cmd, ['-loop', '1', '-t', $this->num($aDur), '-i', $aImg]);   // 1 = answer
        }

        // Audio inputs — voice ho to wahi, warna us segment ke liye silence
        $audioIdx = $withAnswer ? 2 : 1;
        $qAudio   = $this->addAudioInput($cmd, $audioIdx, $qVoice, $qDur, 'aq');
        $qFit     = ReelMotion::chain($w, $h, $qDur, 0, $motion);
        $qVideo   = '[0:v]' . $qFit . $this->countdownFilter($timer, $qDur);

        if ($withAnswer) {
            $aAudio = $this->addAudioInput($cmd, $audioIdx, $aVoice, $aDur, 'aa');
            $aFit   = ReelMotion::chain($w, $h, $aDur, 1, $motion);

            $totalDur = $qDur + $aDur;

            $filterParts = [
                $qVideo . '[v0]',
                '[1:v]' . $aFit . '[v1]',
                '[v0][v1]concat=n=2:v=1:a=0[v]',
            ];

            $audioFilters = [
                rtrim($qAudio, ';'),
                rtrim($aAudio, ';'),
                '[aq][aa]concat=n=2:v=0:a=1[avoice]',
            ];
        } else {
            $totalDur = $qDur;

            $filterParts  = [$qVideo . '[v]'];
            $audioFilters = [rtrim($qAudio, ';'), '[aq]anull[avoice]'];
        }

        $currentAudioLabel = '[avoice]';

        // 1. Sound Effects (Tick-Tick during countdown & Ding on Answer Reveal)
        if ($sfx) {
            $tickStart = max(0.0, $qDur - $timer);
            $sfxNodes = [];
            for ($k = 0; $k < $timer; $k++) {
                $tTime = $tickStart + $k;
                $delayMs = (int) round($tTime * 1000);
                $sfxNodes[] = "sine=frequency=1600:duration=0.04,afade=t=out:st=0.01:d=0.03,volume=1.5,adelay={$delayMs}|{$delayMs}[sfx_t{$k}]";
            }
            $sfxInputsStr = '';
            for ($k = 0; $k < $timer; $k++) {
                $sfxInputsStr .= "[sfx_t{$k}]";
            }
            $sfxTotalCount = $timer;

            // Ding at exact answer reveal ($qDur) — answer hi nahi to ding bhi nahi
            if ($withAnswer) {
                $dingDelayMs = (int) round($qDur * 1000);
                $sfxNodes[] = "sine=frequency=1760:duration=1.1,afade=t=out:st=0.05:d=1.05,volume=1.9,adelay={$dingDelayMs}|{$dingDelayMs}[sfx_ding]";
                $sfxInputsStr .= '[sfx_ding]';
                $sfxTotalCount++;
            }

            $audioFilters[] = implode(';', $sfxNodes);
            $audioFilters[] = $sfxInputsStr . "amix=inputs={$sfxTotalCount}:duration=longest,aformat=sample_rates=44100:channel_layouts=stereo[sfx_all]";
            $audioFilters[] = "[avoice][sfx_all]amix=inputs=2:duration=first[avoice_sfx]";
            $currentAudioLabel = '[avoice_sfx]';
        }

        // 2. Background Music (BGM)
        $bgmFile = public_path("audio/bgm/{$bgmTrack}.mp3");
        if ($bgmTrack !== 'none' && file_exists($bgmFile)) {
            $cmd = array_merge($cmd, ['-stream_loop', '-1', '-t', $this->num($totalDur), '-i', $bgmFile]);
            $bgmIdx = $audioIdx;
            $audioIdx++;

            $audioFilters[] = "[{$bgmIdx}:a]atrim=duration={$this->num($totalDur)},volume=0.22,aformat=sample_rates=44100:channel_layouts=stereo[abgm]";
            $audioFilters[] = "{$currentAudioLabel}[abgm]amix=inputs=2:duration=first:dropout_transition=2[afinal]";
            $currentAudioLabel = '[afinal]';
        }

        $audioFilters[] = "{$currentAudioLabel}aformat=sample_rates=44100:channel_layouts=stereo[a]";
        $filter = implode(';', array_merge($filterParts, array_filter($audioFilters)));

        return array_merge($cmd, [
            '-filter_complex', $filter,
            '-map', '[v]', '-map', '[a]',
            '-c:v', 'libx264', '-preset', 'ultrafast', '-tune', 'stillimage', '-threads', '1',
            '-x264-params', 'ref=1:bframes=0:rc-lookahead=10:sync-lookahead=0',
            '-profile:v', 'high', '-level', '3.1',
            '-pix_fmt', 'yuv420p', '-color_range', 'tv', '-r', '25',
            '-g', '50', '-keyint_min', '50', '-sc_threshold', '0', '-flags', '+cgop',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
            '-movflags', '+faststart',
            $out,
        ]);
    }

    /**
     * Ek segment ka audio input jodo aur uska filter-chunk return karo.
     * Voice ho to use pad/trim karke exact segment length me fit karte hain.
     */
    protected function addAudioInput(array &$cmd, int &$idx, ?string $voice, float $dur, string $label): string
    {
        if ($voice) {
            $cmd = array_merge($cmd, ['-i', $voice]);
            $chunk = "[{$idx}:a]aresample=44100,apad,atrim=duration=" . $this->num($dur)
                . ',aformat=sample_rates=44100:channel_layouts=stereo[' . $label . '];';
        } else {
            $cmd = array_merge($cmd, [
                '-f', 'lavfi', '-t', $this->num($dur),
                '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100',
            ]);
            $chunk = "[{$idx}:a]aformat=sample_rates=44100:channel_layouts=stereo[" . $label . '];';
        }

        $idx++;

        return $chunk;
    }

    /**
     * Countdown overlay — bada number (5,4,3,2,1) + neeche ghatati hui progress line.
     *
     * Font na mile to khaali string — reel phir bhi ban jaati hai, bas timer ke
     * bina (Linux/shared host par font path alag hota hai).
     *
     * Progress bar har second ke liye ek alag drawbox hai (ghatati hui width),
     * ek time-based expression ke bajaye. Wajah: drawbox ke expression me `t`
     * ka matlab THICKNESS hota hai, time nahi — isliye `iw*(1-t/5)` jaisi width
     * hamesha poori width de deti thi. `n` (frame number) drawbox support hi
     * nahi karta ("Error when evaluating the expression").
     */
    protected function countdownFilter(int $timer, float $qDur): string
    {
        $font = $this->fontFile();

        if ($font === null) {
            return '';
        }

        // Bacha hua time. ceil isliye ki pehle poore second me "5" dikhe — bina
        // iske eif value truncate kar deta hai aur countdown 4 se shuru lagta hai.
        $text = sprintf('%%{eif\\:ceil(max(0\\,%d-t))\\:d}', $timer);

        // Countdown UPAR-DAAYE kone me. Pehle ye neeche beech me tha (y=h-th-118)
        // jahan card ka apna footer ("jawab comment me...") aur handle hote hain —
        // number seedha unke upar chadh jaata tha aur dono padhe nahi jaate the.
        // Upar-daaya kona khaali rehta hai kyunki header text center-aligned hai.
        //
        // fontfile ko quote karna zaroori hai — bina quote ke ffmpeg escaped colon
        // ke baawajood path ko "C:" par tod deta hai ("No option name near ...").
        $filter = ',drawtext=fontfile=\'' . $font . '\':text=\'' . $text . '\''
            . ':fontcolor=white:fontsize=84:box=1:boxcolor=black@0.62:boxborderw=22'
            . ':x=w-tw-46:y=42:enable=\'lt(t,' . $timer . ')\'';

        for ($i = 0; $i < $timer; $i++) {
            $width = number_format(1 - $i / $timer, 4, '.', '');
            // y me `ih` (input height) — `h` drawbox me BOX ki height hoti hai,
            // usse y negative ho jaata hai aur bar frame ke upar clip ho jaati hai.
            $filter .= ',drawbox=x=0:y=ih-26:w=\'iw*' . $width . '\':h=14:color=0x22c55e:t=fill'
                . ':enable=\'between(t,' . $i . ',' . ($i + 1) . ')\'';
        }

        return $filter;
    }

    /**
     * drawtext ke liye font file. ffmpeg filter parser me ':' ka matlab option
     * separator hota hai, isliye path me use escape karna padta hai.
     */
    protected function fontFile(): ?string
    {
        $candidates = array_filter([
            config('services.ffmpeg.font'),
            'C:/Windows/Fonts/arialbd.ttf',
            'C:/Windows/Fonts/arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                // Pehle backslash → forward slash, PHIR colon escape. Ulta karne par
                // abhi-abhi lagaya hua escape-backslash khud hi replace ho jaata hai
                // ("C\:/..." → "C/:/...") aur ffmpeg filter parse fail kar deta hai.
                return str_replace(':', '\\:', str_replace('\\', '/', $path));
            }
        }

        Log::warning('Quiz reel: drawtext ke liye koi font nahi mila — timer ke bina reel banegi.');

        return null;
    }

    /** ffmpeg ko locale-safe decimal do (kahin comma na aa jaye). */
    protected function num(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
