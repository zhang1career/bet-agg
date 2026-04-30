@extends('layouts.app')

@section('title', 'Edit event #'.$event->id)

@section('content')
    <form method="post" action="{{ route('admin.events.update', $event) }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 560px;">
        @csrf
        @method('PUT')
        <h2 class="h5 mb-3">Edit event</h2>
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" required maxlength="500" value="{{ old('name', $event->name) }}">
        </div>
        <div class="mb-3">
            <label class="form-label" for="starts_at">Starts at (unix ms)</label>
            <input type="number" name="starts_at" id="starts_at" class="form-control" min="0" value="{{ old('starts_at', $event->starts_at) }}">
        </div>
        <div class="mb-3">
            <label class="form-label" for="status">Status</label>
            <select name="status" id="status" class="form-select" required>
                <option value="1" @selected((int) old('status', $event->status) === 1)>Open</option>
                <option value="2" @selected((int) old('status', $event->status) === 2)>Closed</option>
                <option value="3" @selected((int) old('status', $event->status) === 3)>Settled</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.events.show', $event) }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
