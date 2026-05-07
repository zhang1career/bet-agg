@extends('layouts.app')

@section('title', 'Markets')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Markets</h2>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            @if($filterGameId)
                <a href="{{ route('admin.markets.index') }}" class="btn btn-outline-secondary btn-sm">Clear game filter</a>
            @endif
            <a href="{{ route('admin.markets.create', $filterGameId ? ['game_id' => $filterGameId] : []) }}" class="btn btn-primary btn-sm">New market</a>
        </div>
    </div>

    @if($filterGameId)
        <p class="text-muted small mb-3">Filtered by game #{{ $filterGameId }} · <a href="{{ route('admin.games.show', $filterGameId) }}">Open game</a></p>
    @endif

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Game</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-end">Odds ×1000</th>
                        <th>Status</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($markets as $m)
                        <tr>
                            <td>
                                <a href="{{ route('admin.markets.show', $m) }}" class="font-monospace">{{ $m->id }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.games.show', $m->game_id) }}">Game #{{ $m->game_id }}</a>
                            </td>
                            <td>{{ $m->name }}</td>
                            <td class="small font-monospace">{{ $m->type->value }}</td>
                            <td class="text-end font-monospace small">
                                {{ json_encode($m->outcomeOddsMillisMap(), JSON_UNESCAPED_UNICODE) }}
                            </td>
                            <td>
                                @include('admin.partials.status_label', ['kind' => 'market', 'value' => $m->status])
                                <span class="text-muted">({{ $m->status }})</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.markets.edit', $m) }}" class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none" title="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="Delete" aria-label="Delete"
                                        data-mall-delete-url="{{ route('admin.markets.destroy', $m) }}"
                                        data-mall-delete-message="Delete market #{{ $m->id }}?">
                                    @include('admin.partials.icon_trash')
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{ $markets->links() }}
@endsection
