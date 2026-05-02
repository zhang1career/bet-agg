@extends('layouts.app')

@section('title', 'Create game')

@section('content')
    <form method="post" action="{{ route('admin.games.store') }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 640px;">
        @csrf
        <h2 class="h5 mb-3">Create game</h2>
        <p class="text-muted small">Creates the record in CMS (<code>POST /api/cms/game</code>), then registers local betting state. <code>raw_id</code> is the CMS-assigned id.</p>
        @if($errors->has('cms'))
            <div class="alert alert-danger">{{ $errors->first('cms') }}</div>
        @endif
        <div class="mb-3">
            <label class="form-label" for="name">Title (CMS)</label>
            <input type="text" name="name" id="name" class="form-control" required maxlength="500" value="{{ old('name') }}">
        </div>
        @include('admin.games.partials.starts-at-field', ['startsAtMs' => (int) old('starts_at', 0)])
        @include('admin.games.partials.media-upload', [
            'banner_path' => old('banner_path', ''),
            'main_image_path' => old('main_image_path', ''),
        ])
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
