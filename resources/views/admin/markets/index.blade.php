@extends('layouts.app')

@section('title', __('console.pages.markets'))

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ __('console.list.markets') }}</h2>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="{{ route('admin.markets.index', ['mall_create' => 1]) }}" class="btn btn-primary btn-sm">{{ __('console.btn.new') }}</a>
        </div>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('console.table.id') }}</th>
                        <th>{{ __('console.table.game') }}</th>
                        <th>{{ __('console.table.name') }}</th>
                        <th>{{ __('console.table.type') }}</th>
                        <th>{{ __('console.table.status') }}</th>
                        <th class="text-end text-nowrap">{{ __('console.table.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($markets as $m)
                        <tr>
                            <td>
                                <a href="{{ route('admin.markets.show', $m) }}" class="font-monospace">{{ $m->id }}</a>
                            </td>
                            <td class="small">
                                <a href="{{ route('admin.games.show', $m->gid) }}">{{ ($cmsByRawId[(int) ($m->game?->raw_id ?? 0)] ?? [])['title'] ?? '—' }}</a>
                            </td>
                            <td>{{ $m->name }}</td>
                            <td class="small font-monospace">{{ $m->type->value }}</td>
                            <td>
                                @include('admin.partials.status_label', ['kind' => 'market', 'value' => $m->status])
                                <span class="text-muted">({{ $m->status }})</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.markets.index', ['mall_edit' => $m->id]) }}"
                                   class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none" title="{{ __('console.btn.edit') }}">
                                    @include('admin.partials.icon_pencil')
                                </a>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="{{ __('console.btn.delete') }}" aria-label="{{ __('console.btn.delete') }}"
                                        data-mall-delete-url="{{ route('admin.markets.destroy', $m) }}"
                                        data-mall-delete-message="{{ __('console.markets.delete_confirm', ['id' => $m->id]) }}">
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

    {{ $markets->links() }}
@endsection

@push('modals')
    <div class="modal fade" id="mallModalMarketCreate" tabindex="-1" aria-hidden="true"
         data-mall-modal="1"
         data-mall-strip-query="mall_create game_id"
         @if($mallCreate) data-mall-auto-show="1" @endif>
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.markets.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ __('console.markets.create_title') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="mmc_game_id">{{ __('console.table.game') }}</label>
                            <select name="game_id" id="mmc_game_id" class="form-select" required>
                                @include('admin.partials.game_select_options', [
                                    'games' => $games,
                                    'gameSelectLabels' => $gameSelectLabels,
                                    'selectedGameId' => (int) old('game_id', $prefillGameId),
                                ])
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="mmc_name">{{ __('console.table.name') }}</label>
                            <input type="text" name="name" id="mmc_name" class="form-control" maxlength="256"
                                   value="{{ old('name', __('console.markets.default_name')) }}" placeholder="{{ __('console.markets.name_placeholder') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="mmc_type">{{ __('console.table.type') }}</label>
                            <select name="type" id="mmc_type" class="form-select" required>
                                @foreach(\App\Enums\MarketType::cases() as $mt)
                                    <option value="{{ $mt->value }}" @selected((int) old('type', $mt->value) === $mt->value)>
                                        {{ $mt->value }} — {{ $mt->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="mmc_status">{{ __('console.markets.market_status') }}</label>
                            <select name="status" id="mmc_status" class="form-select" required
                                    data-mall-dict-options="market_status"
                                    data-mall-dict-selected="{{ (int) old('status', 1) }}"></select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.markets.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('console.btn.create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($modalMarket)
        <div class="modal fade" id="mallModalMarketEdit" tabindex="-1" aria-hidden="true"
             data-mall-modal="1"
             data-mall-strip-query="mall_edit"
             data-mall-auto-show="1">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.markets.update', $modalMarket) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('console.markets.edit_title', ['id' => $modalMarket->id]) }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="mme_game_id">{{ __('console.table.game') }}</label>
                                <select name="game_id" id="mme_game_id" class="form-select" required>
                                    @include('admin.partials.game_select_options', [
                                        'games' => $games,
                                        'gameSelectLabels' => $gameSelectLabels,
                                        'selectedGameId' => (int) old('game_id', $modalMarket->gid),
                                    ])
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="mme_name">{{ __('console.table.name') }}</label>
                                <input type="text" name="name" id="mme_name" class="form-control" maxlength="256"
                                       value="{{ old('name', $modalMarket->name) }}" placeholder="{{ __('console.markets.name_placeholder') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="mme_type">{{ __('console.table.type') }}</label>
                                <select name="type" id="mme_type" class="form-select" required>
                                    @foreach(\App\Enums\MarketType::cases() as $mt)
                                        <option value="{{ $mt->value }}" @selected((int) old('type', $modalMarket->type->value) === $mt->value)>
                                            {{ $mt->value }} — {{ $mt->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="mme_status">{{ __('console.markets.market_status') }}</label>
                                <select name="status" id="mme_status" class="form-select" required
                                        data-mall-dict-options="market_status"
                                        data-mall-dict-selected="{{ (int) old('status', $modalMarket->status) }}"></select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('admin.markets.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('console.btn.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endpush
