@extends('layouts.app')

@section('title', __('console.pages.group_code', ['code' => $gameGroup->code]))

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.game-groups.index'),
        'backLabel' => __('console.detail.back_groups'),
    ])

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0"><code class="small">{{ $gameGroup->code }}</code> <span class="text-muted">#{{ $gameGroup->id }}</span></h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.game-groups.index', ['mall_edit' => $gameGroup->id]) }}" class="btn btn-outline-primary btn-sm">{{ __('console.btn.edit') }}</a>
            <button type="button" class="btn btn-outline-danger btn-sm"
                    title="{{ __('console.btn.delete') }}" aria-label="{{ __('console.btn.delete') }}"
                    data-mall-delete-url="{{ route('admin.game-groups.destroy', $gameGroup) }}"
                    data-mall-delete-message="{{ __('console.game_groups.delete_show', ['code' => $gameGroup->code]) }}">
                {{ __('console.btn.delete_group') }}
            </button>
        </div>
    </div>

    <p class="text-muted small mb-3">{{ __('console.game_groups.group_note_before') }}
        <a href="{{ route('admin.games.index') }}">{{ __('console.list.games') }}</a>{{ __('console.game_groups.group_note_after') }}
    </p>

    <div class="mall-console-card card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('console.table.local_id') }}</dt>
                <dd class="col-sm-9 font-monospace">{{ $gameGroup->id }}</dd>
                <dt class="col-sm-3">{{ __('console.table.code') }}</dt>
                <dd class="col-sm-9 font-monospace"><code>{{ $gameGroup->code }}</code></dd>
                <dt class="col-sm-3">{{ __('console.games.timestamps') }}</dt>
                <dd class="col-sm-9 text-muted small">ct {{ \App\Support\MillisTimestampDisplay::format($gameGroup->ct) }}
                    · ut {{ \App\Support\MillisTimestampDisplay::format($gameGroup->ut) }}</dd>
            </dl>
        </div>
    </div>

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h3 class="h6 mb-0">{{ __('console.list.related_games') }}</h3>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('console.table.local_id') }}</th>
                        <th>{{ __('console.table.raw_id') }}</th>
                        <th>{{ __('console.table.title') }}</th>
                        <th>{{ __('console.table.status') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($gameGroup->games as $game)
                        @php
                            $cmsRow = $cmsByRawId[(int) $game->raw_id] ?? null;
                            $title = is_array($cmsRow) && isset($cmsRow['title']) && is_string($cmsRow['title'])
                                ? trim($cmsRow['title'])
                                : '';
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.games.show', $game) }}" class="font-monospace">{{ $game->id }}</a>
                            </td>
                            <td class="font-monospace">{{ $game->raw_id }}</td>
                            <td>{{ $title !== '' ? $title : '—' }}</td>
                            <td>
                                @include('admin.partials.status_label', ['kind' => 'game', 'value' => $game->status])
                                <span class="text-muted">({{ $game->status }})</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">{{ __('console.empty.no_linked_games') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
