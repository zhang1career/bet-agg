{{--
    Detail page top back link (aligned with service_foundation app_console detail_back_link.html).
    Requires window.returnToList from bet-admin.js.
--}}
@php
    $backLabel = $backLabel ?? '返回';
@endphp
<div class="mb-3">
    <a href="{{ $backUrl }}"
       onclick="return returnToList(@js($backUrl))"
       class="d-inline-flex align-items-center text-primary text-decoration-none mall-detail-back-link">
        <svg class="flex-shrink-0 me-1" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        {{ $backLabel }}
    </a>
</div>
