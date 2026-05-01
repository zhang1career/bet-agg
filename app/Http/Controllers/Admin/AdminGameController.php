<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SportGame;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminGameController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $games = SportGame::query()
            ->withCount('markets')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.games.index', ['games' => $games]);
    }

    public function create(): View
    {
        return view('admin.games.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'raw_id' => 'required|integer|min:1|unique:biz_game,raw_id',
            'status' => 'required|integer|in:1,2,3',
        ]);

        $game = new SportGame([
            'raw_id' => (int) $v['raw_id'],
            'status' => (int) $v['status'],
        ]);
        $game->save();

        return redirect()->route('admin.games.show', $game)->with('status', 'Game registered.');
    }

    public function show(SportGame $game): View
    {
        $game->load([
            'markets' => static fn ($q) => $q->withCount('selections')->orderByDesc('id'),
        ]);

        return view('admin.games.show', ['game' => $game]);
    }

    public function edit(SportGame $game): View
    {
        return view('admin.games.edit', ['game' => $game]);
    }

    public function update(Request $request, SportGame $game): RedirectResponse
    {
        $v = $request->validate([
            'status' => 'required|integer|in:1,2,3',
        ]);

        $game->fill([
            'status' => (int) $v['status'],
        ]);
        $game->save();

        return redirect()->route('admin.games.show', $game)->with('status', 'Game updated.');
    }

    public function destroy(SportGame $game): RedirectResponse
    {
        if ($game->markets()->exists()) {
            return redirect()
                ->route('admin.games.show', $game)
                ->withErrors(['delete' => 'Delete or reassign all markets for this game first.']);
        }

        $game->delete();

        return redirect()->route('admin.games.index')->with('status', 'Game deleted.');
    }
}
