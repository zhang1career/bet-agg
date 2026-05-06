@extends('layouts.app')

@section('title', 'Edit game #'.$game->id)

@section('content')
    <form method="post" action="{{ route('admin.games.update', $game) }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 640px;">
        @csrf
        @method('PUT')
        <h2 class="h5 mb-3">Edit game</h2>
        @if(! is_array($cms_game))
            <p class="text-muted small">
                <strong class="text-warning">CMS record could not be loaded</strong> (check gateway or create this id in CMS). You can still save local betting status; media syncs to CMS when the record is available.
            </p>
        @endif
        @if($errors->has('cms'))
            <div class="alert alert-danger">{{ $errors->first('cms') }}</div>
        @endif
        @php
            $cms = is_array($cms_game) ? $cms_game : [];
            $cmsName = old('name', (string) ($cms['title'] ?? ''));
            $cmsStarts = old('starts_at', (int) ($cms['starts_at'] ?? 0));
            $defBanner = (string) ($cms['banner'] ?? '');
            $defMain = (string) ($cms['main_media'] ?? '');
        @endphp
        @if(is_array($cms_game))
            <div class="mb-3">
                <label class="form-label" for="name">Title (CMS)</label>
                <input type="text" name="name" id="name" class="form-control" required maxlength="500" value="{{ $cmsName }}">
            </div>
            @include('admin.games.partials.starts-at-field', ['startsAtMs' => $cmsStarts])
        @else
            <div class="mb-3">
                <span class="form-label d-block">Title / starts at (CMS)</span>
                <p class="text-muted small mb-1">Not loaded. Fix CMS or gateway for <span class="font-monospace">{{ $game->raw_id }}</span> to sync these fields.</p>
                <input type="hidden" name="name" value="">
                <input type="hidden" name="starts_at" value="0">
            </div>
        @endif
        @include('admin.games.partials.media-upload', [
            'banner_path' => old('banner_path', $defBanner),
            'main_image_path' => old('main_image_path', $defMain),
        ])
        <div class="mb-3">
            <label class="form-label" for="status">Status</label>
            <select name="status" id="status" class="form-select" required
                    data-mall-dict-options="game_status"
                    data-mall-dict-selected="{{ (int) old('status', $game->status) }}"></select>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.games.show', $game) }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
