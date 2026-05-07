<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Enums\MarketStatus;
use App\Enums\MarketType;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Market;
use App\Support\AdminGameSelectOptionLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminMarketController extends Controller
{
    public function __construct(
        private readonly AdminGameSelectOptionLabels $gameSelectLabels,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $markets = Market::query()
            ->with('game')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.markets.index', [
            'markets' => $markets,
        ]);
    }

    public function create(Request $request): View
    {
        $games = Game::query()->orderByDesc('id')->limit(500)->get();
        $prefillGameId = max(0, (int) $request->query('game_id', 0));

        return view('admin.markets.create', [
            'games' => $games,
            'gameSelectLabels' => $this->gameSelectLabels->mapByLocalId($games),
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
            'home_odds_millis' => 'required|integer|min:1000',
            'draw_odds_millis' => 'required|integer|min:1000',
            'away_odds_millis' => 'required|integer|min:1000',
        ]);

        $odds = Market::oneX2OddsMillisJson(
            (int) $v['home_odds_millis'],
            (int) $v['draw_odds_millis'],
            (int) $v['away_odds_millis'],
        );

        $market = new Market([
            'game_id' => (int) $v['game_id'],
            'type' => MarketType::from((int) $v['type']),
            'name' => trim((string) $v['name']) !== '' ? trim((string) $v['name']) : '胜平负',
            'status' => MarketStatus::from((int) $v['status'])->value,
            'odds_millis' => $odds,
        ]);
        $market->save();

        return redirect()->route('admin.markets.index')->with('status', 'Market created.');
    }

    public function show(Market $market): View
    {
        $market->load(['game.sideASubject', 'game.sideBSubject']);

        return view('admin.markets.show', ['market' => $market]);
    }

    public function edit(Market $market): View
    {
        $market->load('game');
        $games = Game::query()->orderByDesc('id')->limit(500)->get();

        return view('admin.markets.edit', [
            'market' => $market,
            'games' => $games,
            'gameSelectLabels' => $this->gameSelectLabels->mapByLocalId($games),
        ]);
    }

    public function update(Request $request, Market $market): RedirectResponse
    {
        $v = $request->validate([
            'game_id' => 'required|integer|exists:biz_game,id',
            'name' => 'string|max:256',
            'type' => ['required', 'integer', Rule::enum(MarketType::class)],
            'status' => ['required', 'integer', Rule::enum(MarketStatus::class)],
            'home_odds_millis' => 'required|integer|min:1000',
            'draw_odds_millis' => 'required|integer|min:1000',
            'away_odds_millis' => 'required|integer|min:1000',
        ]);

        $odds = Market::oneX2OddsMillisJson(
            (int) $v['home_odds_millis'],
            (int) $v['draw_odds_millis'],
            (int) $v['away_odds_millis'],
        );

        $market->fill([
            'game_id' => (int) $v['game_id'],
            'type' => MarketType::from((int) $v['type']),
            'name' => trim((string) $v['name']) !== '' ? trim((string) $v['name']) : $market->name,
            'status' => MarketStatus::from((int) $v['status'])->value,
            'odds_millis' => $odds,
        ]);
        $market->save();

        return redirect()->route('admin.markets.index')->with('status', 'Market updated.');
    }

    public function destroy(Market $market): RedirectResponse
    {
        $market->delete();

        return redirect()->route('admin.markets.index')->with('status', 'Market deleted.');
    }
}
