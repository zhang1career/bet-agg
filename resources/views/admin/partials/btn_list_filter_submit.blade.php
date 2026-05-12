{{-- GET list filter form: vector SVG (icon_search), same control chrome as mall-icon-btn row actions—not Unicode/emoji. --}}
<button type="submit"
        class="mall-icon-btn d-inline-flex align-items-center justify-content-center p-1 rounded flex-shrink-0{{ ($extraClass ?? '') !== '' ? ' ' . trim((string) $extraClass) : '' }}"
        title="{{ __('console.btn.list_filter_apply') }}"
        aria-label="{{ __('console.btn.list_filter_apply') }}">
    @include('admin.partials.icon_search')
</button>
