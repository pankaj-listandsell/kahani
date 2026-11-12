<?php

namespace App\Http\Controllers\Admin;

/**
 * 🔞 Adult Kahani (1 Card) — ek hi 9:16 card par poori romantic / affair / pehli-raat wali kahani.
 * Salah Studio jaisa hi pipeline (generate → canvas card → save → reel / auto-post), bas alag menu,
 * alag type ('romance') aur hamesha 'kahani' style. Intimacy sirf ishaare me (fade-to-black).
 */
class RomanceController extends SalahController
{
    protected string $type = 'romance';
    protected string $route = 'admin.romance';
    protected string $view = 'admin.romance';
    protected string $label = 'Adult Kahani';
    protected ?string $forcedStyle = 'kahani';
}
