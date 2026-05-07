@extends('layouts.app')

@section('title', __('console.pages.game_detail', ['id' => $game->id]))

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.games.index'),
        'backLabel' => __('console.games.back_list'),
    ])

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ __('console.pages.game_detail', ['id' => $game->id]) }}</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.games.index', ['mall_edit' => $game->id]) }}" class="btn btn-outline-primary btn-sm">{{ __('console.btn.edit') }}</a>
        </div>
    </div>

    @if($errors->has('delete'))
        <div class="alert alert-danger">{{ $errors->first('delete') }}</div>
    @endif

    @php $cdnBase = rtrim((string) config('services.cloudfront.domain'), '/'); @endphp

    <div class="mall-console-card card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('console.games.local_id') }}</dt>
                <dd class="col-sm-9 font-monospace">{{ $game->id }}</dd>
                <dt class="col-sm-3">{{ __('console.games.raw_id_cms') }}</dt>
                <dd class="col-sm-9 font-monospace">{{ $game->raw_id }}</dd>
                <dt class="col-sm-3">{{ __('console.games.title_cms') }}</dt>
                <dd class="col-sm-9">
                    @if(is_array($cms_game))
                        {{ $cms_game['title'] ?? '—' }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </dd>
                <dt class="col-sm-3">{{ __('console.games.starts_at_cms') }}</dt>
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
                        <span class="text-muted">{{ __('console.games.cms_unavailable_raw', ['raw_id' => $game->raw_id]) }}</span>
                    @endif
                </dd>
                @php
                    $cmsBanner = is_array($cms_game) ? ($cms_game['banner'] ?? null) : null;
                    $cmsMain = is_array($cms_game) ? ($cms_game['main_media'] ?? null) : null;
                @endphp
                <dt class="col-sm-3">{{ __('console.games.banner_cms') }}</dt>
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
                <dt class="col-sm-3">{{ __('console.games.main_media_cms') }}</dt>
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
                <dt class="col-sm-3">{{ __('console.games.status') }}</dt>
                <dd class="col-sm-9">
                    @include('admin.partials.status_label', ['kind' => 'game', 'value' => $game->status])
                    <span class="text-muted">({{ $game->status }})</span>
                </dd>
                <dt class="col-sm-3">{{ __('console.games.groups_biz_x') }}</dt>
                <dd class="col-sm-9">
                    @forelse($game->groups as $gr)
                        <code class="small">{{ $gr->code }}</code>@if(!$loop->last), @endif
                    @empty
                        <span class="text-muted">—</span>
                    @endforelse
                </dd>
                <dt class="col-sm-3">{{ __('console.games.side_ab') }}</dt>
                <dd class="col-sm-9">
                    {{ $game->sideASubject?->name ?? '—' }}
                    <span class="text-muted">{{ __('console.games.vs') }}</span>
                    {{ $game->sideBSubject?->name ?? '—' }}
                </dd>
                @if(filled($game->winning_outcomes))
                    <dt class="col-sm-3">{{ __('console.games.winning_outcomes') }}</dt>
                    <dd class="col-sm-9 font-monospace small">{{ json_encode($game->winning_outcomes) }}</dd>
                @endif
                <dt class="col-sm-3">{{ __('console.games.timestamps') }}</dt>
                <dd class="col-sm-9 text-muted small">ct {{ \App\Support\MillisTimestampDisplay::format($game->ct) }}
                    · ut {{ \App\Support\MillisTimestampDisplay::format($game->ut) }}</dd>
            </dl>
        </div>
    </div>

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h3 class="h6 mb-0">{{ __('console.list.markets_section') }}</h3>
        <a href="{{ route('admin.markets.index', ['mall_create' => 1, 'game_id' => $game->id]) }}" class="btn btn-primary btn-sm">{{ __('console.btn.new') }}</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('console.table.id') }}</th>
                        <th>{{ __('console.table.name') }}</th>
                        <th>{{ __('console.table.type') }}</th>
                        <th class="text-end">{{ __('console.table.odds_json') }}</th>
                        <th>{{ __('console.table.status') }}</th>
                        <th class="text-end text-nowrap">{{ __('console.table.actions') }}</th>
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
                                <a href="{{ route('admin.markets.index', ['mall_edit' => $m->id]) }}" class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none" title="{{ __('console.btn.edit') }}">
                                    @include('admin.partials.icon_pencil')
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('console.empty.no_markets') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
