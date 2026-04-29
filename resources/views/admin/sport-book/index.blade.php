@extends('layouts.app')

@section('title', 'Sport book')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Selections</h2>
        <a href="{{ route('admin.sport-book.create') }}" class="btn btn-primary btn-sm">New fixture</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Event</th>
                        <th>Market</th>
                        <th>Label</th>
                        <th class="text-end">Odds millis</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($selections as $sel)
                        <tr>
                            <td class="font-monospace">{{ $sel->id }}</td>
                            <td>{{ $sel->market->event->name ?? '—' }}</td>
                            <td>{{ $sel->market?->market_type->label() ?? '—' }} ({{ $sel->market?->market_type->value ?? '—' }})</td>
                            <td>{{ $sel->label }}</td>
                            <td class="text-end font-monospace">{{ $sel->current_odds_millis }}</td>
                            <td>{{ $sel->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No selections. Create a fixture first.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $selections->links() }}
@endsection
