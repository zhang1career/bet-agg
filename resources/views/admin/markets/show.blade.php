@extends('layouts.app')

@section('title', __('console.pages.market_detail', ['id' => $market->id]))

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.markets.index'),
        'backLabel' => __('console.markets.back_list'),
    ])

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ __('console.pages.market_detail', ['id' => $market->id]) }}</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.markets.index', ['mall_edit' => $market->id]) }}" class="btn btn-primary btn-sm">{{ __('console.btn.edit_market') }}</a>
            <button type="button" class="btn btn-outline-danger btn-sm" title="{{ __('console.btn.delete') }}"
                    data-mall-delete-url="{{ route('admin.markets.destroy', $market) }}"
                    data-mall-delete-message="{{ __('console.markets.delete_confirm', ['id' => $market->id]) }}">
                {{ __('console.btn.delete') }}
            </button>
        </div>
    </div>

    <div class="mall-console-card card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('console.table.id') }}</dt>
                <dd class="col-sm-9 font-monospace">{{ $market->id }}</dd>
                <dt class="col-sm-3">{{ __('console.table.game') }}</dt>
                <dd class="col-sm-9">
                    <a href="{{ route('admin.games.show', $market->game_id) }}">{{ __('console.markets.game_number', ['id' => $market->game_id]) }}</a>
                </dd>
                <dt class="col-sm-3">{{ __('console.table.name') }}</dt>
                <dd class="col-sm-9">{{ $market->name }}</dd>
                <dt class="col-sm-3">{{ __('console.table.type') }}</dt>
                <dd class="col-sm-9 font-monospace">{{ $market->type->value }} — {{ $market->type->label() }}</dd>
                <dt class="col-sm-3">odds_millis</dt>
                <dd class="col-sm-9 font-monospace small">{{ json_encode($market->outcomeOddsMillisMap(), JSON_UNESCAPED_UNICODE) }}</dd>
                <dt class="col-sm-3">{{ __('console.markets.synthetic_legs') }}</dt>
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
                <dt class="col-sm-3">{{ __('console.table.status') }}</dt>
                <dd class="col-sm-9">
                    @include('admin.partials.status_label', ['kind' => 'market', 'value' => $market->status])
                    <span class="text-muted">({{ $market->status }})</span>
                </dd>
                <dt class="col-sm-3">{{ __('console.games.timestamps') }}</dt>
                <dd class="col-sm-9 text-muted small">ct {{ \App\Support\MillisTimestampDisplay::format($market->ct) }}
                    · ut {{ \App\Support\MillisTimestampDisplay::format($market->ut) }}</dd>
            </dl>
        </div>
    </div>
@endsection
