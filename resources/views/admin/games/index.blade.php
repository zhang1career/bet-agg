@extends('layouts.app')

@section('title', 'Games')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Games</h2>
        <a href="{{ route('admin.games.index', ['mall_create' => 1]) }}" class="btn btn-primary btn-sm">新建</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>Local ID</th>
                        <th>raw id</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th class="text-end">Markets</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($games as $game)
                        <tr>
                            <td>
                                <a href="{{ route('admin.games.show', $game) }}" class="font-monospace">{{ $game->id }}</a>
                            </td>
                            <td class="font-monospace">{{ $game->raw_id }}</td>
                            <td class="small">{{ ($cmsByRawId[(int) $game->raw_id] ?? [])['title'] ?? '—' }}</td>
                            <td>
                                @include('admin.partials.status_label', ['kind' => 'game', 'value' => $game->status])
                                <span class="text-muted">({{ $game->status }})</span>
                            </td>
                            <td class="text-end font-monospace">{{ $game->markets_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.games.index', ['mall_edit' => $game->id]) }}"
                                   class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none"
                                   title="Edit" aria-label="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="Delete" aria-label="Delete"
                                        data-mall-delete-url="{{ route('admin.games.destroy', $game) }}"
                                        data-mall-delete-message="Delete game #{{ $game->id }}?">
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

    {{ $games->links() }}

    {{-- Create --}}
    <div class="modal fade" id="mallModalGameCreate" tabindex="-1" aria-hidden="true"
         data-mall-modal="1"
         data-mall-strip-query="mall_create"
         @if($mallCreate) data-mall-auto-show="1" @endif>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.games.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">Create game</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Creates the record in CMS (<code>POST /api/cms/game</code>), then registers local betting state.</p>
                        @if($errors->has('cms'))
                            <div class="alert alert-danger">{{ $errors->first('cms') }}</div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label" for="game_name_gc">Title (CMS)</label>
                            <input type="text" name="name" id="game_name_gc" class="form-control" required maxlength="500" value="{{ old('name') }}">
                        </div>
                        @include('admin.games.partials.starts-at-field', ['startsAtMs' => (int) old('starts_at', 0), 'idSuf' => '_gc'])
                        @include('admin.games.partials.media-upload', [
                            'banner_path' => old('banner_path', ''),
                            'main_image_path' => old('main_image_path', ''),
                            'mediaIdPfx' => 'gg_gc',
                        ])
                        @include('admin.games.partials.groups-and-sides', [
                            'allGroups' => $allGroups,
                            'allSubjects' => $allSubjects,
                            'idSuf' => '_gc',
                        ])
                        <div class="mb-3">
                            <label class="form-label" for="game_status_gc">Status</label>
                            <select name="status" id="game_status_gc" class="form-select" required
                                    data-mall-dict-options="game_status"
                                    data-mall-dict-selected="{{ (int) old('status', 1) }}"></select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.games.index') }}" class="btn btn-outline-secondary">取消</a>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit --}}
    @if($modalEditGame)
        @php
            $game = $modalEditGame;
            $cms_game = $modalEditCms;
            $cms = is_array($cms_game) ? $cms_game : [];
            $cmsName = old('name', (string) ($cms['title'] ?? ''));
            $cmsStarts = old('starts_at', (int) ($cms['starts_at'] ?? 0));
            $defBanner = (string) ($cms['banner'] ?? '');
            $defMain = (string) ($cms['main_media'] ?? '');
        @endphp
        <div class="modal fade" id="mallModalGameEdit" tabindex="-1" aria-hidden="true"
             data-mall-modal="1"
             data-mall-strip-query="mall_edit"
             data-mall-auto-show="1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.games.update', $game) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5">Edit game #{{ $game->id }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if(! is_array($cms_game))
                                <p class="text-muted small">
                                    <strong class="text-warning">CMS record could not be loaded</strong> (check gateway or create this id in CMS). You can still save local betting status; media syncs to CMS when the record is available.
                                </p>
                            @endif
                            @if($errors->has('cms'))
                                <div class="alert alert-danger">{{ $errors->first('cms') }}</div>
                            @endif
                            @if(is_array($cms_game))
                                <div class="mb-3">
                                    <label class="form-label" for="game_name_ge">Title (CMS)</label>
                                    <input type="text" name="name" id="game_name_ge" class="form-control" required maxlength="500" value="{{ $cmsName }}">
                                </div>
                                @include('admin.games.partials.starts-at-field', ['startsAtMs' => $cmsStarts, 'idSuf' => '_ge'])
                            @else
                                <div class="mb-3">
                                    <span class="form-label d-block">Title / starts at (CMS)</span>
                                    <p class="text-muted small mb-1">Not loaded. Fix CMS or gateway for <span class="font-monospace">{{ $game->raw_id }}</span>.</p>
                                    <input type="hidden" name="name" value="">
                                    <input type="hidden" name="starts_at" value="0">
                                </div>
                            @endif
                            @include('admin.games.partials.media-upload', [
                                'banner_path' => old('banner_path', $defBanner),
                                'main_image_path' => old('main_image_path', $defMain),
                                'mediaIdPfx' => 'gg_ge',
                            ])
                            @include('admin.games.partials.groups-and-sides', [
                                'allGroups' => $allGroups,
                                'allSubjects' => $allSubjects,
                                'selectedGroupIds' => $modalEditSelectedGroups,
                                'selectedSideA' => $game->side_a_subject_id,
                                'selectedSideB' => $game->side_b_subject_id,
                                'idSuf' => '_ge',
                            ])
                            <div class="mb-3">
                                <label class="form-label" for="game_status_ge">Status</label>
                                <select name="status" id="game_status_ge" class="form-select" required
                                        data-mall-dict-options="game_status"
                                        data-mall-dict-selected="{{ (int) old('status', $game->status) }}"></select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('admin.games.index') }}" class="btn btn-outline-secondary">取消</a>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
