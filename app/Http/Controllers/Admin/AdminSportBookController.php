<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SportEvent;
use App\Models\SportMarket;
use App\Models\SportSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSportBookController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $selections = SportSelection::query()
            ->with(['market.event'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return view('admin.sport-book.index', ['selections' => $selections]);
    }

    public function create(): View
    {
        return view('admin.sport-book.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'event_name' => 'required|string|max:500',
            'starts_at' => 'nullable|integer|min:0',
            'market_type' => 'required|string|max:128',
            'selection_label' => 'required|string|max:256',
            'current_odds_millis' => 'required|integer|min:1000',
        ]);

        $startsAt = (int) ($v['starts_at'] ?? SportEvent::nowMillis());
        $event = new SportEvent([
            'name' => $v['event_name'],
            'starts_at' => $startsAt,
            'status' => SportEvent::STATUS_OPEN,
        ]);
        $event->save();

        $market = new SportMarket([
            'event_id' => $event->id,
            'market_type' => $v['market_type'],
            'status' => SportMarket::STATUS_OPEN,
        ]);
        $market->save();

        $selection = new SportSelection([
            'market_id' => $market->id,
            'label' => $v['selection_label'],
            'current_odds_millis' => (int) $v['current_odds_millis'],
            'status' => SportSelection::STATUS_OPEN,
        ]);
        $selection->save();

        return redirect()->route('admin.sport-book.index')->with('status', 'Fixture created (event #'.$event->id.', selection #'.$selection->id.').');
    }
}
