@php
    $idSuf = $idSuf ?? '';
    $groupIdsForOld = old('group_ids', $selectedGroupIds ?? []);
    if (! is_array($groupIdsForOld)) {
        $groupIdsForOld = [];
    }
    $groupIdsForOld = array_map('intval', $groupIdsForOld);
    $sideA = old('side_a_subject_id', $selectedSideA ?? '');
    $sideB = old('side_b_subject_id', $selectedSideB ?? '');
@endphp
<div class="mb-3">
    <label class="form-label" for="game_group_ids{{ $idSuf }}">关联赛事分组（biz_x，多选）</label>
    <select name="group_ids[]" id="game_group_ids{{ $idSuf }}" class="form-select @error('group_ids') is-invalid @enderror" multiple required size="5">
        @foreach($allGroups as $g)
            <option value="{{ $g->id }}" @selected(in_array((int) $g->id, $groupIdsForOld, true))>
                <code>{{ $g->code }}</code> · #{{ $g->id }}
            </option>
        @endforeach
    </select>
    @error('group_ids')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <p class="text-muted small mt-1">勾选后，下方 A/B 方下拉仅显示这些分组在「赛事主体 ↔ 分组」<code>biz_y</code> 中的主体。分组与赛事的关联仅在此表单维护。</p>
</div>

<div class="mb-3">
    <label class="form-label" for="side_a_subject_id{{ $idSuf }}">Side A（主场侧，biz_game.side_a_subject_id）</label>
    <select name="side_a_subject_id" id="side_a_subject_id{{ $idSuf }}" class="form-select @error('side_a_subject_id') is-invalid @enderror">
        <option value="">— 未选 —</option>
        @foreach($allSubjects as $subj)
            @php
                $gidList = $subj->groups->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                $attr = implode(',', $gidList);
            @endphp
            <option value="{{ $subj->id }}"
                    data-group-ids="{{ $attr }}"
                    @selected((string) $sideA !== '' && (int) $sideA === (int) $subj->id)>
                {{ $subj->name }}
            </option>
        @endforeach
    </select>
    @error('side_a_subject_id')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="side_b_subject_id{{ $idSuf }}">Side B（客场侧，biz_game.side_b_subject_id）</label>
    <select name="side_b_subject_id" id="side_b_subject_id{{ $idSuf }}" class="form-select @error('side_b_subject_id') is-invalid @enderror">
        <option value="">— 未选 —</option>
        @foreach($allSubjects as $subj)
            @php
                $gidList = $subj->groups->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                $attr = implode(',', $gidList);
            @endphp
            <option value="{{ $subj->id }}"
                    data-group-ids="{{ $attr }}"
                    @selected((string) $sideB !== '' && (int) $sideB === (int) $subj->id)>
                {{ $subj->name }}
            </option>
        @endforeach
    </select>
    @error('side_b_subject_id')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var grp = document.getElementById(@json('game_group_ids'.$idSuf));
            var a = document.getElementById(@json('side_a_subject_id'.$idSuf));
            var b = document.getElementById(@json('side_b_subject_id'.$idSuf));
            if (!grp || !a || !b) return;

            function selectedGroupIdSet() {
                var out = {};
                Array.prototype.forEach.call(grp.selectedOptions, function (opt) {
                    out[String(opt.value)] = true;
                });
                return out;
            }

            function filterSelect(sel) {
                var g = selectedGroupIdSet();
                var any = Object.keys(g).length > 0;
                Array.prototype.forEach.call(sel.querySelectorAll('option'), function (opt) {
                    if (!opt.value) {
                        opt.hidden = false;
                        return;
                    }
                    var raw = opt.getAttribute('data-group-ids') || '';
                    var ids = raw.split(',').map(function (x) { return x.trim(); }).filter(Boolean);
                    if (!any) {
                        opt.hidden = true;
                        return;
                    }
                    var ok = ids.some(function (id) { return g[id]; });
                    opt.hidden = !ok;
                });
                if (sel.selectedOptions.length && sel.selectedOptions[0].hidden) {
                    sel.value = '';
                }
            }

            function refresh() {
                filterSelect(a);
                filterSelect(b);
            }

            grp.addEventListener('change', refresh);
            refresh();
        });
    </script>
@endpush
