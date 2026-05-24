@php
    $mediaIdPfx = $mediaIdPfx ?? 'gsi';
    $icon_path = $icon_path ?? '';
@endphp

<div class="subject-icon-upload">
    <div class="mb-3">
        <label class="form-label" for="{{ $mediaIdPfx }}-icon_path">{{ __('console.game_subjects.label_icon') }}</label>
        <div class="d-flex flex-column gap-2">
            <input type="text" name="icon_path" id="{{ $mediaIdPfx }}-icon_path" class="form-control font-monospace small"
                   value="{{ $icon_path }}" placeholder="{{ __('console.game_subjects.icon_path_placeholder') }}">
            <input type="file" class="form-control" id="{{ $mediaIdPfx }}-icon_path-file" accept="image/*">
        </div>
        <div class="form-text text-muted">{{ __('console.game_subjects.icon_path_help') }}</div>
        <div class="text-danger small mt-1 d-none" id="{{ $mediaIdPfx }}-icon_path-err" role="alert"></div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            'use strict';

            var uploadUrl = @json(route('admin.uploads.store'));

            function csrfFromMeta() {
                var m = document.querySelector('meta[name="csrf-token"]');
                return m ? (m.getAttribute('content') || '') : '';
            }

            function showErr(id, msg) {
                var el = document.getElementById(id);
                if (!el) {
                    return;
                }
                el.textContent = msg || '';
                el.classList.toggle('d-none', !msg);
            }

            function uploadOne(file, uploadKind) {
                var csrf = csrfFromMeta();
                if (!csrf) {
                    return Promise.reject(new Error('Missing CSRF token; refresh the page and try again.'));
                }

                var fd = new FormData();
                fd.append('_token', csrf);
                fd.append('file', file);
                fd.append('upload_kind', uploadKind);
                return fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: fd,
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.json().then(function (j) {
                        if (!r.ok) {
                            var msg = j.message || '';
                            if (j.errors && j.errors.file && j.errors.file[0]) {
                                msg = j.errors.file[0];
                            }
                            throw new Error(msg || 'Upload failed');
                        }
                        var path = j.path || (j.data && j.data.path) || '';
                        if (!path) {
                            throw new Error('Invalid upload response');
                        }
                        return path;
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                var pfx = @json($mediaIdPfx);
                var iconInput = document.getElementById(pfx + '-icon_path');
                var iconFile = document.getElementById(pfx + '-icon_path-file');
                if (iconFile && iconInput) {
                    iconFile.addEventListener('change', function () {
                        var f = iconFile.files && iconFile.files[0];
                        showErr(pfx + '-icon_path-err', '');
                        if (!f) {
                            return;
                        }
                        uploadOne(f, 'subj_icon').then(function (path) {
                            iconInput.value = path;
                            iconFile.value = '';
                        }).catch(function (e) {
                            showErr(pfx + '-icon_path-err', e.message || 'Upload failed');
                            iconFile.value = '';
                        });
                    });
                }
            });
        })();
    </script>
@endpush
