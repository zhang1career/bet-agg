@extends('layouts.app')

@section('title', 'Edit '.$gameGroup->code)

@section('content')
    <form method="post" action="{{ route('admin.game-groups.update', $gameGroup) }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 640px;">
        @csrf
        @method('PUT')
        <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h5 mb-0">编辑分组 #{{ $gameGroup->id }}</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.game-groups.show', $gameGroup) }}" class="btn btn-outline-secondary btn-sm">View detail</a>
            </div>
        </div>

        <p class="text-muted small">仅能包含字母、数字、<code>.</code>、<code>_</code>、<code>-</code></p>

        <div class="mb-3">
            <label class="form-label" for="code">Code</label>
            <input type="text" name="code" id="code" class="form-control font-monospace @error('code') is-invalid @enderror" required maxlength="192"
                   value="{{ old('code', $gameGroup->code) }}" autocomplete="off" spellcheck="false">
            @error('code')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.game-groups.index') }}" class="btn btn-outline-secondary">取消</a>
    </form>
@endsection
