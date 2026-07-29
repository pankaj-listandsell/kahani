<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Bachchon ke liye safe yoga aasan ki curated list.
 *
 * AI ko aasan KHUD invent nahi karne dete — warna galat ya khatarnak pose aa
 * sakte hain. Yahan sirf beginner/kid-safe aasan hain (koi headstand/shirsasana
 * jaisi advanced pose nahi). AI se sirf steps/fayde ka text banwate hain.
 *
 * Har entry me:
 *   hi / en / emoji  — card par dikhne wale naam
 *   scene            — image model ko pose ki EXACT geometry (naam se guess
 *                      karne par flux aksar galat pose banata hai)
 *   shape            — AI image fail ho to konsa local SVG fallback use ho
 */
class YogaPoseLibrary
{
    /** Fallback SVG figures — public/img/yoga/<shape>.svg */
    public const SHAPES = ['standing', 'sitting', 'kneeling', 'lying'];

    /**
     * Image prompt ka fixed style — HAR pose par same lagta hai taaki poori
     * collection ek jaisi dikhe (alag-alag style ke cards reel me bure lagte hain).
     *
     * Order maayne rakhta hai: pose ka NAAM pehle, phir geometry, phir style.
     * Style pehle rakhne par flux pose ignore karke sirf "koi bachcha yoga kar
     * raha hai" bana deta tha — testing me naam-pehle wala saaf jeeta.
     */
    public const STYLE_PREFIX = 'flat vector illustration for children, cute cartoon indian kid doing ';

    public const STYLE_SUFFIX = ', simple rounded shapes, thick clean outlines, soft pastel colors, '
        . "full body visible, centered, plain light background, children's book illustration style, "
        . 'no text, no letters, no words, no watermark, not photorealistic, no real child photo';

