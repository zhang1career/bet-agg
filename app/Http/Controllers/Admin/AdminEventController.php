<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SportEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminEventController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $events = SportEvent::query()
            ->withCount('markets')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.events.index', ['events' => $events]);
    }

    public function create(): View
    {
        return view('admin.events.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:500',
            'starts_at' => 'nullable|integer|min:0',
            'status' => 'required|integer|in:1,2,3',
        ]);

        $event = new SportEvent([
            'name' => $v['name'],
            'starts_at' => (int) ($v['starts_at'] ?? SportEvent::nowMillis()),
            'status' => (int) $v['status'],
        ]);
        $event->save();

        return redirect()->route('admin.events.show', $event)->with('status', 'Event created.');
    }

    public function show(SportEvent $event): View
    {
        $event->load([
            'markets' => static fn ($q) => $q->withCount('selections')->orderByDesc('id'),
        ]);

        return view('admin.events.show', ['event' => $event]);
    }

    public function edit(SportEvent $event): View
    {
        return view('admin.events.edit', ['event' => $event]);
    }

    public function update(Request $request, SportEvent $event): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:500',
            'starts_at' => 'nullable|integer|min:0',
            'status' => 'required|integer|in:1,2,3',
        ]);

        $event->fill([
            'name' => $v['name'],
            'starts_at' => (int) ($v['starts_at'] ?? $event->starts_at),
            'status' => (int) $v['status'],
        ]);
        $event->save();

        return redirect()->route('admin.events.show', $event)->with('status', 'Event updated.');
    }

    public function destroy(SportEvent $event): RedirectResponse
    {
        if ($event->markets()->exists()) {
            return redirect()
                ->route('admin.events.show', $event)
                ->withErrors(['delete' => 'Delete or reassign all markets for this event first.']);
        }

        $event->delete();

        return redirect()->route('admin.events.index')->with('status', 'Event deleted.');
    }
}
