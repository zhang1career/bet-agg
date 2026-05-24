@extends('layouts.app')

@section('title', __('console.pages.games'))

@section('content')
    @php
        $retainQs = request()->except('page');
        $selSettleGid = (int) old('game_id', 0);
        if ($selSettleGid < 1 && $mallSettlementGamePrefill !== null && $mallSettlementGamePrefill >= 1 && $settlementOpenGames->contains('id', $mallSettlementGamePrefill)) {
            $selSettleGid = $mallSettlementGamePrefill;
        }
        if ($selSettleGid < 1) {
            $selSettleGid = (int) ($settlementOpenGames->first()->id ?? 0);
        }
    @endphp

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ __('console.list.games') }}</h2>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <form method="get" action="{{ route('admin.games.index') }}" class="d-flex flex-wrap gap-2 align-items-center mb-0">
                @foreach($retainQs as $k => $v)
                    @continue(in_array($k, ['status', 'sort', 'dir', 'mall_settlement', 'mall_settlement_game'], true))
                    @if(is_array($v))
                        @continue
                    @endif
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <input type="hidden" name="sort" value="{{ $listSort }}">
                <input type="hidden" name="dir" value="{{ $listDir }}">
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <label for="games_filter_status" class="form-label small mb-0 text-nowrap text-muted">{{ __('console.table.status') }}</label>
                    <select name="status" id="games_filter_status" class="form-select form-select-sm" style="width: auto; min-width: 9rem;">
                        <option value="" @selected($listStatusFilter === null)>{{ __('console.games.filter_status_all') }}</option>
                        <option value="{{ \App\Models\Game::STATUS_OPEN }}" @selected($listStatusFilter === \App\Models\Game::STATUS_OPEN)>{{ __('console.games.filter_status_open') }}</option>
                        <option value="{{ \App\Models\Game::STATUS_CLOSED }}" @selected($listStatusFilter === \App\Models\Game::STATUS_CLOSED)>{{ __('console.games.filter_status_closed') }}</option>
                        <option value="{{ \App\Models\Game::STATUS_PENDING_SETTLEMENT }}" @selected($listStatusFilter === \App\Models\Game::STATUS_PENDING_SETTLEMENT)>{{ __('console.games.filter_status_pending_settlement') }}</option>
                        <option value="{{ \App\Models\Game::STATUS_SETTLED }}" @selected($listStatusFilter === \App\Models\Game::STATUS_SETTLED)>{{ __('console.games.filter_status_settled') }}</option>
                    </select>
                </div>
                @include('admin.partials.btn_list_filter_submit')
            </form>
            <a href="{{ route('admin.games.index', ['mall_create' => 1]) }}" class="btn btn-primary btn-sm flex-shrink-0">{{ __('console.btn.new') }}</a>
        </div>
    </div>

    @if(!empty($gamesListTruncated))
        <div class="alert alert-warning py-2 small mb-3">{{ __('console.games.list_truncated', ['cap' => $gamesListCap]) }}</div>
    @endif

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
                        @include('admin.partials.th_sort_get', [
                            'routeName' => 'admin.games.index',
                            'retainQuery' => $retainQs,
                            'column' => 'starts_at',
                            'currentSort' => $listSort,
                            'currentDir' => $listDir,
                            'label' => __('console.table.starts_at'),
                            'ascTitle' => __('console.games.sort_starts_at_asc'),
                            'descTitle' => __('console.games.sort_starts_at_desc'),
                        ])
                        <th class="text-end">{{ __('console.table.markets') }}</th>
                        <th class="text-end text-nowrap">{{ __('console.table.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($games as $game)
                        @php
                            $cmsRow = $cmsByRawId[(int) $game->raw_id] ?? [];
                            $startsMs = isset($cmsRow['starts_at']) ? (int) $cmsRow['starts_at'] : 0;
                            $canSettle = $game->status === \App\Models\Game::STATUS_OPEN
                                && $game->side_a_subj_id !== null
                                && $game->side_b_subj_id !== null;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.games.show', $game) }}" class="font-monospace">{{ $game->id }}</a>
                            </td>
                            <td class="font-monospace">{{ $game->raw_id }}</td>
                            <td class="small">{{ ($cmsRow['title'] ?? null) ? $cmsRow['title'] : '—' }}</td>
                            <td>
                                @include('admin.partials.status_label', ['kind' => 'game', 'value' => $game->status])
                                <span class="text-muted">({{ $game->status }})</span>
                            </td>
                            <td class="text-nowrap small">{{ \App\Support\MillisTimestampDisplay::formatYmdHi($startsMs > 0 ? $startsMs : null) }}</td>
                            <td class="text-end font-monospace">{{ $game->markets_count }}</td>
                            <td class="text-end text-nowrap">
                                @if($canSettle)
                                    <button type="button"
                                            class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none text-success"
                                            title="{{ __('console.games.settlement_icon_title') }}"
                                            aria-label="{{ __('console.games.settlement_icon_title') }}"
                                            data-bs-toggle="modal" data-bs-target="#mallModalSettlement"
                                            data-mall-settlement-game-id="{{ $game->id }}">
                                        @include('admin.partials.icon_settlement')
                                    </button>
                                @else
                                    <span class="d-inline-flex p-1 rounded text-muted opacity-50"
                                          title="{{ __('console.games.settlement_icon_disabled') }}"
                                          aria-label="{{ __('console.games.settlement_icon_disabled') }}">
                                        @include('admin.partials.icon_settlement')
                                    </span>
                                @endif
                                <a href="{{ route('admin.games.index', ['mall_edit' => $game->id]) }}"
                                   class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none"
                                   title="{{ __('console.btn.edit') }}" aria-label="{{ __('console.btn.edit') }}">
                                    @include('admin.partials.icon_pencil')
                                </a>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="{{ __('console.btn.delete') }}" aria-label="{{ __('console.btn.delete') }}"
                                        data-mall-delete-url="{{ route('admin.games.destroy', $game) }}"
                                        data-mall-delete-message="{{ __('console.games.delete_confirm', ['id' => $game->id]) }}">
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

@push('modals')
    <div class="modal fade" id="mallModalSettlement" tabindex="-1" aria-hidden="true"
         data-mall-modal="1"
         data-mall-strip-query="mall_settlement mall_settlement_game"
         @if($mallSettlement) data-mall-auto-show="1" @endif>
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.settlement.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ __('console.settlement.modal_title') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">{{ __('console.settlement.hint') }}</p>
                        <div class="mb-3">
                            <label class="form-label" for="settlement_game_id">{{ __('console.settlement.open_game') }}</label>
                            <select name="game_id" id="settlement_game_id" class="form-select" required>
                                @if($settlementOpenGames->isEmpty())
                                    <option value="" disabled>{{ __('console.settlement.no_open_games') }}</option>
                                @else
                                    @include('admin.partials.game_select_options', [
                                        'games' => $settlementOpenGames,
                                        'gameSelectLabels' => $settlementGameSelectLabels,
                                        'selectedGameId' => $selSettleGid,
                                    ])
                                @endif
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="result_payload">{{ __('console.settlement.result') }}</label>
                            <select name="result_payload" id="result_payload" class="form-select" required></select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.games.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                        <button type="submit" class="btn btn-primary" @if($settlementOpenGames->isEmpty()) disabled @endif>{{ __('console.btn.submit_settlement') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Create --}}
    <div class="modal fade" id="mallModalGameCreate" tabindex="-1" aria-hidden="true"
         data-mall-modal="1"
         data-mall-strip-query="mall_create"
         @if($mallCreate) data-mall-auto-show="1" @endif>
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.games.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ __('console.games.create_title') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">{{ __('console.games.create_blurb') }}</p>
                        @if($errors->has('cms'))
                            <div class="alert alert-danger">{{ $errors->first('cms') }}</div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label" for="game_name_gc">{{ __('console.games.cms_title') }}</label>
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
                            <label class="form-label" for="game_status_gc">{{ __('console.games.status') }}</label>
                            <select name="status" id="game_status_gc" class="form-select" required
                                    data-mall-dict-options="game_status"
                                    data-mall-dict-selected="{{ (int) old('status', 1) }}"></select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.games.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('console.btn.create') }}</button>
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
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.games.update', $game) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('console.games.edit_title', ['id' => $game->id]) }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                        </div>
                        <div class="modal-body">
                            @if(! is_array($cms_game))
                                <p class="text-muted small">
                                    <strong class="text-warning">{{ __('console.games.cms_unavailable') }}</strong>
                                </p>
                            @endif
                            @if($errors->has('cms'))
                                <div class="alert alert-danger">{{ $errors->first('cms') }}</div>
                            @endif
                            @if(is_array($cms_game))
                                <div class="mb-3">
                                    <label class="form-label" for="game_name_ge">{{ __('console.games.cms_title') }}</label>
                                    <input type="text" name="name" id="game_name_ge" class="form-control" required maxlength="500" value="{{ $cmsName }}">
                                </div>
                                @include('admin.games.partials.starts-at-field', ['startsAtMs' => $cmsStarts, 'idSuf' => '_ge'])
                            @else
                                <div class="mb-3">
                                    <span class="form-label d-block">{{ __('console.games.cms_not_loaded_fields') }}</span>
                                    <p class="text-muted small mb-1">{{ __('console.games.cms_not_loaded_body', ['raw_id' => $game->raw_id]) }}</p>
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
                                'selectedSideA' => $game->side_a_subj_id,
                                'selectedSideB' => $game->side_b_subj_id,
                                'idSuf' => '_ge',
                            ])
                            <div class="mb-3">
                                <label class="form-label" for="game_status_ge">{{ __('console.games.status') }}</label>
                                <select name="status" id="game_status_ge" class="form-select" required
                                        data-mall-dict-options="game_status"
                                        data-mall-dict-selected="{{ (int) old('status', $game->status) }}"></select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('admin.games.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('console.btn.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var byGame = @json($settlementOutcomesByGame);
            var gameSel = document.getElementById('settlement_game_id');
            var payloadSel = document.getElementById('result_payload');
            var settleModal = document.getElementById('mallModalSettlement');
            if (gameSel && payloadSel) {
                function refill() {
                    var gid = gameSel.value;
                    payloadSel.innerHTML = '';
                    var rows = byGame[gid] || [];
                    var oldVal = @json(old('result_payload', ''));
                    rows.forEach(function (o) {
                        var opt = document.createElement('option');
                        opt.value = o.value;
                        opt.textContent = o.label;
                        if (oldVal && oldVal === o.value) opt.selected = true;
                        payloadSel.appendChild(opt);
                    });
                }
                gameSel.addEventListener('change', refill);
                refill();
            }
            if (settleModal && gameSel) {
                settleModal.addEventListener('show.bs.modal', function (ev) {
                    var btn = ev.relatedTarget;
                    var gid = btn && btn.getAttribute('data-mall-settlement-game-id');
                    if (!gid) return;
                    var opt = gameSel.querySelector('option[value="' + gid + '"]');
                    if (!opt) return;
                    gameSel.value = gid;
                    gameSel.dispatchEvent(new Event('change'));
                });
            }
        });
    </script>
@endpush
