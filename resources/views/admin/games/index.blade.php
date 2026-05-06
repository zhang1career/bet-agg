@extends('layouts.app')

@section('title', 'Games')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Games (local betting state)</h2>
        <a href="{{ route('admin.games.create') }}" class="btn btn-primary btn-sm">新建</a>
    </div>

    <p class="text-muted small">Local <code>biz_game.id</code> for markets; <code>raw_id</code> is the CMS record id (create/update uses CMS API). Names and primary times live in CMS.</p>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>Local ID</th>
                        <th>raw id</th>
                        <th>Status</th>
                        <th class="text-end">Markets</th>
                        <th>CMS title</th>
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
                            <td>
                                @include('admin.partials.status_label', ['kind' => 'game', 'value' => $game->status])
                                <span class="text-muted">({{ $game->status }})</span>
                            </td>
                            <td class="text-end font-monospace">{{ $game->markets_count }}</td>
                            <td class="small">{{ ($cmsByRawId[(int) $game->raw_id] ?? [])['title'] ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.games.edit', $game) }}" class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none"
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
@endsection
