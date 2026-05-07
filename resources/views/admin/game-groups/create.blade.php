@extends('layouts.app')

@section('title', '新建分组')

@section('content')
    <form method="post" action="{{ route('admin.game-groups.store') }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 640px;">
        @csrf
        <h2 class="h5 mb-3">新建赛事分组</h2>

        <p class="text-muted small">仅能包含字母、数字、<code>.</code>、<code>_</code>、<code>-</code>，例如 <code class="small">fifa-2026-group</code></p>

        <div class="mb-3">
            <label class="form-label" for="code">Code</label>
            <input type="text" name="code" id="code" class="form-control font-monospace @error('code') is-invalid @enderror" required maxlength="192"
                   value="{{ old('code', '') }}" placeholder="fifa-2026-group" autocomplete="off" spellcheck="false">
            @error('code')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">创建</button>
        <a href="{{ route('admin.game-groups.index') }}" class="btn btn-outline-secondary">取消</a>
    </form>
@endsection
