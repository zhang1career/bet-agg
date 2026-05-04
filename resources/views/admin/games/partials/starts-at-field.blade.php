@php
    $startsAtMs = (int) ($startsAtMs ?? 0);
@endphp
<div class="mb-3">
    <label class="form-label" for="starts_at_picker">Starts at</label>
    <input type="datetime-local" id="starts_at_picker" class="form-control" step="60" autocomplete="off">
    <input type="hidden" name="starts_at" id="starts_at_ms" value="{{ $startsAtMs }}">
    <p class="form-text text-muted small mb-0">Submitted to CMS as Unix timestamp (milliseconds).</p>
</div>
@push('scripts')
<script>
(function () {
    var msEl = document.getElementById('starts_at_ms');
    var pickEl = document.getElementById('starts_at_picker');
    if (!msEl || !pickEl || !msEl.form) {
        return;
    }
    function pad(n) {
        return String(n).padStart(2, '0');
    }
    function msToLocalDatetimeValue(ms) {
        var n = parseInt(ms, 10);
        if (!n || n < 1) {
            return '';
        }
        var d = new Date(n);
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function syncPickerFromMs() {
        pickEl.value = msToLocalDatetimeValue(msEl.value);
    }
    function syncMsFromPicker() {
        var v = pickEl.value;
        if (!v) {
            msEl.value = '0';
            return;
        }
        var t = new Date(v).getTime();
        msEl.value = isNaN(t) ? '0' : String(t);
    }
    pickEl.addEventListener('change', syncMsFromPicker);
    pickEl.addEventListener('input', syncMsFromPicker);
    msEl.form.addEventListener('submit', syncMsFromPicker);
    syncPickerFromMs();
})();
</script>
@endpush
