@extends('layouts.app')

@section('title', 'Game #'.$game->id)

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.games.index'),
        'backLabel' => '返回游戏列表',
    ])

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Game #{{ $game->id }}</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.games.index', ['mall_edit' => $game->id]) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        </div>
    </div>

    @if($errors->has('delete'))
        <div class="alert alert-danger">{{ $errors->first('delete') }}</div>
    @endif

    @php $cdnBase = rtrim((string) config('services.cloudfront.domain'), '/'); @endphp

    <div class="mall-console-card card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Local ID</dt>
                <dd class="col-sm-9 font-monospace">{{ $game->id }}</dd>
                <dt class="col-sm-3">Raw id (CMS)</dt>
                <dd class="col-sm-9 font-monospace">{{ $game->raw_id }}</dd>
                <dt class="col-sm-3">Title (CMS)</dt>
                <dd class="col-sm-9">
                    @if(is_array($cms_game))
                        {{ $cms_game['title'] ?? '—' }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </dd>
                <dt class="col-sm-3">Starts at (CMS)</dt>
                <dd class="col-sm-9">
                    @if(is_array($cms_game))
                        @php $cmsStarts = (int) ($cms_game['starts_at'] ?? 0); @endphp
                        @if($cmsStarts > 0)
                            {{ \App\Support\MillisTimestampDisplay::format($cmsStarts) }}
                            <span class="text-muted small font-monospace">({{ $cmsStarts }} ms)</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    @else
                        <span class="text-muted">CMS unavailable for raw_id {{ $game->raw_id }}</span>
                    @endif
                </dd>
                @php
                    $cmsBanner = is_array($cms_game) ? ($cms_game['banner'] ?? null) : null;
                    $cmsMain = is_array($cms_game) ? ($cms_game['main_media'] ?? null) : null;
                @endphp
                <dt class="col-sm-3">Banner (CMS)</dt>
                <dd class="col-sm-9">
                    @if(filled($cmsBanner))
                        <code class="small text-break d-block mb-2">{{ $cmsBanner }}</code>
                        @if($cdnBase !== '')
                            <img src="{{ $cdnBase.'/'.ltrim($cmsBanner, '/') }}" alt="Banner"
                                 class="img-fluid rounded border" style="max-height: 180px">
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </dd>
                <dt class="col-sm-3">Main media (CMS)</dt>
                <dd class="col-sm-9">
                    @if(filled($cmsMain))
                        <code class="small text-break d-block mb-2">{{ $cmsMain }}</code>
                        @if($cdnBase !== '')
                            <img src="{{ $cdnBase.'/'.ltrim($cmsMain, '/') }}" alt="Main media"
                                 class="img-fluid rounded border" style="max-height: 180px">
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @include('admin.partials.status_label', ['kind' => 'game', 'value' => $game->status])
                    <span class="text-muted">({{ $game->status }})</span>
                </dd>
                <dt class="col-sm-3">Groups (biz_x)</dt>
                <dd class="col-sm-9">
                    @forelse($game->groups as $gr)
                        <code class="small">{{ $gr->code }}</code>@if(!$loop->last), @endif
                    @empty
                        <span class="text-muted">—</span>
                    @endforelse
                </dd>
                <dt class="col-sm-3">Side A / B</dt>
                <dd class="col-sm-9">
                    {{ $game->sideASubject?->name ?? '—' }}
                    <span class="text-muted"> vs </span>
                    {{ $game->sideBSubject?->name ?? '—' }}
                </dd>
                @if(filled($game->winning_outcomes))
                    <dt class="col-sm-3">Winning outcomes</dt>
                    <dd class="col-sm-9 font-monospace small">{{ json_encode($game->winning_outcomes) }}</dd>
                @endif
                <dt class="col-sm-3">Timestamps</dt>
                <dd class="col-sm-9 text-muted small">ct {{ \App\Support\MillisTimestampDisplay::format($game->ct) }}
                    · ut {{ \App\Support\MillisTimestampDisplay::format($game->ut) }}</dd>
            </dl>
        </div>
    </div>

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h3 class="h6 mb-0">Markets</h3>
        <a href="{{ route('admin.markets.index', ['mall_create' => 1, 'game_id' => $game->id]) }}" class="btn btn-primary btn-sm">New market</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-end">Odds JSON ×1000</th>
                        <th>Status</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($game->markets as $m)
                        <tr>
                            <td>
                                <a href="{{ route('admin.markets.show', $m) }}" class="font-monospace">{{ $m->id }}</a>
                            </td>
                            <td>{{ $m->name }}</td>
                            <td class="font-monospace small">{{ $m->type->value }} · {{ $m->type->label() }}</td>
                            <td class="text-end font-monospace small">
                                {{ json_encode($m->outcomeOddsMillisMap(), JSON_UNESCAPED_UNICODE) }}
                            </td>
                            <td>
                                @include('admin.partials.status_label', ['kind' => 'market', 'value' => $m->status])
                                <span class="text-muted">({{ $m->status }})</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.markets.index', ['mall_edit' => $m->id]) }}" class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none" title="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No markets yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