    /**
     * @return array<string, array{hi:string, en:string, emoji:string, shape:string, scene:string}>
     */
    public static function all(): array
    {
        return [
            'tadasana' => [
                'hi' => 'ताड़ासन', 'en' => 'Mountain Pose', 'emoji' => '🏔️', 'shape' => 'standing',
                'scene' => 'standing tall and straight, BOTH feet together on the ground, both arms stretched straight up overhead with palms joined',
            ],
            'vrikshasana' => [
                'hi' => 'वृक्षासन', 'en' => 'Tree Pose', 'emoji' => '🌳', 'shape' => 'standing',
                'scene' => 'standing on ONE leg only, the other foot lifted and pressed flat against the inner thigh, both palms joined together straight above the head',
            ],
            'trikonasana' => [
                'hi' => 'त्रिकोणासन', 'en' => 'Triangle Pose', 'emoji' => '📐', 'shape' => 'standing',
                'scene' => 'legs WIDE apart, body bent sideways, one hand reaching down to touch the ankle and the other arm pointing straight up to the sky',
            ],
            'garudasana' => [
                'hi' => 'गरुड़ासन', 'en' => 'Eagle Pose', 'emoji' => '🦅', 'shape' => 'standing',
                'scene' => 'standing on ONE bent leg, the other leg wrapped around it, both arms crossed and twisted together in front of the chest',
            ],
            'sukhasana' => [
                'hi' => 'सुखासन', 'en' => 'Easy Pose', 'emoji' => '🧘', 'shape' => 'sitting',
                'scene' => 'sitting cross-legged on the floor, back straight, both hands resting on the knees, eyes closed with a calm smile',
            ],
            'padmasana' => [
                'hi' => 'पद्मासन', 'en' => 'Lotus Pose', 'emoji' => '🪷', 'shape' => 'sitting',
                'scene' => 'sitting on the floor in full lotus, EACH foot resting on top of the opposite thigh, back straight, hands on the knees',
            ],
            'titli' => [
                'hi' => 'तितली आसन', 'en' => 'Butterfly Pose', 'emoji' => '🦋', 'shape' => 'sitting',
                'scene' => 'sitting on the floor with the SOLES OF BOTH FEET pressed together in front, knees dropped out to the sides, both hands holding the toes',
            ],
            'naukasana' => [
                'hi' => 'नौकासन', 'en' => 'Boat Pose', 'emoji' => '⛵', 'shape' => 'sitting',
                'scene' => 'balancing on the bottom with BOTH straight legs lifted off the floor and both arms stretched forward, the body making a clear V shape',
            ],
            'paschimottanasana' => [
                'hi' => 'पश्चिमोत्तानासन', 'en' => 'Seated Forward Bend', 'emoji' => '🙇', 'shape' => 'sitting',
                'scene' => 'sitting with BOTH legs straight out in front on the floor, bending forward over the legs, both hands holding the toes',
            ],
            'anulom_vilom' => [
                'hi' => 'अनुलोम-विलोम', 'en' => 'Alternate Nostril Breathing', 'emoji' => '🌬️', 'shape' => 'sitting',
                'scene' => 'sitting cross-legged, ONE hand raised to the face with a finger gently closing one nostril, the other hand resting on the knee, eyes closed',
            ],
            'vajrasana' => [
                'hi' => 'वज्रासन', 'en' => 'Thunderbolt Pose', 'emoji' => '⚡', 'shape' => 'kneeling',
                'scene' => 'kneeling on the floor and sitting back on the heels, back straight, both palms resting flat on the thighs',
            ],
            'balasana' => [
                'hi' => 'बालासन', 'en' => "Child's Pose", 'emoji' => '🧒', 'shape' => 'kneeling',
                'scene' => 'kneeling and folded all the way forward, FOREHEAD resting on the mat, both arms stretched out flat in front, resting',
            ],
            'marjariasana' => [
                'hi' => 'मार्जरी आसन', 'en' => 'Cat-Cow Pose', 'emoji' => '🐱', 'shape' => 'kneeling',
                'scene' => 'on ALL FOURS on hands and knees in a table position, the back arched up high like a stretching cat, head looking down',
            ],
            'simhasana' => [
                'hi' => 'सिंहासन', 'en' => 'Lion Pose', 'emoji' => '🦁', 'shape' => 'kneeling',
                'scene' => 'kneeling with both hands on the knees, MOUTH WIDE OPEN and tongue stuck out, roaring playfully like a lion',
            ],
            'ustrasana' => [
                'hi' => 'उष्ट्रासन', 'en' => 'Camel Pose', 'emoji' => '🐫', 'shape' => 'kneeling',
                'scene' => 'kneeling upright and leaning BACKWARDS, chest open to the sky, both hands reaching back down to hold the heels',
            ],
            'bhujangasana' => [
                'hi' => 'भुजंगासन', 'en' => 'Cobra Pose', 'emoji' => '🐍', 'shape' => 'lying',
                'scene' => 'lying FACE DOWN on the mat with palms under the shoulders, only the chest and head lifted up like a cobra, legs flat on the floor',
            ],
            'dhanurasana' => [
                'hi' => 'धनुरासन', 'en' => 'Bow Pose', 'emoji' => '🏹', 'shape' => 'lying',
                'scene' => 'lying FACE DOWN, knees bent up, BOTH hands reaching back to hold the ankles, chest and thighs lifted so the body curves like a bow',
            ],
            'setubandhasana' => [
                'hi' => 'सेतुबंधासन', 'en' => 'Bridge Pose', 'emoji' => '🌉', 'shape' => 'lying',
                'scene' => 'lying ON THE BACK with knees bent and feet flat on the mat, hips lifted high to make a bridge shape, both arms flat on the floor',
            ],
            'pawanmuktasana' => [
                'hi' => 'पवनमुक्तासन', 'en' => 'Knee-to-Chest Pose', 'emoji' => '🎈', 'shape' => 'lying',
                'scene' => 'lying ON THE BACK, both knees bent and hugged tightly to the chest with both arms, head resting on the mat',
            ],
            'shavasana' => [
                'hi' => 'शवासन', 'en' => 'Relaxation Pose', 'emoji' => '😴', 'shape' => 'lying',
                'scene' => 'lying FLAT ON THE BACK completely relaxed, legs straight, arms a little away from the body with palms facing up, eyes closed',
            ],
        ];
    }

    /** Ek pose ka data — key galat ho to null. */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** Sirf valid pose keys chaano (user input safe karne ke liye). */
    public static function filterKeys(array $keys): array
    {
        $valid = self::all();

        return array_values(array_unique(array_filter(
            $keys,
            fn ($k) => is_string($k) && isset($valid[$k])
        )));
    }

    /**
     * Image model ke liye poora prompt — pose ka naam + exact geometry + fixed style.
     * Bachchon ki asli photo kabhi na bane isliye style me hi cartoon/vector lock hai.
     */
    public static function imagePrompt(string $key): string
    {
        $pose = self::get($key);

        if ($pose === null) {
            return self::STYLE_PREFIX . 'EASY POSE yoga: sitting cross-legged, back straight' . self::STYLE_SUFFIX;
        }

        // "TREE POSE yoga (vrikshasana): standing on ONE leg only, ..."
        return self::STYLE_PREFIX
            . Str::upper($pose['en']) . ' yoga (' . str_replace('_', ' ', $key) . '): '
            . $pose['scene']
            . self::STYLE_SUFFIX;
    }

    /** AI image fail ho to local SVG ka public path. */
    public static function fallbackSvg(string $key): string
    {
        $shape = self::get($key)['shape'] ?? 'sitting';

        return '/img/yoga/' . (in_array($shape, self::SHAPES, true) ? $shape : 'sitting') . '.svg';
    }
}
