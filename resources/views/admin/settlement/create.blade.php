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
                <label class="form-label" for="event_id">Open event</label>
                <select name="event_id" id="event_id" class="form-select" required>
                    @forelse($events as $ev)
                        <option value="{{ $ev->id }}">#{{ $ev->id }} — {{ $ev->name }}</option>
                    @empty
                        <option value="" disabled>No open events</option>
                    @endforelse
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="winning_selection_ids">Winning selection IDs (comma-separated)</label>
                <input type="text" name="winning_selection_ids" id="winning_selection_ids" class="form-control" required
                       placeholder="e.g. 1,2" value="{{ old('winning_selection_ids') }}">
            </div>
            <button type="submit" class="btn btn-primary" @if($events->isEmpty()) disabled @endif>Settle</button>
        </form>
    </div>
@endsection
