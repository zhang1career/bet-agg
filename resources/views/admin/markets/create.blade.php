@extends('layouts.app')

@section('title', 'New market')

@section('content')
    <form method="post" action="{{ route('admin.markets.store') }}" id="market-create-form" class="bg-white shadow-sm p-4 rounded mb-4">
        @csrf
        <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h5 mb-0">Create market</h2>
            <a href="{{ $prefillGameId ? route('admin.games.show', $prefillGameId) : route('admin.markets.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>

        <div class="mb-3">
            <label class="form-label" for="game_id">Game</label>
            <select name="game_id" id="game_id" class="form-select" required>
                @foreach($games as $g)
                    <option value="{{ $g->id }}" @selected((int) old('game_id', $prefillGameId) === $g->id)>Local #{{ $g->id }} · raw {{ $g->raw_id }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" maxlength="256"
                   value="{{ old('name', '') }}" placeholder="Display label for this market">
        </div>
        <div class="mb-4">
            <label class="form-label" for="status">Market status</label>
            <select name="status" id="status" class="form-select" required
                    data-mall-dict-options="sport_market_status"
                    data-mall-dict-selected="{{ (int) old('status', 1) }}"></select>
        </div>

        <h3 class="h6">Selections</h3>
        <p class="text-muted small">Add at least one option (label, decimal odds × 1000, status).</p>

        <div class="table-responsive mb-2">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>Label</th>
                    <th style="width: 140px;">Odds × 1000</th>
                    <th style="width: 130px;">Status</th>
                    <th class="text-end" style="width: 72px;"></th>
                </tr>
                </thead>
                <tbody id="selection-rows-body">
                @php
                    $rows = old('selections', [['label' => '', 'current_odds_millis' => '1950', 'status' => '1']]);
                @endphp
                @foreach($rows as $idx => $row)
                    <tr class="selection-row">
                        <td>
                            <input type="text" name="selections[{{ $idx }}][label]" class="form-control form-control-sm"
                                   required maxlength="256" value="{{ $row['label'] ?? '' }}">
                        </td>
                        <td>
                            <input type="number" name="selections[{{ $idx }}][current_odds_millis]" class="form-control form-control-sm"
                                   required min="1000" value="{{ $row['current_odds_millis'] ?? '1950' }}">
                        </td>
                        <td>
                            <select name="selections[{{ $idx }}][status]" class="form-select form-select-sm" required
                                    data-mall-dict-options="sport_market_status"
                                    data-mall-dict-selected="{{ (int)($row['status'] ?? 1) }}"></select>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm selection-remove" title="Remove row">×</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="selection-add-row">Add selection row</button>

        <div>
            <button type="submit" class="btn btn-primary">Create market</button>
        </div>
    </form>

    <template id="selection-row-template">
        <tr class="selection-row">
            <td>
                <input type="text" name="selections[__I__][label]" class="form-control form-control-sm" required maxlength="256" value="">
            </td>
            <td>
                <input type="number" name="selections[__I__][current_odds_millis]" class="form-control form-control-sm" required min="1000" value="1950">
            </td>
            <td>
                <select name="selections[__I__][status]" class="form-select form-select-sm" required
                        data-mall-dict-options="sport_market_status"
                        data-mall-dict-selected="1"></select>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-outline-danger btn-sm selection-remove" title="Remove row">×</button>
            </td>
        </tr>
    </template>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var body = document.getElementById('selection-rows-body');
            var tpl = document.getElementById('selection-row-template');
            var addBtn = document.getElementById('selection-add-row');
            if (!body || !tpl || !addBtn) return;

            function nextIndex() {
                return body.querySelectorAll('tr.selection-row').length;
            }

            function bindRemove(row) {
                var btn = row.querySelector('.selection-remove');
                if (!btn) return;
                btn.addEventListener('click', function () {
                    if (body.querySelectorAll('tr.selection-row').length <= 1) return;
                    row.remove();
                    renumber();
                });
            }

            function renumber() {
                var rows = body.querySelectorAll('tr.selection-row');
                rows.forEach(function (row, i) {
                    row.querySelectorAll('[name^="selections["]').forEach(function (el) {
                        el.name = el.name.replace(/selections\[\d+]/, 'selections[' + i + ']');
                    });
                });
            }

            body.querySelectorAll('tr.selection-row').forEach(bindRemove);

            addBtn.addEventListener('click', function () {
                var html = tpl.innerHTML.replace(/__I__/g, String(nextIndex()));
                var wrap = document.createElement('tbody');
                wrap.innerHTML = html.trim();
                var row = wrap.firstElementChild;
                if (!row) return;
                body.appendChild(row);
                bindRemove(row);
                renumber();
                if (typeof window.mallDictPopulateSelects === 'function') {
                    window.mallDictPopulateSelects(row);
                }
            });
        });
    </script>
@endpush
