@extends('layouts.app')

@section('title', __('console.pages.game_groups'))

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ __('console.list.game_groups') }}</h2>
        <a href="{{ route('admin.game-groups.index', ['mall_create' => 1]) }}" class="btn btn-primary btn-sm">{{ __('console.btn.new') }}</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('console.table.id') }}</th>
                        <th>{{ __('console.table.code') }}</th>
                        <th class="text-end">{{ __('console.table.games') }}</th>
                        <th class="text-end text-nowrap">{{ __('console.table.actions') }}</th>
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
                                   title="{{ __('console.btn.edit') }}" aria-label="{{ __('console.btn.edit') }}">
                                    @include('admin.partials.icon_pencil')
                                </a>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="{{ __('console.btn.delete') }}" aria-label="{{ __('console.btn.delete') }}"
                                        data-mall-delete-url="{{ route('admin.game-groups.destroy', $group) }}"
                                        data-mall-delete-message="{{ __('console.game_groups.delete_row', ['code' => $group->code]) }}">
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
                        <h2 class="modal-title h5">{{ __('console.game_groups.create_title') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">{{ __('console.game_groups.code_hint') }}</p>
                        <div class="mb-3">
                            <label class="form-label" for="ggc_code">{{ __('console.table.code') }}</label>
                            <input type="text" name="code" id="ggc_code" class="form-control font-monospace @error('code') is-invalid @enderror" required maxlength="192"
                                   value="{{ old('code', '') }}" placeholder="fifa-2026-group" autocomplete="off" spellcheck="false">
                            @error('code')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.game-groups.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('console.btn.create') }}</button>
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
                            <h2 class="modal-title h5">{{ __('console.game_groups.edit_title', ['id' => $modalGroup->id]) }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">{{ __('console.game_groups.code_hint') }}</p>
                            <div class="mb-3">
                                <label class="form-label" for="gge_code">{{ __('console.table.code') }}</label>
                                <input type="text" name="code" id="gge_code" class="form-control font-monospace @error('code') is-invalid @enderror" required maxlength="192"
                                       value="{{ old('code', $modalGroup->code) }}" autocomplete="off" spellcheck="false">
                                @error('code')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('admin.game-groups.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('console.btn.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
