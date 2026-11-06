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

    /* ===================================================================
     |  🔮 Tithi / nakshatra / yog / karan / chandra rashi / maas — ganit se
     |  (Meeus "Astronomical Algorithms": Surya ch.25, Chandra ch.47; Lahiri ayanamsa).
     |  Panchang ka niyam: jo tithi/nakshatra SURYODAY ke samay chal raha ho wahi us din ka.
     * =================================================================== */

    private const TITHI = [
        'gujarati' => ['એકમ', 'બીજ', 'ત્રીજ', 'ચોથ', 'પાંચમ', 'છઠ', 'સાતમ', 'આઠમ', 'નોમ', 'દશમ', 'અગિયારસ', 'બારસ', 'તેરસ', 'ચૌદસ', 'પૂનમ', 'અમાસ'],
        'hindi'    => ['प्रतिपदा', 'द्वितीया', 'तृतीया', 'चतुर्थी', 'पंचमी', 'षष्ठी', 'सप्तमी', 'अष्टमी', 'नवमी', 'दशमी', 'एकादशी', 'द्वादशी', 'त्रयोदशी', 'चतुर्दशी', 'पूर्णिमा', 'अमावस्या'],
    ];
    private const NAKSHATRA = [
        'gujarati' => ['અશ્વિની', 'ભરણી', 'કૃત્તિકા', 'રોહિણી', 'મૃગશીર્ષ', 'આર્દ્રા', 'પુનર્વસુ', 'પુષ્ય', 'આશ્લેષા', 'મઘા', 'પૂર્વા ફાલ્ગુની', 'ઉત્તરા ફાલ્ગુની', 'હસ્ત', 'ચિત્રા', 'સ્વાતિ', 'વિશાખા', 'અનુરાધા', 'જ્યેષ્ઠા', 'મૂળ', 'પૂર્વાષાઢા', 'ઉત્તરાષાઢા', 'શ્રવણ', 'ધનિષ્ઠા', 'શતભિષા', 'પૂર્વા ભાદ્રપદ', 'ઉત્તરા ભાદ્રપદ', 'રેવતી'],
        'hindi'    => ['अश्विनी', 'भरणी', 'कृत्तिका', 'रोहिणी', 'मृगशिरा', 'आर्द्रा', 'पुनर्वसु', 'पुष्य', 'आश्लेषा', 'मघा', 'पूर्वा फाल्गुनी', 'उत्तरा फाल्गुनी', 'हस्त', 'चित्रा', 'स्वाती', 'विशाखा', 'अनुराधा', 'ज्येष्ठा', 'मूल', 'पूर्वाषाढ़ा', 'उत्तराषाढ़ा', 'श्रवण', 'धनिष्ठा', 'शतभिषा', 'पूर्वा भाद्रपद', 'उत्तरा भाद्रपद', 'रेवती'],
    ];
    private const YOGA = [
        'gujarati' => ['વિષ્કુંભ', 'પ્રીતિ', 'આયુષ્માન', 'સૌભાગ્ય', 'શોભન', 'અતિગંડ', 'સુકર્મા', 'ધૃતિ', 'શૂળ', 'ગંડ', 'વૃદ્ધિ', 'ધ્રુવ', 'વ્યાઘાત', 'હર્ષણ', 'વજ્ર', 'સિદ્ધિ', 'વ્યતિપાત', 'વરીયાન', 'પરિઘ', 'શિવ', 'સિદ્ધ', 'સાધ્ય', 'શુભ', 'શુક્લ', 'બ્રહ્મ', 'ઇન્દ્ર', 'વૈધૃતિ'],
        'hindi'    => ['विष्कुम्भ', 'प्रीति', 'आयुष्मान', 'सौभाग्य', 'शोभन', 'अतिगण्ड', 'सुकर्मा', 'धृति', 'शूल', 'गण्ड', 'वृद्धि', 'ध्रुव', 'व्याघात', 'हर्षण', 'वज्र', 'सिद्धि', 'व्यतीपात', 'वरीयान', 'परिघ', 'शिव', 'सिद्ध', 'साध्य', 'शुभ', 'शुक्ल', 'ब्रह्म', 'इन्द्र', 'वैधृति'],
    ];
    /** 0 = Kimstughna, 1-7 = chalit (Bava…Vishti), 8-10 = Shakuni, Chatushpad, Naag */
    private const KARANA = [
        'gujarati' => ['કિંસ્તુઘ્ન', 'બવ', 'બાલવ', 'કૌલવ', 'તૈતિલ', 'ગર', 'વણિજ', 'વિષ્ટિ', 'શકુનિ', 'ચતુષ્પાદ', 'નાગ'],
        'hindi'    => ['किंस्तुघ्न', 'बव', 'बालव', 'कौलव', 'तैतिल', 'गर', 'वणिज', 'विष्टि', 'शकुनि', 'चतुष्पद', 'नाग'],
    ];
    private const RASHI = [
        'gujarati' => ['મેષ', 'વૃષભ', 'મિથુન', 'કર્ક', 'સિંહ', 'કન્યા', 'તુલા', 'વૃશ્ચિક', 'ધન', 'મકર', 'કુંભ', 'મીન'],
        'hindi'    => ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन'],
    ];
    /** Chaitra se — Gujarat amanta (amas se amas), Hindi purnimanta. */
    private const MAAS = [
        'gujarati' => ['ચૈત્ર', 'વૈશાખ', 'જેઠ', 'અષાઢ', 'શ્રાવણ', 'ભાદરવો', 'આસો', 'કારતક', 'માગશર', 'પોષ', 'મહા', 'ફાગણ'],
        'hindi'    => ['चैत्र', 'वैशाख', 'ज्येष्ठ', 'आषाढ़', 'श्रावण', 'भाद्रपद', 'आश्विन', 'कार्तिक', 'मार्गशीर्ष', 'पौष', 'माघ', 'फाल्गुन'],
    ];

    /** Chandra longitude ke Meeus ch.47 terms: [D, M, M', F, coefficient (1e-6 deg)] */
    private const MOON_TERMS = [
        [0, 0, 1, 0, 6288774], [2, 0, -1, 0, 1274027], [2, 0, 0, 0, 658314], [0, 0, 2, 0, 213618],
        [0, 1, 0, 0, -185116], [0, 0, 0, 2, -114332], [2, 0, -2, 0, 58793], [2, -1, -1, 0, 57066],
        [2, 0, 1, 0, 53322], [2, -1, 0, 0, 45758], [0, 1, -1, 0, -40923], [1, 0, 0, 0, -34720],
        [0, 1, 1, 0, -30383], [2, 0, 0, -2, 15327], [0, 0, 1, 2, -12528], [0, 0, 1, -2, 10980],
        [4, 0, -1, 0, 10675], [0, 0, 3, 0, 10034], [4, 0, -2, 0, 8548], [2, 1, -1, 0, -7888],
        [2, 1, 0, 0, -6766], [1, 0, -1, 0, -5163], [1, 1, 0, 0, 4987], [2, -1, 1, 0, 4036],
        [2, 0, 2, 0, 3994], [4, 0, 0, 0, 3861], [2, 0, -3, 0, 3665], [0, 1, -2, 0, -2689],
        [2, 0, -1, 2, -2602], [2, -1, -2, 0, 2390], [1, 0, 1, 0, -2348], [2, -2, 0, 0, 2236],
        [0, 1, 2, 0, -2120], [0, 2, 0, 0, -2069], [2, -2, -1, 0, 2048], [2, 0, 1, -2, -1773],
        [2, 0, 0, 2, -1595], [4, -1, -1, 0, 1215], [0, 0, 2, 2, -1110], [3, 0, -1, 0, -892],
        [2, 1, 1, 0, -810], [4, -1, -2, 0, 759], [0, 2, -1, 0, -713], [2, 2, -1, 0, -700],
        [2, 1, -2, 0, 691], [2, -1, 0, -2, 596], [4, 0, 1, 0, 549], [0, 0, 4, 0, 537],
        [4, -1, 0, 0, 520], [1, 0, -2, 0, -487], [2, 1, 0, -2, -399], [0, 0, 2, -2, -381],
        [1, 1, 1, 0, 351], [3, 0, -2, 0, -340], [4, 0, -3, 0, 330], [2, -1, 2, 0, 327],
        [0, 2, 1, 0, -323], [1, 1, -1, 0, 299], [2, 0, 3, 0, 294],
    ];

    /**
     * Suryoday ke samay ka tithi, nakshatra, yog, karan, chandra rashi, maas, samvat —
     * aur tithi/nakshatra/yog kab tak (agla kaunsa) — bhasha ke hisaab se shabd.
     */
    public function astro(string $date, string $city = 'ahmedabad', string $language = 'gujarati'): array
    {
        $lang = $language === 'hindi' ? 'hindi' : 'gujarati';
        [, , $lat, $lon] = self::CITIES[$city] ?? self::CITIES['ahmedabad'];
        $day  = Carbon::parse($date, self::TZ)->startOfDay();
        $rise = $this->sun($day, $lat, $lon, 'sunrise');

        // Tithi (1-30): elongation / 12°
        $tithiAt = fn (float $ts) => (int) floor($this->elongation($ts) / 12);
        $ti      = $tithiAt($rise);
        $tEnd    = $this->nextChange($rise, $tithiAt);
        $paksha  = $ti < 15 ? 'sud' : 'vad';
        $tName   = fn (int $i) => self::TITHI[$lang][$i === 29 ? 15 : ($i % 15)];

        $nakAt = fn (float $ts) => (int) floor($this->sidereal($this->moonLon($ts), $ts) / (360 / 27));
        $ni    = $nakAt($rise);
        $nEnd  = $this->nextChange($rise, $nakAt);

        $yogAt = fn (float $ts) => (int) floor($this->norm($this->sidereal($this->moonLon($ts), $ts) + $this->sidereal($this->sunLon($ts), $ts)) / (360 / 27));
        $yi    = $yogAt($rise);

        $k  = (int) floor($this->elongation($rise) / 6); // 0-59
        $ki = $k === 0 ? 0 : ($k >= 57 ? $k - 49 : (($k - 1) % 7) + 1);

        $ri = (int) floor($this->sidereal($this->moonLon($rise), $rise) / 30);

        // Maas: amas se amas — jis rashi me Surya agle amas par ho (Mesh → Chaitra)
        $prevNm = $this->newMoon($rise, -1);
        $nextNm = $this->newMoon($rise, +1);
        $sunR1  = (int) floor($this->sidereal($this->sunLon($prevNm), $prevNm) / 30);
        $sunR2  = (int) floor($this->sidereal($this->sunLon($nextNm), $nextNm) / 30);
        $mi     = $sunR2;                       // 0 = Chaitra
        $adhik  = $sunR1 === $sunR2;            // is mahine koi sankranti nahi
        // Hindi (purnimanta): krishna paksha agle mahine ka hissa
        $shownMi = ($lang === 'hindi' && $paksha === 'vad') ? ($mi + 1) % 12 : $mi;
        $maas    = ($adhik ? ($lang === 'hindi' ? 'अधिक ' : 'અધિક ') : '') . self::MAAS[$lang][$shownMi];

        // Vikram samvat: Gujarat me Kartak sud 1 se naya saal
        $samvat = (int) $day->year + 56 + (($mi >= 7 && $mi <= 9 && $day->month >= 10) ? 1 : 0);

        return [
            'paksha'    => $paksha,
            'tithi'     => $this->withEnd($tName($ti), $tEnd, $tName(($ti + 1) % 30), $day, $lang),
            'nakshatra' => $this->withEnd(self::NAKSHATRA[$lang][$ni], $nEnd, self::NAKSHATRA[$lang][($ni + 1) % 27], $day, $lang),
            'yog'       => self::YOGA[$lang][$yi],
            'karan'     => self::KARANA[$lang][$ki],
            'chandra'   => self::RASHI[$lang][$ri],
            'maas'      => $maas,
            'samvat'    => ($lang === 'hindi' ? 'विक्रम संवत ' : 'વિક્રમ સંવત ') . $this->digits((string) $samvat, $lang),
            'is_amas'   => $ti === 29,
            'is_poonam' => $ti === 14,
        ];
    }

    /**
     * 🪔 Is din kaunsa tyohar (Rashifal festival key) — tithi + maas se, Gujarat ke hisaab se.
     * Dhanteras / Kali Chaudas / Diwali / Dev Diwali: jo tithi SAANJH (pradosh) ko ho.
     * Bestu Varas / Bhai Beej / Labh Pancham: jo tithi SURYODAY par ho. Kuch nahi to ''.
     */
    public function festival(string $date, string $city = 'ahmedabad'): string
    {
        [, , $lat, $lon] = self::CITIES[$city] ?? self::CITIES['ahmedabad'];
        $day  = Carbon::parse($date, self::TZ)->startOfDay();
        $rise = $this->sun($day, $lat, $lon, 'sunrise');
        $set  = $this->sun($day, $lat, $lon, 'sunset');

        $tiRise = (int) floor($this->elongation($rise) / 12); // 0 = sud ekam … 29 = amas
        $tiSet  = (int) floor($this->elongation($set) / 12);

        // Amanta maas (0 = Chaitra … 6 = Aso, 7 = Kartak) — saanjh ke samay ka
        $nextNm = $this->newMoon($set, +1);
        $mi     = (int) floor($this->sidereal($this->sunLon($nextNm), $nextNm) / 30);

        return match (true) {
            $mi === 6 && $tiSet === 29  => 'diwali',
            $mi === 6 && $tiSet === 28  => 'kali_chaudas',
            $mi === 6 && $tiSet === 27  => 'dhanteras',
            $mi === 7 && $tiRise === 0  => 'navu_varsh',
            $mi === 7 && $tiRise === 1  => 'bhai_beej',
            $mi === 7 && $tiRise === 4  => 'labh_pancham',
            $mi === 7 && $tiSet === 14  => 'dev_diwali',
            default                     => '',
        };
    }

    /** "ચૌદસ — બપોરે ૧:૦૫ સુધી, પછી અમાસ" */
    private function withEnd(string $name, ?float $end, string $next, Carbon $day, string $lang): string
    {
        if ($end === null) {
            return $name;
        }
        $at   = Carbon::createFromTimestamp((int) round($end), self::TZ);
        $h    = (int) $at->format('G');
        $part = $lang === 'hindi'
            ? ($h < 4 || $h >= 20 ? 'रात' : ($h < 12 ? 'सुबह' : ($h < 16 ? 'दोपहर' : 'शाम')))
            : ($h < 4 || $h >= 20 ? 'રાત્રે' : ($h < 12 ? 'સવારે' : ($h < 16 ? 'બપોરે' : 'સાંજે')));
        $when = ($at->isSameDay($day) ? '' : ($lang === 'hindi' ? 'कल ' : 'કાલે ')) . $part . ' ' . $this->digits($at->format('g:i'), $lang);

        return $lang === 'hindi'
            ? "{$name} — {$when} तक, फिर {$next}"
            : "{$name} — {$when} સુધી, પછી {$next}";
    }

    private function digits(string $s, string $lang): string
    {
        return strtr($s, array_combine(range(0, 9), $lang === 'hindi'
            ? ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९']
            : ['૦', '૧', '૨', '૩', '૪', '૫', '૬', '૭', '૮', '૯']));
    }

    /** $fn ki value kab badalti hai (48 ghante tak) — 20 min ke kadam, phir binary search. */
    private function nextChange(float $from, callable $fn): ?float
    {
        $v = $fn($from);
        for ($t = $from + 1200; $t <= $from + 172800; $t += 1200) {
            if ($fn($t) !== $v) {
                $lo = $t - 1200;
                $hi = $t;
                while ($hi - $lo > 20) {
                    $mid = ($lo + $hi) / 2;
                    $fn($mid) === $v ? $lo = $mid : $hi = $mid;
                }

                return $hi;
            }
        }

        return null;
    }

    /** Pichhla (-1) / agla (+1) amas — elongation 0 hone ka samay. */
    public function newMoon(float $ts, int $dir): float
    {
        $e = $this->elongation($ts);
        $t = $ts + ($dir < 0 ? -$e : 360 - $e) / 12.1907 * 86400;
        for ($i = 0; $i < 6; $i++) {
            $d = $this->elongation($t);
            $d = $d > 180 ? $d - 360 : $d;
            $t -= $d / 12.1907 * 86400;
        }

        return $t;
    }

    /** Chandra − Surya (0-360). */
    public function elongation(float $ts): float
    {
        return $this->norm($this->moonLon($ts) - $this->sunLon($ts));
    }

    private function sidereal(float $lon, float $ts): float
    {
        return $this->norm($lon - $this->ayanamsa($ts));
    }

    /** Lahiri ayanamsa (J2000 par 23.8531°, ~50.29"/varsh). */
    private function ayanamsa(float $ts): float
    {
        return 23.8531 + ($this->jd($ts) - 2451545.0) / 365.25 * (50.2788 / 3600);
    }

    private function jd(float $ts): float
    {
        return $ts / 86400 + 2440587.5 + 70 / 86400; // +ΔT (~70 sec)
    }

    private function centuries(float $ts): float
    {
        return ($this->jd($ts) - 2451545.0) / 36525;
    }

    /** Surya ki apparent longitude (Meeus ch.25). */
    public function sunLon(float $ts): float
    {
        $T  = $this->centuries($ts);
        $L0 = 280.46646 + 36000.76983 * $T + 0.0003032 * $T * $T;
        $M  = deg2rad(357.52911 + 35999.05029 * $T - 0.0001537 * $T * $T);
        $C  = (1.914602 - 0.004817 * $T - 0.000014 * $T * $T) * sin($M) + (0.019993 - 0.000101 * $T) * sin(2 * $M) + 0.000289 * sin(3 * $M);
        $om = deg2rad(125.04 - 1934.136 * $T);

        return $this->norm($L0 + $C - 0.00569 - 0.00478 * sin($om));
    }

    /** Chandra ki apparent longitude (Meeus ch.47). */
    public function moonLon(float $ts): float
    {
        $T  = $this->centuries($ts);
        $Lp = 218.3164477 + 481267.88123421 * $T - 0.0015786 * $T ** 2 + $T ** 3 / 538841 - $T ** 4 / 65194000;
        $D  = 297.8501921 + 445267.1114034 * $T - 0.0018819 * $T ** 2 + $T ** 3 / 545868 - $T ** 4 / 113065000;
        $M  = 357.5291092 + 35999.0502909 * $T - 0.0001536 * $T ** 2 + $T ** 3 / 24490000;
        $Mp = 134.9633964 + 477198.8675055 * $T + 0.0087414 * $T ** 2 + $T ** 3 / 69699 - $T ** 4 / 14712000;
        $F  = 93.2720950 + 483202.0175233 * $T - 0.0036539 * $T ** 2 - $T ** 3 / 3526000 + $T ** 4 / 863310000;
        $E  = 1 - 0.002516 * $T - 0.0000074 * $T ** 2;
        $A1 = 119.75 + 131.849 * $T;
        $A2 = 53.09 + 479264.290 * $T;

        $sum = 0.0;
        foreach (self::MOON_TERMS as [$d, $m, $mp, $f, $c]) {
            $arg = deg2rad($d * $D + $m * $M + $mp * $Mp + $f * $F);
            $sum += $c * (abs($m) === 1 ? $E : (abs($m) === 2 ? $E * $E : 1)) * sin($arg);
        }
        $sum += 3958 * sin(deg2rad($A1)) + 1962 * sin(deg2rad($Lp - $F)) + 318 * sin(deg2rad($A2));

        $om = deg2rad(125.04452 - 1934.136261 * $T);

        return $this->norm($Lp + $sum / 1e6 - 0.00478 * sin($om)); // + nutation (mukhya hissa)
    }

    private function norm(float $deg): float
    {
        $deg = fmod($deg, 360.0);

        return $deg < 0 ? $deg + 360 : $deg;
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
