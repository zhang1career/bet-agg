@extends('layouts.app')

@section('title', 'Group '.$gameGroup->code)

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.game-groups.index'),
        'backLabel' => '返回分组列表',
    ])

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0"><code class="small">{{ $gameGroup->code }}</code> <span class="text-muted">#{{ $gameGroup->id }}</span></h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.game-groups.edit', $gameGroup) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            <button type="button" class="btn btn-outline-danger btn-sm"
                    title="Delete" aria-label="Delete"
                    data-mall-delete-url="{{ route('admin.game-groups.destroy', $gameGroup) }}"
                    data-mall-delete-message="删除分组 {{ $gameGroup->code }}？将解除 biz_x / biz_y 关联，不删除赛事或主体。">
                Delete group
            </button>
        </div>
    </div>

    <p class="text-muted small mb-3">关联赛事请在 <a href="{{ route('admin.games.index') }}">Games</a> 新建 / 编辑页通过多选分组维护；此页仅查看。</p>

    <div class="mall-console-card card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Local ID</dt>
                <dd class="col-sm-9 font-monospace">{{ $gameGroup->id }}</dd>
                <dt class="col-sm-3">Code</dt>
                <dd class="col-sm-9 font-monospace"><code>{{ $gameGroup->code }}</code></dd>
                <dt class="col-sm-3">Timestamps</dt>
                <dd class="col-sm-9 text-muted small">ct {{ \App\Support\MillisTimestampDisplay::format($gameGroup->ct) }}
                    · ut {{ \App\Support\MillisTimestampDisplay::format($gameGroup->ut) }}</dd>
            </dl>
        </div>
    </div>

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h3 class="h6 mb-0">关联赛事</h3>
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
                            <td colspan="4" class="text-center text-muted py-4">暂无关联赛事。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
