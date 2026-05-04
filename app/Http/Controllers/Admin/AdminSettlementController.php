<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SportGame;
use App\Services\mall\BetSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminSettlementController extends Controller
{
    public function __construct(
        private readonly BetSettlementService $settlement,
    ) {}

    public function create(): View
    {
        $games = SportGame::query()
            ->where('status', SportGame::STATUS_OPEN)
            ->orderByDesc('id')
            ->get();

        return view('admin.settlement.create', ['games' => $games]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'game_id' => 'required|integer|min:1',
            'winning_selection_ids' => 'required|string',
        ]);

        $raw = trim((string) $v['winning_selection_ids']);
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $ids = array_map(static fn (string $s): int => (int) $s, $parts);
        $ids = array_values(array_filter($ids, static fn (int $i) => $i > 0));

        try {
            $this->settlement->applyGameResult((int) $v['game_id'], $ids);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.settlement.create')->withErrors(['settlement' => $e->getMessage()]);
        }

        return redirect()->route('admin.settlement.create')->with('status', 'Game settled.');
    }
}
