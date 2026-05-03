@extends('layouts.app')

@section('title', 'Market #'.$market->id)

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Market #{{ $market->id }}</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.markets.edit', $market) }}" class="btn btn-primary btn-sm">Edit market</a>
            <a href="{{ route('admin.games.show', $market->game_id) }}" class="btn btn-outline-secondary btn-sm">Game</a>
            <a href="{{ route('admin.markets.index', ['game_id' => $market->game_id]) }}" class="btn btn-outline-secondary btn-sm">Markets for game</a>
            <button type="button" class="btn btn-outline-danger btn-sm" title="Delete"
                    data-mall-delete-url="{{ route('admin.markets.destroy', $market) }}"
                    data-mall-delete-message="Delete market #{{ $market->id }} and all selections?">
                Delete
            </button>
        </div>
    </div>

    <div class="mall-console-card card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">ID</dt>
                <dd class="col-sm-9 font-monospace">{{ $market->id }}</dd>
                <dt class="col-sm-3">Game</dt>
                <dd class="col-sm-9">
                    <a href="{{ route('admin.games.show', $market->game_id) }}">Game #{{ $market->game_id }}</a>
                </dd>
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $market->name }}</dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @include('admin.partials.sport_status_label', ['kind' => 'market', 'value' => $market->status])
                    <span class="text-muted">({{ $market->status }})</span>
                </dd>
                <dt class="col-sm-3">Timestamps</dt>
                <dd class="col-sm-9 text-muted small">ct {{ \App\Support\MillisTimestampDisplay::format($market->ct) }}
                    · ut {{ \App\Support\MillisTimestampDisplay::format($market->ut) }}</dd>
            </dl>
        </div>
    </div>

    @include('admin.markets.partials.selections-panel', ['market' => $market])
@endsection
