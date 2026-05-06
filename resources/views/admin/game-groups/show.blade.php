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
                    data-mall-delete-message="删除分组 {{ $gameGroup->code }}？关联的赛事将仅解除映射，不会被删除。">
                Delete group
            </button>
        </div>
    </div>

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

    @error('detach')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    @if($availableGames->isNotEmpty())
        <div class="mall-console-card card shadow-sm mb-4">
            <div class="card-body">
                <h4 class="h6 mb-3">添加赛事到此分组</h4>
                <form method="post" action="{{ route('admin.game-groups.games.store', $gameGroup) }}"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label" for="game_id">本地 Game</label>
                        <select name="game_id" id="game_id" class="form-select @error('game_id') is-invalid @enderror" required>
                            @foreach($availableGames as $g)
                                <option value="{{ $g->id }}" @selected((int) old('game_id') === $g->id)>
                                    Local #{{ $g->id }} · raw {{ $g->raw_id }}
                                </option>
                            @endforeach
                        </select>
                        @error('game_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">添加</button>
                    </div>
                </form>
            </div>
        </div>
    @else
        @if($gameGroup->games->isEmpty())
            <p class="text-muted small mb-3">暂无可用赛事可添加（请先在 Games 创建本地赛事）；当前分组下也没有关联赛事。</p>
        @else
            <p class="text-muted small mb-3">当前分组已包含列表中的全部本地赛事。</p>
        @endif
    @endif

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>Local ID</th>
                        <th>raw id</th>
                        <th>Status</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($gameGroup->games as $game)
                        <tr>
                            <td>
                                <a href="{{ route('admin.games.show', $game) }}" class="font-monospace">{{ $game->id }}</a>
                            </td>
                            <td class="font-monospace">{{ $game->raw_id }}</td>
                            <td>
                                @include('admin.partials.status_label', ['kind' => 'game', 'value' => $game->status])
                                <span class="text-muted">({{ $game->status }})</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="移出分组" aria-label="移除"
                                        data-mall-delete-url="{{ route('admin.game-groups.games.destroy', [$gameGroup, $game]) }}"
                                        data-mall-delete-message="从当前分组移除 Game #{{ $game->id }}？（不会删除赛事本身）">
                                    @include('admin.partials.icon_trash')
                                </button>
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
