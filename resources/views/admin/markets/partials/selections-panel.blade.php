@php
    /** @var \App\Models\Market $market */
    $mallOpenSelectionCreateModal = $errors->any()
        && ($errors->has('label') || $errors->has('current_odds_millis') || $errors->has('selection_status'));
@endphp

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h3 class="h5 mb-0">Selections</h3>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mallModalSelectionCreate">
        新建选项
    </button>
</div>

<div class="mall-console-card card shadow-sm mb-4">
    <div class="card-body">
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
                            @include('admin.partials.status_label', ['kind' => 'selection', 'value' => $sel->status])
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
                        <td colspan="5" class="text-center text-muted py-3">No selections yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="mallModalSelectionCreate" tabindex="-1" aria-labelledby="mallModalSelectionCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('admin.markets.selections.store', $market) }}" id="mall-form-selection-create">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="mallModalSelectionCreateLabel">新建选项</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="create_sel_label">Label</label>
                        <input type="text" name="label" id="create_sel_label" class="form-control @error('label') is-invalid @enderror" required maxlength="256" value="{{ old('label') }}">
                        @error('label')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="create_sel_odds">Odds × 1000</label>
                        <input type="number" name="current_odds_millis" id="create_sel_odds" class="form-control @error('current_odds_millis') is-invalid @enderror" required min="1000" value="{{ old('current_odds_millis', 1950) }}">
                        @error('current_odds_millis')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="create_sel_status">Status</label>
                        <select name="selection_status" id="create_sel_status" class="form-select @error('selection_status') is-invalid @enderror" required
                                data-mall-dict-options="market_status"
                                data-mall-dict-selected="{{ (int) old('selection_status', 1) }}"></select>
                        @error('selection_status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
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
                        <select name="status" id="edit_sel_status" class="form-select" required
                                data-mall-dict-options="market_status"></select>
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
            var createModalEl = document.getElementById('mallModalSelectionCreate');
            var createForm = document.getElementById('mall-form-selection-create');
            if (createModalEl && createForm) {
                createModalEl.addEventListener('show.bs.modal', function (ev) {
                    if (!ev.relatedTarget) {
                        return;
                    }
                    var labelInp = createForm.querySelector('#create_sel_label');
                    if (labelInp) {
                        labelInp.value = '';
                    }
                    var odds = createForm.querySelector('#create_sel_odds');
                    if (odds) {
                        odds.value = '1950';
                    }
                    var st = createForm.querySelector('#create_sel_status');
                    if (st) {
                        st.value = '1';
                    }
                });
            }

            @if($mallOpenSelectionCreateModal)
                if (createModalEl) {
                    bootstrap.Modal.getOrCreateInstance(createModalEl).show();
                }
            @endif

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
