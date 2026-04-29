@extends('layouts.app')

@section('title', 'New fixture')

@section('content')
    <div class="bg-white shadow-sm p-4 rounded mb-4" style="max-width: 560px;">
        <h2 class="h5 mb-3">Create event, market, selection</h2>
        <form method="post" action="{{ route('admin.sport-book.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="event_name">Event name</label>
                <input type="text" name="event_name" id="event_name" class="form-control" required maxlength="500" value="{{ old('event_name') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="starts_at">Starts at (unix ms, optional)</label>
                <input type="number" name="starts_at" id="starts_at" class="form-control" min="0" value="{{ old('starts_at') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="market_type">Market type</label>
                <select name="market_type" id="market_type" class="form-select" required>
                    @foreach(\App\Enums\SportMarketType::cases() as $case)
                        @if($case === \App\Enums\SportMarketType::Unknown)
                            @continue
                        @endif
                        <option value="{{ $case->value }}" @selected((int) old('market_type', \App\Enums\SportMarketType::MatchResult1x2->value) === $case->value)>
                            {{ $case->label() }} ({{ $case->value }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="selection_label">Selection label</label>
                <input type="text" name="selection_label" id="selection_label" class="form-control" required maxlength="256" value="{{ old('selection_label') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="current_odds_millis">Odds (decimal × 1000, int)</label>
                <input type="number" name="current_odds_millis" id="current_odds_millis" class="form-control" required min="1000" value="{{ old('current_odds_millis', 1950) }}">
            </div>
            <button type="submit" class="btn btn-primary">Create</button>
            <a href="{{ route('admin.sport-book.index') }}" class="btn btn-link">Cancel</a>
        </form>
    </div>
@endsection
