@extends('layouts.app')

@section('title', __('console.pages.points'))

@section('content')
    <nav class="mall-subnav d-flex flex-wrap gap-2 mb-4" aria-label="{{ __('console.points.nav_label') }}">
        <a href="{{ route('admin.points.index', ['tab' => 'balances']) }}"
           class="btn btn-sm {{ $tab === 'balances' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('console.list.balances') }}</a>
        <a href="{{ route('admin.points.index', ['tab' => 'flows']) }}"
           class="btn btn-sm {{ $tab === 'flows' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('console.list.flows') }}</a>
    </nav>

    @if($errors->has('delete'))
        <div class="alert alert-danger py-2">{{ $errors->first('delete') }}</div>
    @endif

    @if($tab === 'balances' && $balances)
        <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h5 mb-0">{{ __('console.list.balances') }}</h2>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mallModalOpenAccount">{{ __('console.btn.new') }}</button>
        </div>

        <div class="mall-console-card card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 mall-data-table align-middle"
                           @if($balances->isNotEmpty())
                               data-mall-balance-user-ids="{{ $balances->pluck('uid')->unique()->join(',') }}"
                           @endif>
                        <thead>
                        <tr>
                            <th>{{ __('console.table.id') }}</th>
                            <th>{{ __('console.table.uid') }}</th>
                            <th>{{ __('console.table.username') }}</th>
                            <th class="text-end">{{ __('console.table.balance_points') }}</th>
                            <th>{{ __('console.table.updated') }}</th>
                            <th class="text-end text-nowrap">{{ __('console.table.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($balances as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.points.balances.show', $row->id) }}" class="font-monospace">{{ $row->id }}</a>
                                </td>
                                <td>
                                    <button type="button"
                                            class="btn btn-link p-0 align-baseline font-monospace text-decoration-none"
                                            title="{{ __('console.points.view_user') }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#mallModalUserDetail"
                                            data-gateway-user-id="{{ $row->uid }}"
                                            data-gateway-user-url="{{ route('admin.users.show', ['user_id' => $row->uid]) }}">
                                        {{ $row->uid }}
                                    </button>
                                </td>
                                <td class="mall-points-balance-username text-muted small"
                                    data-mall-balance-username-for="{{ $row->uid }}">—</td>
                                <td class="text-end font-monospace">{{ number_format((int) $row->balance) }}</td>
                                <td class="text-muted small">{{ \App\Support\MillisTimestampDisplay::format($row->ut) }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded" title="{{ __('console.points.adjust') }}"
                                            data-bs-toggle="modal" data-bs-target="#mallModalAdjust"
                                            data-balance-uid="{{ $row->uid }}">
                                        @include('admin.partials.icon_pencil')
                                    </button>
                                    <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger" title="{{ __('console.btn.delete') }}"
                                            data-mall-delete-url="{{ route('admin.points.balances.destroy', $row->id) }}"
                                            data-mall-delete-message="{{ __('console.points.delete_balance_confirm', ['id' => $row->id]) }}">
                                        @include('admin.partials.icon_trash')
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ __('console.empty.no_accounts') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        {{ $balances->appends(['tab' => 'balances'])->links() }}

    @elseif($tab === 'flows' && $flows)
        <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h5 mb-0">{{ __('console.list.flows') }}</h2>
        </div>

        <div class="mall-console-card card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                        <thead>
                        <tr>
                            <th>{{ __('console.table.id') }}</th>
                            <th>{{ __('console.table.uid') }}</th>
                            <th>{{ __('console.table.oid') }}</th>
                            <th class="text-end">{{ __('console.table.amount') }}</th>
                            <th>{{ __('console.table.state') }}</th>
                            <th class="text-end text-nowrap">{{ __('console.table.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($flows as $f)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.points.flows.show', $f->id) }}" class="font-monospace">{{ $f->id }}</a>
                                </td>
                                <td>{{ $f->uid }}</td>
                                <td>{{ $f->oid }}</td>
                                <td class="text-end font-monospace">{{ number_format((int) $f->amount) }}</td>
                                <td>
                                    <span class="badge mall-badge-soft"
                                          data-mall-dict-code="points_hold_state"
                                          data-mall-dict-value="{{ $f->state->value }}">{{ $f->state->value }}</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded" title="{{ __('console.points.view') }}"
                                            data-bs-toggle="modal" data-bs-target="#mallModalFlowView"
                                            data-flow-id="{{ $f->id }}"
                                            data-flow-uid="{{ $f->uid }}"
                                            data-flow-oid="{{ $f->oid }}"
                                            data-flow-amount="{{ $f->amount }}"
                                            data-flow-state="{{ $f->state->value }}"
                                            data-flow-ct="{{ \App\Support\MillisTimestampDisplay::format($f->ct) }}"
                                            data-flow-ut="{{ \App\Support\MillisTimestampDisplay::format($f->ut) }}">
                                        @include('admin.partials.icon_pencil')
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ __('console.empty.no_flows') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        {{ $flows->appends(['tab' => 'flows'])->links() }}
    @endif

    {{-- Open account --}}
    <div class="modal fade" id="mallModalOpenAccount" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.points.accounts.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ __('console.points.new_account') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        @if($errors->has('account'))
                            <div class="alert alert-danger py-2">{{ $errors->first('account') }}</div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label" for="m-open-uid">{{ __('console.points.user_id') }}</label>
                            <input type="number" name="uid" id="m-open-uid" class="form-control" required min="1" value="{{ old('uid') }}">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="m-open-bal">{{ __('console.points.initial_balance') }}</label>
                            <input type="number" name="balance" id="m-open-bal" class="form-control" min="0" value="{{ old('balance', 0) }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('console.btn.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('console.btn.create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Adjust --}}
    <div class="modal fade" id="mallModalAdjust" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.points.adjust') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ __('console.points.adjust_balance') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        @if($errors->has('adjust'))
                            <div class="alert alert-danger py-2">{{ $errors->first('adjust') }}</div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label" for="m-adj-uid">{{ __('console.points.user_id') }}</label>
                            <input type="number" name="uid" id="m-adj-uid" class="form-control" required min="1" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="m-adj-delta">{{ __('console.points.delta') }}</label>
                            <input type="number" name="delta_points" id="m-adj-delta" class="form-control" required value="{{ old('delta_points') }}">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="m-adj-oid">{{ __('console.points.order_id_optional') }}</label>
                            <input type="number" name="oid" id="m-adj-oid" class="form-control" min="0" value="{{ old('oid', 0) }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('console.btn.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('console.btn.apply') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- User JSON via GET /admin/users/{user_id} --}}
    <div class="modal fade" id="mallModalUserDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">{{ __('console.points.user_title') }} <span id="m-user-title-id"></span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2" id="m-user-status">{{ __('console.js.loading') }}</p>
                    <div id="m-user-detail-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">{{ __('console.btn.close') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Flow read-only --}}
    <div class="modal fade" id="mallModalFlowView" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">{{ __('console.points.flow_title') }} <span id="m-flow-title-id"></span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
                </div>
                <div class="modal-body small">
                    <dl class="row mb-0">
                        <dt class="col-4">{{ __('console.table.uid') }}</dt>
                        <dd class="col-8" id="m-flow-uid"></dd>
                        <dt class="col-4">{{ __('console.table.oid') }}</dt>
                        <dd class="col-8" id="m-flow-oid"></dd>
                        <dt class="col-4">{{ __('console.table.amount') }}</dt>
                        <dd class="col-8 font-monospace" id="m-flow-amount"></dd>
                        <dt class="col-4">{{ __('console.table.state') }}</dt>
                        <dd class="col-8" id="m-flow-state"></dd>
                        <dt class="col-4">{{ __('console.points.flow_ct_ut') }}</dt>
                        <dd class="col-8" id="m-flow-ctut"></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">{{ __('console.btn.close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var I18N = window.MALL_CONSOLE_I18N || {};
            var balanceUserTable = document.querySelector('table[data-mall-balance-user-ids]');
            if (balanceUserTable) {
                var rawIds = balanceUserTable.getAttribute('data-mall-balance-user-ids');
                var batchUrl = @json(route('admin.users.index'));
                if (rawIds && rawIds.trim() !== '') {
                    fetch(batchUrl + '?user_ids=' + encodeURIComponent(rawIds), { headers: { Accept: 'application/json' } })
                        .then(function (r) {
                            return r.json().then(function (j) {
                                return { ok: r.ok, json: j };
                            });
                        })
                        .then(function (res) {
                            if (!res.ok || !res.json || !Array.isArray(res.json.users)) {
                                return;
                            }
                            var byId = {};
                            res.json.users.forEach(function (u) {
                                if (u && typeof u === 'object' && u.id !== undefined && u.id !== null) {
                                    var un = u.username;
                                    byId[String(u.id)] = un === undefined || un === null ? '' : String(un);
                                }
                            });
                            balanceUserTable.querySelectorAll('[data-mall-balance-username-for]').forEach(function (cell) {
                                var uid = cell.getAttribute('data-mall-balance-username-for');
                                if (!uid) {
                                    return;
                                }
                                var name = byId[uid];
                                if (name !== undefined && name !== '') {
                                    cell.textContent = name;
                                    cell.classList.remove('text-muted');
                                }
                            });
                        })
                        .catch(function () { /* leave placeholders */ });
                }
            }
            var adjModal = document.getElementById('mallModalAdjust');
            if (adjModal) {
                adjModal.addEventListener('show.bs.modal', function (e) {
                    var btn = e.relatedTarget;
                    var uid = btn && btn.getAttribute('data-balance-uid');
                    var input = document.getElementById('m-adj-uid');
                    if (input && uid) {
                        input.value = uid;
                    }
                });
            }
            var userModal = document.getElementById('mallModalUserDetail');
            if (userModal) {
                userModal.addEventListener('show.bs.modal', function (e) {
                    var btn = e.relatedTarget;
                    var url = btn && btn.getAttribute('data-gateway-user-url');
                    var uid = btn && btn.getAttribute('data-gateway-user-id');
                    var titleEl = document.getElementById('m-user-title-id');
                    var statusEl = document.getElementById('m-user-status');
                    var bodyEl = document.getElementById('m-user-detail-body');
                    if (!url || !bodyEl) {
                        return;
                    }
                    if (titleEl) {
                        titleEl.textContent = uid ? '#' + uid : '';
                    }
                    if (statusEl) {
                        statusEl.textContent = I18N.loading || '';
                        statusEl.classList.remove('text-danger', 'd-none');
                        statusEl.classList.add('text-muted');
                    }
                    bodyEl.innerHTML = '';
                    fetch(url, { headers: { Accept: 'application/json' } })
                        .then(function (r) {
                            return r.json().then(function (j) {
                                return { ok: r.ok, status: r.status, json: j };
                            });
                        })
                        .then(function (res) {
                            if (!statusEl) {
                                return;
                            }
                            if (!res.ok) {
                                statusEl.textContent = (res.json && res.json.message) ? String(res.json.message) : ('HTTP ' + res.status);
                                statusEl.classList.remove('text-muted');
                                statusEl.classList.add('text-danger');
                                return;
                            }
                            var user = res.json && res.json.user;
                            if (!user || typeof user !== 'object') {
                                statusEl.textContent = I18N.invalidResponse || '';
                                statusEl.classList.remove('text-muted');
                                statusEl.classList.add('text-danger');
                                return;
                            }
                            statusEl.textContent = '';
                            statusEl.classList.add('d-none');
                            var dl = document.createElement('dl');
                            dl.className = 'row mb-0 small';
                            Object.keys(user).sort().forEach(function (k) {
                                var v = user[k];
                                var dt = document.createElement('dt');
                                dt.className = 'col-sm-4 text-break';
                                dt.textContent = k;
                                var dd = document.createElement('dd');
                                dd.className = 'col-sm-8';
                                if (v !== null && typeof v === 'object') {
                                    var pre = document.createElement('pre');
                                    pre.className = 'mb-0 small font-monospace';
                                    pre.textContent = JSON.stringify(v, null, 2);
                                    dd.appendChild(pre);
                                } else {
                                    dd.textContent = (v === null || v === undefined) ? '—' : String(v);
                                }
                                dl.appendChild(dt);
                                dl.appendChild(dd);
                            });
                            bodyEl.appendChild(dl);
                        })
                        .catch(function () {
                            if (statusEl) {
                                statusEl.textContent = I18N.requestFailed || '';
                                statusEl.classList.remove('text-muted');
                                statusEl.classList.add('text-danger');
                            }
                        });
                });
                userModal.addEventListener('hidden.bs.modal', function () {
                    var s = document.getElementById('m-user-status');
                    var b = document.getElementById('m-user-detail-body');
                    if (s) {
                        s.textContent = I18N.loading || '';
                        s.classList.remove('text-danger', 'd-none');
                        s.classList.add('text-muted');
                    }
                    if (b) {
                        b.innerHTML = '';
                    }
                });
            }
            var flowModal = document.getElementById('mallModalFlowView');
            if (flowModal) {
                flowModal.addEventListener('show.bs.modal', function (e) {
                    var btn = e.relatedTarget;
                    if (!btn) {
                        return;
                    }
                    var id = document.getElementById('m-flow-title-id');
                    if (id) {
                        id.textContent = '#' + (btn.getAttribute('data-flow-id') || '');
                    }
                    var map = [
                        ['m-flow-uid', 'data-flow-uid'],
                        ['m-flow-oid', 'data-flow-oid'],
                        ['m-flow-amount', 'data-flow-amount'],
                    ];
                    map.forEach(function (pair) {
                        var el = document.getElementById(pair[0]);
                        if (el) {
                            el.textContent = btn.getAttribute(pair[1]) || '—';
                        }
                    });
                    var stateVal = btn.getAttribute('data-flow-state') || '';
                    var elState = document.getElementById('m-flow-state');
                    if (elState) {
                        if (window.mallDictEnsure) {
                            window.mallDictEnsure(['points_hold_state'], function () {
                                elState.textContent = window.mallDictLabel
                                    ? window.mallDictLabel('points_hold_state', stateVal)
                                    : stateVal;
                            });
                        } else {
                            elState.textContent = stateVal || '—';
                        }
                    }
                    var ct = btn.getAttribute('data-flow-ct') || '';
                    var ut = btn.getAttribute('data-flow-ut') || '';
                    var elCt = document.getElementById('m-flow-ctut');
                    if (elCt) {
                        elCt.textContent = ct + ' / ' + ut;
                    }
                });
            }
        })();
    </script>
    @if($errors->has('account') || $errors->has('adjust'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var m = new bootstrap.Modal(document.getElementById('{{ $errors->has('account') ? 'mallModalOpenAccount' : 'mallModalAdjust' }}'));
                m.show();
            });
        </script>
    @endif
@endpush
