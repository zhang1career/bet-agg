<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\mall\BetSettlementService;
use App\Support\AdminGameSelectOptionLabels;
use App\Support\SettlementPayloadResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminSettlementController extends Controller
{
    public function __construct(
        private readonly AdminGameSelectOptionLabels $gameSelectLabels,
        private readonly SettlementPayloadResolver $payloadResolver,
    ) {}

    public function create(): View
    {
        $games = Game::query()
            ->where('status', Game::STATUS_OPEN)
            ->whereNotNull('side_a_subject_id')
            ->whereNotNull('side_b_subject_id')
            ->with(['sideASubject', 'sideBSubject'])
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $outcomesByGame = [];
        foreach ($games as $game) {
            $outcomesByGame[(string) $game->id] = $this->outcomeOptionsForGame($game);
        }

        return view('admin.settlement.create', [
            'games' => $games,
            'gameSelectLabels' => $this->gameSelectLabels->mapByLocalId($games),
            'outcomesByGame' => $outcomesByGame,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'game_id' => ['required', 'integer', 'exists:biz_game,id'],
            'result_payload' => ['required', 'string', 'max:128'],
        ]);

        $game = Game::query()->whereKey((int) $v['game_id'])->firstOrFail();

        if ($game->status !== Game::STATUS_OPEN) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['game_id' => 'Only open games can be queued for settlement.']);
        }

        try {
            $resolved = $this->payloadResolver->winnerAndVoidOutcomeCodes(
                $game,
                (string) $v['result_payload'],
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['result_payload' => $e->getMessage()]);
        }

        $gameId = (int) $game->id;
        $winners = $resolved['winners'];
        $voids = $resolved['voids'];

        dispatch(static function () use ($gameId, $winners, $voids): void {
            app(BetSettlementService::class)->applyGameResult($gameId, $winners, $voids);
        });

        return redirect()
            ->route('admin.settlement.create')
            ->with('status', 'Settlement queued for processing.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function outcomeOptionsForGame(Game $game): array
    {
        $aId = (int) $game->side_a_subject_id;
        $bId = (int) $game->side_b_subject_id;
        $aName = $game->sideASubject?->name ?? 'Side A';
        $bName = $game->sideBSubject?->name ?? 'Side B';

        return [
            [
                'value' => 'subject:'.$aId,
                'label' => 'Home win — '.$aName,
            ],
            [
                'value' => 'draw',
                'label' => 'Draw',
            ],
            [
                'value' => 'subject:'.$bId,
                'label' => 'Away win — '.$bName,
            ],
            [
                'value' => 'void_all',
                'label' => 'Void all (refund)',
            ],
        ];
    }
}
