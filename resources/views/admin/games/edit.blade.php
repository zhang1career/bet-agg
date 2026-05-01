@extends('layouts.app')

@section('title', 'Edit game #'.$game->id)

@section('content')
    <form method="post" action="{{ route('admin.games.update', $game) }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 560px;">
        @csrf
        @method('PUT')
        <h2 class="h5 mb-3">Edit game</h2>
        <p class="text-muted small">Local id <span class="font-monospace">{{ $game->id }}</span> and raw id <span class="font-monospace">{{ $game->raw_id }}</span> are fixed; change status only here.</p>
        <div class="mb-3">
            <label class="form-label" for="status">Status</label>
            <select name="status" id="status" class="form-select" required>
                <option value="1" @selected((int) old('status', $game->status) === 1)>Open</option>
                <option value="2" @selected((int) old('status', $game->status) === 2)>Closed</option>
                <option value="3" @selected((int) old('status', $game->status) === 3)>Settled</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.games.show', $game) }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
