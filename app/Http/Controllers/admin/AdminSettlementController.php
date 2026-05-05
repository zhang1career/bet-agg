<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

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
            'winning_selection_ids' => 'nullable|string',
            'voided_selection_ids' => 'nullable|string',
        ]);

        $winners = self::parseIdList((string) ($v['winning_selection_ids'] ?? ''));
        $voids = self::parseIdList((string) ($v['voided_selection_ids'] ?? ''));
        if ($winners === [] && $voids === []) {
            return redirect()->route('admin.settlement.create')
                ->withErrors(['settlement' => 'Provide at least one winning or voided selection id.']);
        }

        try {
            $result = $this->settlement->applyGameResult((int) $v['game_id'], $winners, $voids);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.settlement.create')->withErrors(['settlement' => $e->getMessage()]);
        }

        $message = sprintf(
            'Game settled. job_id=%d total=%d success=%d failure=%d status=%d',
            $result->jobId,
            $result->total,
            $result->successCount,
            $result->failureCount,
            $result->status->value,
        );

        return redirect()->route('admin.settlement.create')->with('status', $message);
    }

    /**
     * @return list<int>
     */
    private static function parseIdList(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $trimmed)));
        $ids = array_map(static fn (string $s): int => (int) $s, $parts);

        return array_values(array_filter($ids, static fn (int $i) => $i > 0));
    }
}
