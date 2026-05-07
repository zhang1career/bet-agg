@extends('layouts.app')

@section('title', 'New market')

@section('content')
    <form method="post" action="{{ route('admin.markets.store') }}" class="bg-white shadow-sm p-4 rounded mb-4">
        @csrf
        <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h5 mb-0">Create market</h2>
        </div>

        <div class="mb-3">
            <label class="form-label" for="game_id">Game</label>
            <select name="game_id" id="game_id" class="form-select" required>
                @include('admin.partials.game_select_options', [
                    'games' => $games,
                    'gameSelectLabels' => $gameSelectLabels,
                    'selectedGameId' => (int) old('game_id', $prefillGameId),
                ])
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" maxlength="256"
                   value="{{ old('name', '胜平负') }}" placeholder="Display label for this market">
        </div>
        <div class="mb-3">
            <label class="form-label" for="type">Type</label>
            <select name="type" id="type" class="form-select" required>
                @foreach(\App\Enums\MarketType::cases() as $mt)
                    <option value="{{ $mt->value }}" @selected((int) old('type', $mt->value) === $mt->value)>
                        {{ $mt->value }} — {{ $mt->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="home_odds_millis">Home odds ×1000</label>
                <input type="number" name="home_odds_millis" id="home_odds_millis" class="form-control" required min="1000"
                       value="{{ old('home_odds_millis', 2000) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="draw_odds_millis">Draw odds ×1000</label>
                <input type="number" name="draw_odds_millis" id="draw_odds_millis" class="form-control" required min="1000"
                       value="{{ old('draw_odds_millis', 2000) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="away_odds_millis">Away odds ×1000</label>
                <input type="number" name="away_odds_millis" id="away_odds_millis" class="form-control" required min="1000"
                       value="{{ old('away_odds_millis', 2000) }}">
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label" for="status">Market status</label>
            <select name="status" id="status" class="form-select" required
                    data-mall-dict-options="market_status"
                    data-mall-dict-selected="{{ (int) old('status', 1) }}"></select>
        </div>
        <button type="submit" class="btn btn-primary">Create market</button>
        <a href="{{ route('admin.markets.index') }}" class="btn btn-outline-secondary">Cancel</a>
@endsection
