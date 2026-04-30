@extends('layouts.app')

@section('title', 'New event')

@section('content')
    <form method="post" action="{{ route('admin.events.store') }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 560px;">
        @csrf
        <h2 class="h5 mb-3">Create event</h2>
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" required maxlength="500" value="{{ old('name') }}">
        </div>
        <div class="mb-3">
            <label class="form-label" for="starts_at">Starts at (unix ms, optional)</label>
            <input type="number" name="starts_at" id="starts_at" class="form-control" min="0" value="{{ old('starts_at') }}"
                   placeholder="Leave empty for now">
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
        <a href="{{ route('admin.events.index') }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
