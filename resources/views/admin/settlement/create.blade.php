@extends('layouts.app')

@section('title', 'Settlement')

@section('content')
    <div class="bg-white shadow-sm p-4 rounded mb-4" style="max-width: 640px;">
        <h2 class="h5 mb-3">Record result (internal)</h2>

        @if($errors->has('settlement'))
            <div class="alert alert-danger py-2">{{ $errors->first('settlement') }}</div>
        @endif

        <form method="post" action="{{ route('admin.settlement.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="game_id">Open game</label>
                <select name="game_id" id="game_id" class="form-select" required>
                    @forelse($games as $g)
                        <option value="{{ $g->id }}">Local #{{ $g->id }} · raw {{ $g->raw_id }}</option>
                    @empty
                        <option value="" disabled>No open games</option>
                    @endforelse
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="winning_selection_ids">Winning selection IDs (comma-separated)</label>
                <input type="text" name="winning_selection_ids" id="winning_selection_ids" class="form-control"
                       placeholder="e.g. 1,2" value="{{ old('winning_selection_ids') }}">
                <small class="form-text text-muted">Bets on these selections settle as Won.</small>
            </div>
            <div class="mb-3">
                <label class="form-label" for="voided_selection_ids">Voided selection IDs (comma-separated)</label>
                <input type="text" name="voided_selection_ids" id="voided_selection_ids" class="form-control"
                       placeholder="e.g. 3" value="{{ old('voided_selection_ids') }}">
                <small class="form-text text-muted">Bets on these selections refund the stake (Void).</small>
            </div>
            <button type="submit" class="btn btn-primary" @if($games->isEmpty()) disabled @endif>Settle</button>
        </form>
    </div>
@endsection
