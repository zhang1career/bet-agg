@extends('layouts.app')

@section('title', '新建赛事主体')

@section('content')
    <form method="post" action="{{ route('admin.game-subjects.store') }}" class="bg-white shadow-sm p-4 rounded" style="max-width: 560px;">
        @csrf
        <h2 class="h5 mb-3">新建赛事主体</h2>
        <div class="mb-3">
            <label class="form-label" for="name">名称</label>
            <input type="text" name="name" id="name" class="form-control" required maxlength="256" value="{{ old('name') }}">
        </div>
        <div class="mb-3">
            <label class="form-label" for="group_ids">关联赛事分组 <small class="text-muted">biz_y（多选）</small></label>
            <select name="group_ids[]" id="group_ids" class="form-select" multiple size="8">
                @foreach($groups as $g)
                    <option value="{{ $g->id }}" @selected(in_array((int) $g->id, array_map('intval', (array) old('group_ids', [])), true))>
                        <code>{{ $g->code }}</code>
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">创建</button>
        <a href="{{ route('admin.game-subjects.index') }}" class="btn btn-outline-secondary">取消</a>
    </form>
@endsection
