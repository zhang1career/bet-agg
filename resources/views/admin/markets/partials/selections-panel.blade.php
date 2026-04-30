@php
    /** @var \App\Models\SportMarket $market */
@endphp

<div class="mall-console-card card shadow-sm mb-4">
    <div class="card-body">
        <h3 class="h6 mb-3">Selections</h3>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0 mall-data-table align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Label</th>
                    <th class="text-end">Odds × 1000</th>
                    <th>Status</th>
                    <th class="text-end text-nowrap">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($market->selections as $sel)
                    <tr>
                        <td class="font-monospace">{{ $sel->id }}</td>
                        <td>{{ $sel->label }}</td>
                        <td class="text-end font-monospace">{{ $sel->current_odds_millis }}</td>
                        <td>
                            @include('admin.partials.sport_status_label', ['kind' => 'selection', 'value' => $sel->status])
                            <span class="text-muted">({{ $sel->status }})</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded" title="Edit"
                                    data-bs-toggle="modal" data-bs-target="#mallModalSelectionEdit"
                                    data-selection-update-url="{{ route('admin.markets.selections.update', [$market, $sel]) }}"
                                    data-selection-label="{{ $sel->label }}"
                                    data-selection-odds="{{ $sel->current_odds_millis }}"
                                    data-selection-status="{{ $sel->status }}">
                                @include('admin.partials.icon_pencil')
                            </button>
                            <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                    title="Delete"
                                    data-mall-delete-url="{{ route('admin.markets.selections.destroy', [$market, $sel]) }}"
                                    data-mall-delete-message="Delete selection #{{ $sel->id }}?">
                                @include('admin.partials.icon_trash')
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">No selections yet. Add one below.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mall-console-card card shadow-sm mb-4">
    <div class="card-body">
        <h4 class="h6 mb-3">Add selection</h4>
        <form method="post" action="{{ route('admin.markets.selections.store', $market) }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label" for="new_sel_label">Label</label>
                <input type="text" name="label" id="new_sel_label" class="form-control" required maxlength="256" value="{{ old('label') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="new_sel_odds">Odds × 1000</label>
                <input type="number" name="current_odds_millis" id="new_sel_odds" class="form-control" required min="1000" value="{{ old('current_odds_millis', 1950) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="new_sel_status">Status</label>
                <select name="status" id="new_sel_status" class="form-select" required>
                    <option value="1" @selected((int) old('status', 1) === 1)>Open</option>
                    <option value="2" @selected((int) old('status', 1) === 2)>Suspended</option>
                    <option value="3" @selected((int) old('status', 1) === 3)>Settled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Add</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="mallModalSelectionEdit" tabindex="-1" aria-labelledby="mallModalSelectionEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="mall-form-selection-update">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h2 class="modal-title h5" id="mallModalSelectionEditLabel">Edit selection</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_sel_label">Label</label>
                        <input type="text" name="label" id="edit_sel_label" class="form-control" required maxlength="256">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_sel_odds">Odds × 1000</label>
                        <input type="number" name="current_odds_millis" id="edit_sel_odds" class="form-control" required min="1000">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="edit_sel_status">Status</label>
                        <select name="status" id="edit_sel_status" class="form-select" required>
                            <option value="1">Open</option>
                            <option value="2">Suspended</option>
                            <option value="3">Settled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('mallModalSelectionEdit');
            var form = document.getElementById('mall-form-selection-update');
            if (!modalEl || !form) return;
            modalEl.addEventListener('show.bs.modal', function (ev) {
                var btn = ev.relatedTarget;
                if (!btn || !btn.getAttribute('data-selection-update-url')) return;
                form.action = btn.getAttribute('data-selection-update-url');
                var label = form.querySelector('#edit_sel_label');
                var odds = form.querySelector('#edit_sel_odds');
                var st = form.querySelector('#edit_sel_status');
                if (label) label.value = btn.getAttribute('data-selection-label') || '';
                if (odds) odds.value = btn.getAttribute('data-selection-odds') || '';
                if (st) st.value = btn.getAttribute('data-selection-status') || '1';
            });
        });
    </script>
@endpush
