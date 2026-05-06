<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Enums\MarketStatus;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Market;
use App\Models\Selection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminMarketController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $q = Market::query()
            ->with('game')
            ->withCount('selections')
            ->orderByDesc('id');

        if ($request->filled('game_id')) {
            $q->where('game_id', (int) $request->query('game_id'));
        }

        $markets = $q->paginate($perPage)->withQueryString();
        $filterGameId = $request->filled('game_id') ? (int) $request->query('game_id') : null;

        return view('admin.markets.index', [
            'markets' => $markets,
            'filterGameId' => $filterGameId,
        ]);
    }

    public function create(Request $request): View
    {
        $games = Game::query()->orderByDesc('id')->limit(500)->get();
        $prefillGameId = max(0, (int) $request->query('game_id', 0));

        return view('admin.markets.create', [
            'games' => $games,
            'prefillGameId' => $prefillGameId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'game_id' => 'required|integer|exists:biz_game,id',
            'name' => 'string|max:256',
            'status' => ['required', 'integer', Rule::enum(MarketStatus::class)],
            'selections' => 'required|array|min:1',
            'selections.*.label' => 'required|string|max:256',
            'selections.*.current_odds_millis' => 'required|integer|min:1000',
            'selections.*.status' => ['required', 'integer', Rule::enum(MarketStatus::class)],
        ]);

        /** @var Market $market */
        $market = DB::transaction(static function () use ($v): Market {
            $market = new Market([
                'game_id' => (int) $v['game_id'],
                'name' => trim($v['name']),
                'status' => (int) $v['status'],
            ]);
            $market->save();

            foreach ($v['selections'] as $row) {
                $sel = new Selection([
                    'market_id' => $market->id,
                    'label' => $row['label'],
                    'current_odds_millis' => (int) $row['current_odds_millis'],
                    'status' => (int) $row['status'],
                ]);
                $sel->save();
            }

            return $market;
        });

        return redirect()->route('admin.markets.show', $market)->with('status', 'Market and selections created.');
    }

    public function show(Market $market): View
    {
        $market->load([
            'game',
            'selections' => static fn ($q) => $q->orderBy('id'),
        ]);

        return view('admin.markets.show', ['market' => $market]);
    }

    public function edit(Market $market): View
    {
        $market->load([
            'game',
            'selections' => static fn ($q) => $q->orderBy('id'),
        ]);
        $games = Game::query()->orderByDesc('id')->limit(500)->get();

        return view('admin.markets.edit', [
            'market' => $market,
            'games' => $games,
        ]);
    }

    public function update(Request $request, Market $market): RedirectResponse
    {
        $v = $request->validate([
            'game_id' => 'required|integer|exists:biz_game,id',
            'name' => 'string|max:256',
            'status' => ['required', 'integer', Rule::enum(MarketStatus::class)],
        ]);

        $market->fill([
            'game_id' => (int) $v['game_id'],
            'name' => trim($v['name']),
            'status' => (int) $v['status'],
        ]);
        $market->save();

        return redirect()->route('admin.markets.show', $market)->with('status', 'Market updated.');
    }

    public function destroy(Market $market): RedirectResponse
    {
        DB::transaction(static function () use ($market): void {
            Selection::query()->where('market_id', $market->id)->delete();
            $market->delete();
        });

        return redirect()->route('admin.markets.index')->with('status', 'Market and its selections deleted.');
    }

    public function storeSelection(Request $request, Market $market): RedirectResponse
    {
        $v = $request->validate([
            'label' => 'required|string|max:256',
            'current_odds_millis' => 'required|integer|min:1000',
            'selection_status' => ['required', 'integer', Rule::enum(MarketStatus::class)],
        ]);

        $sel = new Selection([
            'market_id' => $market->id,
            'label' => $v['label'],
            'current_odds_millis' => (int) $v['current_odds_millis'],
            'status' => (int) $v['selection_status'],
        ]);
        $sel->save();

        return back()->with('status', 'Selection added.');
    }

    public function updateSelection(Request $request, Market $market, Selection $selection): RedirectResponse
    {
        abort_unless($selection->market_id === $market->id, 404);

        $v = $request->validate([
            'label' => 'required|string|max:256',
            'current_odds_millis' => 'required|integer|min:1000',
            'status' => ['required', 'integer', Rule::enum(MarketStatus::class)],
        ]);

        $selection->fill([
            'label' => $v['label'],
            'current_odds_millis' => (int) $v['current_odds_millis'],
            'status' => (int) $v['status'],
        ]);
        $selection->save();

        return back()->with('status', 'Selection updated.');
    }

    public function destroySelection(Market $market, Selection $selection): RedirectResponse
    {
        abort_unless($selection->market_id === $market->id, 404);
        $selection->delete();

        return back()->with('status', 'Selection deleted.');
    }
}
