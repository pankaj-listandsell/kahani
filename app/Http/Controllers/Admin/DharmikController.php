<?php

namespace App\Http\Controllers\Admin;

use App\Models\Story;
use App\Services\ViralStudioAiService;
use Illuminate\Http\Request;

/**
 * 🙏 Dharmik Card Studio — "11 Dhan Akarshan Mantra" (list), "20 Niyam" (2-column grid),
 * "Shivling par mathu kem?" (article) jaise 4:5 dharmik text cards.
 * Save / show / delete / reel / auto-post Salah wala hi pipeline; bas AI aur design alag.
 */
class DharmikController extends SalahController
{
    protected string $type = 'dharmik';
    protected string $route = 'admin.dharmik';
    protected string $view = 'admin.dharmik';
    protected string $label = 'Dharmik Card';

    public function index()
    {
        $query = Story::withCount('cards')->where('type', $this->type)->latest();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return view($this->view . '.index', [
            'collections' => $query->get(),
            'categories'  => ViralStudioAiService::DHARMIK_CATEGORIES,
            'todayDev'    => ViralStudioAiService::DHARMIK_WEEKDAY_DEV[now()->dayOfWeekIso],
        ]);
    }

    public function generate(Request $request, ViralStudioAiService $ai)
    {
        $data = $request->validate([
            'count'    => ['required', 'integer', 'min:1', 'max:10'],
            'language' => ['required', 'in:gujarati,hindi,hinglish'],
            'template' => ['required', 'in:mix,list,grid,article'],
            'category' => ['required', 'string', 'max:30'],
            'topic'    => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $items = $ai->generateDharmik(
                (int) $data['count'],
                $data['language'],
                $data['template'],
                $data['category'],
                trim((string) ($data['topic'] ?? '')),
                $this->recentTitles()
            );

            return response()->json(['ok' => true, 'items' => $items]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Save ke baad card titles description me rakho — agli baar AI inhe repeat na kare. */
    public function save(Request $request)
    {
        $request->validate([
            'titles'   => ['nullable', 'array', 'max:30'],
            'titles.*' => ['nullable', 'string', 'max:200'],
        ]);

        $res = parent::save($request);

        $id = $res->getData(true)['collection'] ?? null;
        $titles = array_filter(array_map('trim', (array) $request->input('titles', [])));
        if ($id && $titles) {
            Story::whereKey($id)->update(['description' => implode("\n", $titles)]);
        }

        return $res;
    }

    /** Pichhli collections ke titles (naye pehle) — AI ko "ye mat dohrao" list. */
    protected function recentTitles(): array
    {
        return Story::where('type', $this->type)
            ->where('user_id', auth()->id())
            ->whereNotNull('description')
            ->latest()->take(40)
            ->pluck('description')
            ->flatMap(fn ($d) => explode("\n", (string) $d))
            ->map(fn ($t) => trim($t))->filter()->unique()
            ->take(80)->values()->all();
    }
}
