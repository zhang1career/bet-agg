@extends('layouts.app')

@section('title', __('console.pages.game_subjects'))

@section('content')
    @php
        $retainQs = request()->except('page');
        $cdnBase = rtrim((string) config('services.cloudfront.domain'), '/');
    @endphp
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
                        <th>{{ __('console.table.icon') }}</th>
                        <th>
                            <form method="get" action="{{ route('admin.game-subjects.index') }}" class="d-flex flex-column gap-1 mb-0">
                                @foreach($retainQs as $k => $v)
                                    @continue(in_array($k, ['group', 'mall_create', 'mall_edit'], true))
                                    @if(is_array($v))
                                        @continue
                                    @endif
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endforeach
                                <span class="text-nowrap">{{ __('console.table.group') }}</span>
                                <select name="group" id="game_subjects_filter_group" class="form-select form-select-sm" style="width: auto; min-width: 9rem;" onchange="this.form.submit()">
                                    <option value="" @selected($listGroupFilter === null)>{{ __('console.game_subjects.filter_group_all') }}</option>
                                    @foreach($groups as $g)
                                        <option value="{{ $g->code }}" @selected($listGroupFilter === $g->code)>{{ $g->code }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </th>
                        <th class="text-end">{{ __('console.table.group_count') }}</th>
                        <th class="text-end text-nowrap">{{ __('console.table.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($subjects as $s)
                        @php
                            $iconPath = trim((string) $s->icon);
                            $hasIcon = $iconPath !== '';
                        @endphp
                        <tr>
                            <td><a href="{{ route('admin.game-subjects.show', $s) }}" class="font-monospace">{{ $s->id }}</a></td>
                            <td>{{ $s->name }}</td>
                            <td>
                                @if($hasIcon)
                                    <div class="d-flex align-items-center gap-2">
                                        @if($cdnBase !== '')
                                            <img src="{{ $cdnBase.'/'.ltrim($iconPath, '/') }}" alt=""
                                                 class="rounded border bg-body-secondary flex-shrink-0"
                                                 style="width: 1.5rem; height: 1.5rem; object-fit: contain;">
                                        @endif
                                        <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">
                                            {{ __('console.game_subjects.icon_set') }}
                                        </span>
                                    </div>
                                @else
                                    <span class="badge text-bg-light text-muted border">
                                        {{ __('console.game_subjects.icon_empty') }}
                                    </span>
                                @endif
                            </td>
                            <td class="font-monospace">
                                @forelse($s->groups as $g)
                                    <code>{{ $g->code }}</code>@if(!$loop->last)<span class="text-muted">, </span>@endif
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
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
@endsection

@push('modals')
    <div class="modal fade" id="mallModalSubjectCreate" tabindex="-1" aria-hidden="true"
         data-mall-modal="1" data-mall-strip-query="mall_create"
         @if($mallCreate) data-mall-auto-show="1" @endif>
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
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
                        @include('admin.game-subjects.partials.icon-upload', [
                            'mediaIdPfx' => 'gsc',
                            'icon_path' => old('icon_path', ''),
                        ])
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
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
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
                            @include('admin.game-subjects.partials.icon-upload', [
                                'mediaIdPfx' => 'gse',
                                'icon_path' => old('icon_path', $modalSubject->icon),
                            ])
                            <div class="mb-3">
                                <label class="form-label" for="gse_group_ids">{{ __('console.game_subjects.label_groups') }} <small class="text-muted">y</small></label>
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
@endpush
