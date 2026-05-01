@extends('layouts.app')

@section('title', 'Register game')

@section('content')
    <form method="post" action="{{ route('admin.games.store') }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 560px;">
        @csrf
        <h2 class="h5 mb-3">Register game</h2>
        <p class="text-muted small">Set <code>raw_id</code> to the same numeric id as in CMS (<code>/api/cms/game/{raw_id}</code>). This app stores a separate local row id for markets.</p>
        <div class="mb-3">
            <label class="form-label" for="raw_id">Raw id (CMS game id)</label>
            <input type="number" name="raw_id" id="raw_id" class="form-control" required min="1" value="{{ old('raw_id') }}">
        </div>
        <div class="mb-3">
            <label class="form-label" for="status">Status</label>
            <select name="status" id="status" class="form-select" required>
                <option value="1" @selected((int) old('status', 1) === 1)>Open</option>
                <option value="2" @selected((int) old('status', 1) === 2)>Closed</option>
                <option value="3" @selected((int) old('status', 1) === 3)>Settled</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Create</button>
        <a href="{{ route('admin.games.index') }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
