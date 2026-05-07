@extends('layouts.app')

@section('title', 'Edit market #'.$market->id)

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Edit market #{{ $market->id }}</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.markets.show', $market) }}" class="btn btn-outline-secondary btn-sm">View detail</a>
        </div>
    </div>

    <form method="post" action="{{ route('admin.markets.update', $market) }}" class="bg-white shadow-sm p-4 rounded mb-4">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label" for="game_id">Game</label>
            <select name="game_id" id="game_id" class="form-select" required>
                @include('admin.partials.game_select_options', [
                    'games' => $games,
                    'gameSelectLabels' => $gameSelectLabels,
                    'selectedGameId' => (int) old('game_id', $market->game_id),
                ])
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" maxlength="256"
                   value="{{ old('name', $market->name) }}" placeholder="Display label for this market">
        </div>
        <div class="mb-3">
            <label class="form-label" for="type">Type</label>
            <select name="type" id="type" class="form-select" required>
                @foreach(\App\Enums\MarketType::cases() as $mt)
                    <option value="{{ $mt->value }}" @selected((int) old('type', $market->type->value) === $mt->value)>
                        {{ $mt->value }} — {{ $mt->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        @php($om = $market->outcomeOddsMillisMap())
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="home_odds_millis">Home odds ×1000</label>
                <input type="number" name="home_odds_millis" id="home_odds_millis" class="form-control" required min="1000"
                       value="{{ old('home_odds_millis', $om[\App\Enums\MatchOutcomeCode::HomeWin->value] ?? 2000) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="draw_odds_millis">Draw odds ×1000</label>
                <input type="number" name="draw_odds_millis" id="draw_odds_millis" class="form-control" required min="1000"
                       value="{{ old('draw_odds_millis', $om[\App\Enums\MatchOutcomeCode::Draw->value] ?? 2000) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="away_odds_millis">Away odds ×1000</label>
                <input type="number" name="away_odds_millis" id="away_odds_millis" class="form-control" required min="1000"
                       value="{{ old('away_odds_millis', $om[\App\Enums\MatchOutcomeCode::AwayWin->value] ?? 2000) }}">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="status">Market status</label>
            <select name="status" id="status" class="form-select" required
                    data-mall-dict-options="market_status"
                    data-mall-dict-selected="{{ (int) old('status', $market->status) }}"></select>
        </div>
        <button type="submit" class="btn btn-primary">Save market</button>
    </form>
@endsection
