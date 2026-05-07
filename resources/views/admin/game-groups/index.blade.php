@extends('layouts.app')

@section('title', 'Game groups')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">赛事分组</h2>
        <a href="{{ route('admin.game-groups.index', ['mall_create' => 1]) }}" class="btn btn-primary btn-sm">新建</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th class="text-end">Games</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($groups as $group)
                        <tr>
                            <td>
                                <a href="{{ route('admin.game-groups.show', $group) }}" class="font-monospace">{{ $group->id }}</a>
                            </td>
                            <td class="font-monospace"><code>{{ $group->code }}</code></td>
                            <td class="text-end font-monospace">{{ $group->games_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.game-groups.index', ['mall_edit' => $group->id]) }}"
                                   class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none"
                                   title="Edit" aria-label="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="Delete" aria-label="Delete"
                                        data-mall-delete-url="{{ route('admin.game-groups.destroy', $group) }}"
                                        data-mall-delete-message="删除分组 {{ $group->code }}？关联的赛事将仅解除映射，不会被删除。">
                                    @include('admin.partials.icon_trash')
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{ $groups->links() }}

    <div class="modal fade" id="mallModalGroupCreate" tabindex="-1" aria-hidden="true"
         data-mall-modal="1" data-mall-strip-query="mall_create"
         @if($mallCreate) data-mall-auto-show="1" @endif>
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.game-groups.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">新建赛事分组</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">仅能包含字母、数字、<code>.</code>、<code>_</code>、<code>-</code></p>
                        <div class="mb-3">
                            <label class="form-label" for="ggc_code">Code</label>
                            <input type="text" name="code" id="ggc_code" class="form-control font-monospace @error('code') is-invalid @enderror" required maxlength="192"
                                   value="{{ old('code', '') }}" placeholder="fifa-2026-group" autocomplete="off" spellcheck="false">
                            @error('code')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.game-groups.index') }}" class="btn btn-outline-secondary">取消</a>
                        <button type="submit" class="btn btn-primary">创建</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($modalGroup)
        <div class="modal fade" id="mallModalGroupEdit" tabindex="-1" aria-hidden="true"
             data-mall-modal="1" data-mall-strip-query="mall_edit"
             data-mall-auto-show="1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.game-groups.update', $modalGroup) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5">编辑分组 #{{ $modalGroup->id }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">仅能包含字母、数字、<code>.</code>、<code>_</code>、<code>-</code></p>
                            <div class="mb-3">
                                <label class="form-label" for="gge_code">Code</label>
                                <input type="text" name="code" id="gge_code" class="form-control font-monospace @error('code') is-invalid @enderror" required maxlength="192"
                                       value="{{ old('code', $modalGroup->code) }}" autocomplete="off" spellcheck="false">
                                @error('code')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('admin.game-groups.index') }}" class="btn btn-outline-secondary">取消</a>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
