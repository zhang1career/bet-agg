<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Enums\MarketStatus;
use App\Enums\MarketType;
use App\Http\Controllers\Controller;
use App\Services\mall\MarketAdminService;
use App\Support\AdminGameSelectOptionLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminMarketController extends Controller
{
    public function __construct(
        private readonly MarketAdminService $marketAdmin,
        private readonly AdminGameSelectOptionLabels $gameSelectLabels,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $markets = $this->marketAdmin->paginateIndex($perPage);
        $games = $this->marketAdmin->listGamesForSelect();

        $mallCreate = $request->boolean('mall_create');
        $mallEditId = (int) $request->query('mall_edit', 0);
        $prefillGameId = (int) $request->query('game_id', 0);

        return view('admin.markets.index', [
            'markets' => $markets,
            'cmsByRawId' => $this->marketAdmin->cmsByRawIdsForMarkets($markets->getCollection()),
            'games' => $games,
            'gameSelectLabels' => $this->gameSelectLabels->mapByLocalId($games),
            'mallCreate' => $mallCreate,
            'modalMarket' => $mallEditId >= 1 ? $this->marketAdmin->findForModal($mallEditId) : null,
            'prefillGameId' => $prefillGameId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'game_id' => 'required|integer|exists:biz_game,id',
            'name' => 'string|max:256',
            'type' => ['required', 'integer', Rule::enum(MarketType::class)],
            'status' => ['required', 'integer', Rule::enum(MarketStatus::class)],
        ]);

        $this->marketAdmin->create(
            (int) $v['game_id'],
            MarketType::from((int) $v['type']),
            trim((string) $v['name']) !== '' ? trim((string) $v['name']) : '1X2',
            MarketStatus::from((int) $v['status']),
        );

        return redirect()->route('admin.markets.index')->with('status', 'Market created.');
    }

    public function show(int $id): View
    {
        return view('admin.markets.show', $this->marketAdmin->showViewData($id));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'game_id' => 'required|integer|exists:biz_game,id',
            'name' => 'string|max:256',
            'type' => ['required', 'integer', Rule::enum(MarketType::class)],
            'status' => ['required', 'integer', Rule::enum(MarketStatus::class)],
        ]);

        $existing = $this->marketAdmin->findForModal($id);
        $fallbackName = $existing !== null ? $existing->name : '1X2';

        $this->marketAdmin->update(
            $id,
            (int) $v['game_id'],
            MarketType::from((int) $v['type']),
            trim((string) $v['name']) !== '' ? trim((string) $v['name']) : $fallbackName,
            MarketStatus::from((int) $v['status']),
        );

        return redirect()->route('admin.markets.index')->with('status', 'Market updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->marketAdmin->delete($id);

        return redirect()->route('admin.markets.index')->with('status', 'Market deleted.');
    }
}
