@extends('layouts.app')

@section('title', 'Market #'.$market->id)

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.markets.index'),
        'backLabel' => '返回 Markets 列表',
    ])

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Market #{{ $market->id }}</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.markets.index', ['mall_edit' => $market->id]) }}" class="btn btn-primary btn-sm">Edit market</a>
            <button type="button" class="btn btn-outline-danger btn-sm" title="Delete"
                    data-mall-delete-url="{{ route('admin.markets.destroy', $market) }}"
                    data-mall-delete-message="Delete market #{{ $market->id }}?">
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
                <dt class="col-sm-3">Type</dt>
                <dd class="col-sm-9 font-monospace">{{ $market->type->value }} — {{ $market->type->label() }}</dd>
                <dt class="col-sm-3">odds_millis</dt>
                <dd class="col-sm-9 font-monospace small">{{ json_encode($market->outcomeOddsMillisMap(), JSON_UNESCAPED_UNICODE) }}</dd>
                <dt class="col-sm-3">Synthetic legs</dt>
                <dd class="col-sm-9 small">
                    @php
                        $legs = app(\App\Services\mall\SyntheticMatchMarket::class)->legsForApi($market, $market->game);
                    @endphp
                    <ul class="mb-0">
                        @foreach($legs as $leg)
                            <li><code>{{ $leg['outcome_code'] }}</code> — {{ $leg['label'] }} · {{ $leg['current_odds_millis'] }}</li>
                        @endforeach
                    </ul>
                </dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @include('admin.partials.status_label', ['kind' => 'market', 'value' => $market->status])
                    <span class="text-muted">({{ $market->status }})</span>
                </dd>
                <dt class="col-sm-3">Timestamps</dt>
                <dd class="col-sm-9 text-muted small">ct {{ \App\Support\MillisTimestampDisplay::format($market->ct) }}
                    · ut {{ \App\Support\MillisTimestampDisplay::format($market->ut) }}</dd>
            </dl>
        </div>
    </div>
@endsection
