<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\mall\BetSettlementService;
use App\Services\mall\GameAdminService;
use App\Support\SettlementPayloadResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AdminSettlementController extends Controller
{
    public function __construct(
        private readonly GameAdminService $gameAdmin,
        private readonly SettlementPayloadResolver $payloadResolver,
        private readonly BetSettlementService $settlementService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'game_id' => ['required', 'integer', 'exists:biz_game,id'],
            'result_payload' => ['required', 'string', 'max:128'],
        ]);

        $game = $this->gameAdmin->findForSettlement((int) $v['game_id']);

        if ($game->status !== Game::STATUS_OPEN) {
            return redirect()
                ->route('admin.games.index', ['mall_settlement' => 1])
                ->withInput()
                ->withErrors(['game_id' => __('console.settlement.only_open')]);
        }

        try {
            $resolved = $this->payloadResolver->winnerAndVoidOutcomeCodes(
                $game,
                (string) $v['result_payload'],
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.games.index', ['mall_settlement' => 1])
                ->withInput()
                ->withErrors(['result_payload' => $e->getMessage()]);
        }

        $gameId = (int) $game->id;
        $winners = $resolved['winners'];
        $voids = $resolved['voids'];

        try {
            $this->settlementService->recordPendingSettlement($gameId, $winners, $voids);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.games.index', ['mall_settlement' => 1])
                ->withInput()
                ->withErrors(['result_payload' => $e->getMessage()]);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.games.index', ['mall_settlement' => 1])
                ->withInput()
                ->withErrors(['game_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.games.index')
            ->with('status', __('console.settlement.status_recorded'));
    }
}
