@extends('layouts.app')

@section('title', __('console.pages.game_subjects'))

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ __('console.list.subjects') }} <small class="text-muted">{{ __('console.list.subjects_note') }}</small></h2>
        <a href="{{ route('admin.game-subjects.index', ['mall_create' => 1]) }}" class="btn btn-primary btn-sm">{{ __('console.btn.new') }}</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('console.table.id') }}</th>
                        <th>{{ __('console.table.name') }}</th>
                        <th class="text-end">{{ __('console.table.group_count') }}</th>
                        <th class="text-end text-nowrap">{{ __('console.table.actions') }}</th>
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
                                   class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none" title="{{ __('console.btn.edit') }}">
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
                        <h2 class="modal-title h5">{{ __('console.game_subjects.create_title') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="gsc_name">{{ __('console.game_subjects.label_name') }}</label>
                            <input type="text" name="name" id="gsc_name" class="form-control" required maxlength="256" value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gsc_group_ids">{{ __('console.game_subjects.label_groups') }} <small class="text-muted">{{ __('console.game_subjects.groups_multi') }}</small></label>
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
                        <a href="{{ route('admin.game-subjects.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('console.btn.create') }}</button>
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
                            <h2 class="modal-title h5">{{ __('console.game_subjects.edit_title', ['id' => $modalSubject->id]) }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="gse_name">{{ __('console.game_subjects.label_name') }}</label>
                                <input type="text" name="name" id="gse_name" class="form-control" required maxlength="256" value="{{ old('name', $modalSubject->name) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="gse_group_ids">{{ __('console.game_subjects.label_groups') }} <small class="text-muted">biz_y</small></label>
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
                            <a href="{{ route('admin.game-subjects.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('console.btn.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
