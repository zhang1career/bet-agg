@extends('layouts.app')

@section('title', 'Game #'.$game->id)

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Game #{{ $game->id }}</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.markets.create', ['game_id' => $game->id]) }}" class="btn btn-primary btn-sm">New market</a>
            <a href="{{ route('admin.games.edit', $game) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            <a href="{{ route('admin.games.index') }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
        </div>
    </div>

    @if($errors->has('delete'))
        <div class="alert alert-danger">{{ $errors->first('delete') }}</div>
    @endif

    <div class="mall-console-card card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Local ID</dt>
                <dd class="col-sm-9 font-monospace">{{ $game->id }}</dd>
                <dt class="col-sm-3">Raw id (CMS)</dt>
                <dd class="col-sm-9 font-monospace">{{ $game->raw_id }}</dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @include('admin.partials.sport_status_label', ['kind' => 'game', 'value' => $game->status])
                    <span class="text-muted">({{ $game->status }})</span>
                </dd>
                @if(filled($game->winning_selection_ids))
                    <dt class="col-sm-3">Winning selection ids</dt>
                    <dd class="col-sm-9 font-monospace small">{{ json_encode($game->winning_selection_ids) }}</dd>
                @endif
                <dt class="col-sm-3">Timestamps</dt>
                <dd class="col-sm-9 text-muted small">ct {{ \App\Support\MillisTimestampDisplay::format($game->ct) }}
                    · ut {{ \App\Support\MillisTimestampDisplay::format($game->ut) }}</dd>
            </dl>
        </div>
    </div>

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h3 class="h6 mb-0">Markets</h3>
        <a href="{{ route('admin.markets.index', ['game_id' => $game->id]) }}" class="btn btn-outline-secondary btn-sm">Filter market list</a>
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
                    @forelse($game->markets as $m)
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
