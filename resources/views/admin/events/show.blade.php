@extends('layouts.app')

@section('title', 'Event #'.$event->id)

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Event #{{ $event->id }}</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.markets.create', ['event_id' => $event->id]) }}" class="btn btn-primary btn-sm">New market</a>
            <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            <a href="{{ route('admin.events.index') }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
        </div>
    </div>

    @if($errors->has('delete'))
        <div class="alert alert-danger">{{ $errors->first('delete') }}</div>
    @endif

    <div class="mall-console-card card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">ID</dt>
                <dd class="col-sm-9 font-monospace">{{ $event->id }}</dd>
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $event->name }}</dd>
                <dt class="col-sm-3">Starts at</dt>
                <dd class="col-sm-9 text-muted small">{{ \App\Support\MillisTimestampDisplay::format($event->starts_at) }}</dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @include('admin.partials.sport_status_label', ['kind' => 'event', 'value' => $event->status])
                    <span class="text-muted">({{ $event->status }})</span>
                </dd>
                <dt class="col-sm-3">Timestamps</dt>
                <dd class="col-sm-9 text-muted small">ct {{ \App\Support\MillisTimestampDisplay::format($event->ct) }}
                    · ut {{ \App\Support\MillisTimestampDisplay::format($event->ut) }}</dd>
            </dl>
        </div>
    </div>

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h3 class="h6 mb-0">Markets</h3>
        <a href="{{ route('admin.markets.index', ['event_id' => $event->id]) }}" class="btn btn-outline-secondary btn-sm">Filter market list</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end">Selections</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($event->markets as $m)
                        <tr>
                            <td>
                                <a href="{{ route('admin.markets.show', $m) }}" class="font-monospace">{{ $m->id }}</a>
                            </td>
                            <td>{{ $m->market_type->label() }} ({{ $m->market_type->value }})</td>
                            <td>
                                @include('admin.partials.sport_status_label', ['kind' => 'market', 'value' => $m->status])
                                <span class="text-muted">({{ $m->status }})</span>
                            </td>
                            <td class="text-end font-monospace">{{ $m->selections_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.markets.edit', $m) }}" class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none" title="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No markets yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
