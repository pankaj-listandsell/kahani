<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * 📅 Panchang ke woh hisse jo pakke ganit se nikalte hain — sunrise/sunset (PHP
 * date_sun_info), aur unse choghadiya, rahu kaal, abhijit muhurat.
 *
 * Tithi / nakshatra / yog / karan / shubh muhurat yahan NAHI — wo user calendar
 * se bharta hai (AI ya andaaz se galat ho jaate hain, aur galat muhurat se bharosa jaata hai).
 */
class PanchangService
{
    public const TZ = 'Asia/Kolkata';

    /** key => [Gujarati naam, Hindi naam, lat, lon] */
    public const CITIES = [
        'ahmedabad'   => ['અમદાવાદ', 'अहमदाबाद', 23.0225, 72.5714],
        'surat'       => ['સુરત', 'सूरत', 21.1702, 72.8311],
        'vadodara'    => ['વડોદરા', 'वडोदरा', 22.3072, 73.1812],
        'rajkot'      => ['રાજકોટ', 'राजकोट', 22.3039, 70.8022],
        'bhavnagar'   => ['ભાવનગર', 'भावनगर', 21.7645, 72.1519],
        'jamnagar'    => ['જામનગર', 'जामनगर', 22.4707, 70.0577],
        'junagadh'    => ['જૂનાગઢ', 'जूनागढ़', 21.5222, 70.4579],
        'gandhinagar' => ['ગાંધીનગર', 'गांधीनगर', 23.2156, 72.6369],
        'mehsana'     => ['મહેસાણા', 'मेहसाणा', 23.5880, 72.3693],
        'bhuj'        => ['ભુજ', 'भुज', 23.2420, 69.6669],
        'mumbai'      => ['મુંબઈ', 'मुंबई', 19.0760, 72.8777],
    ];

    /** Din ka pehla choghadiya (0 = Ravivar … 6 = Shanivar), phir DAY_CYCLE kram me. */
    private const DAY_START = ['udveg', 'amrit', 'rog', 'labh', 'shubh', 'chal', 'kaal'];
    private const DAY_CYCLE = ['udveg', 'chal', 'labh', 'amrit', 'kaal', 'shubh', 'rog'];

    /** Raat ka pehla choghadiya, phir NIGHT_CYCLE kram me. */
    private const NIGHT_START = ['shubh', 'chal', 'kaal', 'udveg', 'amrit', 'rog', 'labh'];
    private const NIGHT_CYCLE = ['shubh', 'amrit', 'chal', 'rog', 'kaal', 'labh', 'udveg'];

    /** Din ke 8 hisse me Rahu kaal kaunsa hissa (1-8), vaar ke hisaab se (0 = Ravivar). */
    private const RAHU_PART = [8, 2, 7, 5, 6, 4, 3];

    /** Shubh choghadiya (baaki: chal = saamanya, udveg/rog/kaal = ashubh). */
    public const GOOD = ['amrit', 'shubh', 'labh'];

    /**
     * @return array{date:string, weekday:int, city:string, sunrise:string, sunset:string,
     *   next_sunrise:string, rahu:array, abhijit:array, day:list<array>, night:list<array>}
     */
    public function compute(string $date, string $city = 'ahmedabad'): array
    {
        [, , $lat, $lon] = self::CITIES[$city] ?? self::CITIES['ahmedabad'];

        $day  = Carbon::parse($date, self::TZ)->startOfDay();
        $rise = $this->sun($day, $lat, $lon, 'sunrise');
        $set  = $this->sun($day, $lat, $lon, 'sunset');
        $next = $this->sun($day->copy()->addDay(), $lat, $lon, 'sunrise');
        $wd   = $day->dayOfWeek; // 0 = Ravivar

        $dayLen   = $set - $rise;
        $nightLen = $next - $set;

        $rahuPart = self::RAHU_PART[$wd] - 1;
        $mid      = $rise + $dayLen / 2;
        $muhurta  = $dayLen / 15;

        return [
            'date'         => $day->toDateString(),
            'weekday'      => $wd,
            'city'         => array_key_exists($city, self::CITIES) ? $city : 'ahmedabad',
            'sunrise'      => $this->hm($rise),
            'sunset'       => $this->hm($set),
            'next_sunrise' => $this->hm($next),
            'rahu'         => ['from' => $this->hm($rise + $dayLen / 8 * $rahuPart), 'to' => $this->hm($rise + $dayLen / 8 * ($rahuPart + 1))],
            // Abhijit = din ke 15 muhurat me 8va (madhyahn ke dono taraf aadha-aadha)
            'abhijit'      => ['from' => $this->hm($mid - $muhurta / 2), 'to' => $this->hm($mid + $muhurta / 2), 'wednesday' => $wd === 3],
            'day'          => $this->slots($rise, $dayLen, self::DAY_START[$wd], self::DAY_CYCLE),
            'night'        => $this->slots($set, $nightLen, self::NIGHT_START[$wd], self::NIGHT_CYCLE),
        ];
    }

    /** 8 barabar choghadiya: [name, from, to, good]. */
    private function slots(float $start, float $len, string $first, array $cycle): array
    {
        $i   = array_search($first, $cycle, true);
        $out = [];
        for ($k = 0; $k < 8; $k++) {
            $name  = $cycle[($i + $k) % 7];
            $out[] = [
                'name' => $name,
                'from' => $this->hm($start + $len / 8 * $k),
                'to'   => $this->hm($start + $len / 8 * ($k + 1)),
                'good' => in_array($name, self::GOOD, true) ? 1 : ($name === 'chal' ? 0 : -1),
            ];
        }

        return $out;
    }

    /** Us din ka sunrise/sunset (unix seconds). */
    private function sun(Carbon $day, float $lat, float $lon, string $key): float
    {
        $info = date_sun_info($day->copy()->setTime(12, 0)->timestamp, $lat, $lon);

        return (float) $info[$key];
    }

    private function hm(float $ts): string
    {
        return Carbon::createFromTimestamp((int) round($ts), self::TZ)->format('H:i');
    }
}
