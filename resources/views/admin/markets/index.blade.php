@extends('layouts.app')

@section('title', 'Markets')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Markets</h2>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="{{ route('admin.markets.index', ['mall_create' => 1]) }}" class="btn btn-primary btn-sm">New market</a>
        </div>
    </div>

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
                                <a href="{{ route('admin.markets.index', ['mall_edit' => $m->id]) }}"
                                   class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none" title="Edit">
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

    <div class="modal fade" id="mallModalMarketCreate" tabindex="-1" aria-hidden="true"
         data-mall-modal="1"
         data-mall-strip-query="mall_create game_id"
         @if($mallCreate) data-mall-auto-show="1" @endif>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.markets.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">Create market</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="mmc_game_id">Game</label>
                            <select name="game_id" id="mmc_game_id" class="form-select" required>
                                @include('admin.partials.game_select_options', [
                                    'games' => $games,
                                    'gameSelectLabels' => $gameSelectLabels,
                                    'selectedGameId' => (int) old('game_id', $prefillGameId),
                                ])
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="mmc_name">Name</label>
                            <input type="text" name="name" id="mmc_name" class="form-control" maxlength="256"
                                   value="{{ old('name', '胜平负') }}" placeholder="Display label for this market">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="mmc_type">Type</label>
                            <select name="type" id="mmc_type" class="form-select" required>
                                @foreach(\App\Enums\MarketType::cases() as $mt)
                                    <option value="{{ $mt->value }}" @selected((int) old('type', $mt->value) === $mt->value)>
                                        {{ $mt->value }} — {{ $mt->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="mmc_home_odds">Home odds ×1000</label>
                                <input type="number" name="home_odds_millis" id="mmc_home_odds" class="form-control" required min="1000"
                                       value="{{ old('home_odds_millis', 2000) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="mmc_draw_odds">Draw odds ×1000</label>
                                <input type="number" name="draw_odds_millis" id="mmc_draw_odds" class="form-control" required min="1000"
                                       value="{{ old('draw_odds_millis', 2000) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="mmc_away_odds">Away odds ×1000</label>
                                <input type="number" name="away_odds_millis" id="mmc_away_odds" class="form-control" required min="1000"
                                       value="{{ old('away_odds_millis', 2000) }}">
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="mmc_status">Market status</label>
                            <select name="status" id="mmc_status" class="form-select" required
                                    data-mall-dict-options="market_status"
                                    data-mall-dict-selected="{{ (int) old('status', 1) }}"></select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.markets.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create market</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($modalMarket)
        @php($om = $modalMarket->outcomeOddsMillisMap())
        <div class="modal fade" id="mallModalMarketEdit" tabindex="-1" aria-hidden="true"
             data-mall-modal="1"
             data-mall-strip-query="mall_edit"
             data-mall-auto-show="1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.markets.update', $modalMarket) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5">Edit market #{{ $modalMarket->id }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="mme_game_id">Game</label>
                                <select name="game_id" id="mme_game_id" class="form-select" required>
                                    @include('admin.partials.game_select_options', [
                                        'games' => $games,
                                        'gameSelectLabels' => $gameSelectLabels,
                                        'selectedGameId' => (int) old('game_id', $modalMarket->game_id),
                                    ])
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="mme_name">Name</label>
                                <input type="text" name="name" id="mme_name" class="form-control" maxlength="256"
                                       value="{{ old('name', $modalMarket->name) }}" placeholder="Display label for this market">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="mme_type">Type</label>
                                <select name="type" id="mme_type" class="form-select" required>
                                    @foreach(\App\Enums\MarketType::cases() as $mt)
                                        <option value="{{ $mt->value }}" @selected((int) old('type', $modalMarket->type->value) === $mt->value)>
                                            {{ $mt->value }} — {{ $mt->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="mme_home_odds">Home odds ×1000</label>
                                    <input type="number" name="home_odds_millis" id="mme_home_odds" class="form-control" required min="1000"
                                           value="{{ old('home_odds_millis', $om[\App\Enums\MatchOutcomeCode::HomeWin->value] ?? 2000) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="mme_draw_odds">Draw odds ×1000</label>
                                    <input type="number" name="draw_odds_millis" id="mme_draw_odds" class="form-control" required min="1000"
                                           value="{{ old('draw_odds_millis', $om[\App\Enums\MatchOutcomeCode::Draw->value] ?? 2000) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="mme_away_odds">Away odds ×1000</label>
                                    <input type="number" name="away_odds_millis" id="mme_away_odds" class="form-control" required min="1000"
                                           value="{{ old('away_odds_millis', $om[\App\Enums\MatchOutcomeCode::AwayWin->value] ?? 2000) }}">
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="mme_status">Market status</label>
                                <select name="status" id="mme_status" class="form-select" required
                                        data-mall-dict-options="market_status"
                                        data-mall-dict-selected="{{ (int) old('status', $modalMarket->status) }}"></select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('admin.markets.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save market</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
