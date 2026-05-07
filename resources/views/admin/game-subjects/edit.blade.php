@extends('layouts.app')

@section('title', '编辑赛事主体 #'.$subject->id)

@section('content')
    <form method="post" action="{{ route('admin.game-subjects.update', $subject) }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 560px;">
        @csrf
        @method('PUT')
        <h2 class="h5 mb-3">编辑</h2>
        <div class="mb-3">
            <label class="form-label" for="name">名称</label>
            <input type="text" name="name" id="name" class="form-control" required maxlength="256" value="{{ old('name', $subject->name) }}">
        </div>
        <div class="mb-3">
            <label class="form-label" for="group_ids">关联赛事分组 <small class="text-muted">biz_y</small></label>
            <select name="group_ids[]" id="group_ids" class="form-select" multiple size="8">
                @php
                    $gids = old('group_ids', $selectedGroupIds);
                    $gids = is_array($gids) ? array_map('intval', $gids) : [];
                @endphp
                @foreach($groups as $g)
                    <option value="{{ $g->id }}" @selected(in_array((int) $g->id, $gids, true))>
                        <code>{{ $g->code }}</code>
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">保存</button>
        <a href="{{ route('admin.game-subjects.show', $subject) }}" class="btn btn-link">取消</a>
    </form>
@endsection
