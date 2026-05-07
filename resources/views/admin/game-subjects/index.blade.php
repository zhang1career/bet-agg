@extends('layouts.app')

@section('title', '赛事主体')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">赛事主体 <small class="text-muted">biz_game_subject</small></h2>
        <a href="{{ route('admin.game-subjects.index', ['mall_create' => 1]) }}" class="btn btn-primary btn-sm">新建</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th class="text-end">分组数 (biz_y)</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($subjects as $s)
                        <tr>
                            <td><a href="{{ route('admin.game-subjects.show', $s) }}" class="font-monospace">{{ $s->id }}</a></td>
                            <td>{{ $s->name }}</td>
                            <td class="text-end font-monospace">{{ $s->groups_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.game-subjects.index', ['mall_edit' => $s->id]) }}"
                                   class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none" title="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $subjects->links() }}

    <div class="modal fade" id="mallModalSubjectCreate" tabindex="-1" aria-hidden="true"
         data-mall-modal="1" data-mall-strip-query="mall_create"
         @if($mallCreate) data-mall-auto-show="1" @endif>
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.game-subjects.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">新建赛事主体</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="gsc_name">名称</label>
                            <input type="text" name="name" id="gsc_name" class="form-control" required maxlength="256" value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gsc_group_ids">关联赛事分组 <small class="text-muted">biz_y（多选）</small></label>
                            <select name="group_ids[]" id="gsc_group_ids" class="form-select" multiple size="8">
                                @foreach($groups as $g)
                                    <option value="{{ $g->id }}" @selected(in_array((int) $g->id, array_map('intval', (array) old('group_ids', [])), true))>
                                        <code>{{ $g->code }}</code>
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.game-subjects.index') }}" class="btn btn-outline-secondary">取消</a>
                        <button type="submit" class="btn btn-primary">创建</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($modalSubject)
        @php
            $gids = old('group_ids', $modalSelectedGroupIds);
            $gids = is_array($gids) ? array_map('intval', $gids) : [];
        @endphp
        <div class="modal fade" id="mallModalSubjectEdit" tabindex="-1" aria-hidden="true"
             data-mall-modal="1" data-mall-strip-query="mall_edit"
             data-mall-auto-show="1">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.game-subjects.update', $modalSubject) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5">编辑 #{{ $modalSubject->id }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="gse_name">名称</label>
                                <input type="text" name="name" id="gse_name" class="form-control" required maxlength="256" value="{{ old('name', $modalSubject->name) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="gse_group_ids">关联赛事分组 <small class="text-muted">biz_y</small></label>
                                <select name="group_ids[]" id="gse_group_ids" class="form-select" multiple size="8">
                                    @foreach($groups as $g)
                                        <option value="{{ $g->id }}" @selected(in_array((int) $g->id, $gids, true))>
                                            <code>{{ $g->code }}</code>
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('admin.game-subjects.index') }}" class="btn btn-outline-secondary">取消</a>
                            <button type="submit" class="btn btn-primary">保存</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
