<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('console.layout.default_title')) — {{ __('console.layout.title_suffix') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: {preflight: false},
            theme: {
                extend: {
                    colors: {
                        sidebar: '#1f2937',
                    },
                },
            },
        };
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/mall-admin.css') }}">
</head>
<body class="mall-admin min-h-screen text-sm">
<div class="flex h-screen overflow-hidden">
    <aside id="console-sidebar"
           class="relative bg-sidebar text-white flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out"
           style="width: var(--sidebar-width, 256px);">
        <div class="h-16 flex items-center justify-center border-b border-gray-700 shrink-0 overflow-hidden">
            <span id="sidebar-logo-text" class="text-xl font-bold whitespace-nowrap">{{ __('console.layout.brand') }}</span>
            <span id="sidebar-logo-icon" class="hidden text-xl font-bold">BA</span>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 overflow-x-hidden">
            <ul class="space-y-1 px-3">
                <li>
                    <a href="{{ route('admin.game-groups.index') }}"
                       class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition-colors sidebar-link {{ request()->routeIs('admin.game-groups.*') ? 'bg-gray-700' : '' }}"
                       title="{{ __('console.sidebar.game_groups') }}">
                        <svg class="w-5 h-5 flex-shrink-0 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <span class="sidebar-text ml-3 whitespace-nowrap">{{ __('console.sidebar.game_groups') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.game-subjects.index') }}"
                       class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition-colors sidebar-link {{ request()->routeIs('admin.game-subjects.*') ? 'bg-gray-700' : '' }}"
                       title="{{ __('console.sidebar.game_subjects') }}">
                        <svg class="w-5 h-5 flex-shrink-0 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="sidebar-text ml-3 whitespace-nowrap">{{ __('console.sidebar.game_subjects') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.games.index') }}"
                       class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition-colors sidebar-link {{ request()->routeIs('admin.games.*') ? 'bg-gray-700' : '' }}"
                       title="{{ __('console.sidebar.games') }}">
                        <svg class="w-5 h-5 flex-shrink-0 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="sidebar-text ml-3 whitespace-nowrap">{{ __('console.sidebar.games') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.markets.index') }}"
                       class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition-colors sidebar-link {{ request()->routeIs('admin.markets.*') ? 'bg-gray-700' : '' }}"
                       title="{{ __('console.sidebar.markets') }}">
                        <svg class="w-5 h-5 flex-shrink-0 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <span class="sidebar-text ml-3 whitespace-nowrap">{{ __('console.sidebar.markets') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.orders.index') }}"
                       class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition-colors sidebar-link {{ request()->routeIs('admin.orders.*') ? 'bg-gray-700' : '' }}"
                       title="{{ __('console.sidebar.orders') }}">
                        <svg class="w-5 h-5 flex-shrink-0 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span class="sidebar-text ml-3 whitespace-nowrap">{{ __('console.sidebar.orders') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.points.index') }}"
                       class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition-colors sidebar-link {{ request()->routeIs('admin.points.*') ? 'bg-gray-700' : '' }}"
                       title="{{ __('console.sidebar.points') }}">
                        <svg class="w-5 h-5 flex-shrink-0 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="sidebar-text ml-3 whitespace-nowrap">{{ __('console.sidebar.points') }}</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div id="sidebar-footer"
             class="p-4 border-t border-gray-700 text-sm text-gray-400 shrink-0 overflow-hidden flex items-center justify-center">
            <p class="sidebar-text whitespace-nowrap">{{ __('console.layout.footer') }}</p>
            <p id="sidebar-footer-short" class="hidden text-xs">BA</p>
        </div>

        <div id="sidebar-handle" class="absolute top-0 right-0 w-1 h-full cursor-col-resize hover:bg-blue-500/50 transition-colors group"
             title="{{ __('console.sidebar.drag_resize') }}"></div>
        <button id="sidebar-toggle" type="button"
                class="absolute -right-3 top-8 w-6 h-6 rounded-full bg-sidebar border-2 border-gray-500 shadow flex items-center justify-center text-gray-300 hover:bg-gray-600 hover:text-white transition-all z-20"
                title="{{ __('console.sidebar.collapse') }}" aria-label="{{ __('console.aria.toggle_sidebar') }}">
            <svg id="sidebar-toggle-icon-expand" class="w-3 h-3 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <svg id="sidebar-toggle-icon-collapse" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
    </aside>

    <main class="mall-admin-main flex-1 overflow-y-auto">
        <header class="theme-header h-16 bg-white shadow-sm flex items-center justify-between px-6 border-b border-transparent">
            <h1 class="theme-title text-xl font-semibold text-gray-800">@yield('title', __('console.layout.default_title'))</h1>
            <div class="d-flex align-items-center gap-3 flex-shrink-0">
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('console.locale.label') }}">
                    <a href="{{ route('admin.locale.switch', ['locale' => 'en']) }}"
                       class="btn {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('console.locale.en') }}</a>
                    <a href="{{ route('admin.locale.switch', ['locale' => 'zh_CN']) }}"
                       class="btn {{ app()->getLocale() === 'zh_CN' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('console.locale.zh') }}</a>
                </div>
                <label class="flex items-center gap-2 cursor-pointer mb-0" for="theme-toggle" title="{{ __('console.aria.toggle_theme') }}">
                    <svg class="theme-toggle-icon w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 18a6 6 0 006-6c0-3.314-2.682-6-6-6S6 8.686 6 12a6 6 0 006 6zM12 2v2M6 4.5l1.5 1.5M18 4.5L16.5 6M4.5 12H2M22 12h-2.5M18 18l1.5-1.5M6 18L4.5 16.5"/>
                    </svg>
                    <span class="console-toggle-wrap">
                        <input type="checkbox" id="theme-toggle" class="console-toggle-input" checked>
                        <span class="console-toggle-track" aria-hidden="true"></span>
                    </span>
                </label>
            </div>
        </header>

        <div class="p-4">
            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if(isset($errors) && $errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </div>
    </main>
</div>

@stack('modals')

{{-- Global confirm delete (form action set via data-mall-delete-url on open button) --}}
<div class="modal fade" id="mallModalDelete" tabindex="-1" aria-labelledby="mallModalDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="mallModalDeleteLabel">{{ __('console.delete_modal.title') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('console.aria.close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="mall-delete-message">{{ __('console.delete_modal.body_default') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('console.btn.cancel') }}</button>
                <form id="mall-form-delete" method="post" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">{{ __('console.delete_modal.confirm') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
@php
    $__mallConsoleI18n = [
        'deleteDefault' => __('console.delete_modal.body_default'),
        'sidebarExpand' => __('console.sidebar.expand'),
        'sidebarCollapse' => __('console.sidebar.collapse'),
        'loading' => __('console.js.loading'),
        'invalidResponse' => __('console.js.invalid_response'),
        'requestFailed' => __('console.js.request_failed'),
    ];
@endphp
<script>
    window.MALL_CONSOLE_I18N = @json($__mallConsoleI18n);
</script>
<script src="{{ asset('js/bet-admin.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var delForm = document.getElementById('mall-form-delete');
        var delModalEl = document.getElementById('mallModalDelete');
        if (!delForm || !delModalEl) {
            return;
        }
        var delMsg = document.getElementById('mall-delete-message');
        var delModal = bootstrap.Modal.getOrCreateInstance(delModalEl);
        var delFallback = (window.MALL_CONSOLE_I18N && window.MALL_CONSOLE_I18N.deleteDefault) || '';
        document.querySelectorAll('[data-mall-delete-url]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                delForm.action = btn.getAttribute('data-mall-delete-url');
                if (delMsg) {
                    delMsg.textContent = btn.getAttribute('data-mall-delete-message') || delFallback;
                }
                delModal.show();
            });
        });
    });
</script>
@stack('scripts')
</body>
</html>
