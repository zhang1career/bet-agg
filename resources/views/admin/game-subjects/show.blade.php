@extends('layouts.app')

@section('title', '赛事主体 #'.$subject->id)

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.game-subjects.index'),
        'backLabel' => '返回赛事主体列表',
    ])
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ $subject->name }} <span class="text-muted">#{{ $subject->id }}</span></h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.game-subjects.index', ['mall_edit' => $subject->id]) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            <button type="button" class="btn btn-outline-danger btn-sm"
                    data-mall-delete-url="{{ route('admin.game-subjects.destroy', $subject) }}"
                    data-mall-delete-message="删除赛事主体 {{ $subject->name }}？">
                Delete
            </button>
        </div>
    </div>
    @if($errors->has('delete'))
        <div class="alert alert-danger">{{ $errors->first('delete') }}</div>
    @endif
    <div class="mall-console-card card shadow-sm">
        <div class="card-body">
            <h3 class="h6">关联分组 <small class="text-muted">biz_y</small></h3>
            <ul class="mb-0">
                @forelse($subject->groups as $g)
                    <li><a href="{{ route('admin.game-groups.show', $g) }}"><code>{{ $g->code }}</code></a></li>
                @empty
                    <li class="text-muted">暂无；请在编辑页勾选分组。</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
