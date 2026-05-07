@php
    $mediaIdPfx = $mediaIdPfx ?? 'gg';
    $banner_path = $banner_path ?? '';
    $main_image_path = $main_image_path ?? '';
@endphp

<div class="game-media-upload">
    <div class="mb-3">
        <label class="form-label" for="{{ $mediaIdPfx }}-banner_path">Banner</label>
        <div class="d-flex flex-column gap-2">
            <input type="text" name="banner_path" id="{{ $mediaIdPfx }}-banner_path" class="form-control font-monospace small"
                   value="{{ $banner_path }}" placeholder="OSS object key (upload or paste)">
            <input type="file" class="form-control" id="{{ $mediaIdPfx }}-banner_path-file" accept="image/*">
        </div>
        <div class="form-text text-muted">Single file; form submits the OSS path (object key), not a public URL.</div>
        <div class="text-danger small mt-1 d-none" id="{{ $mediaIdPfx }}-banner_path-err" role="alert"></div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="{{ $mediaIdPfx }}-main_image_path">Main media</label>
        <div class="d-flex flex-column gap-2">
            <input type="text" name="main_image_path" id="{{ $mediaIdPfx }}-main_image_path" class="form-control font-monospace small"
                   value="{{ $main_image_path }}" placeholder="OSS object key (upload or paste)">
            <input type="file" class="form-control" id="{{ $mediaIdPfx }}-main_image_path-file" accept="image/*">
        </div>
        <div class="form-text text-muted">Single file; form submits the OSS path (object key), not a public URL.</div>
        <div class="text-danger small mt-1 d-none" id="{{ $mediaIdPfx }}-main_image_path-err" role="alert"></div>
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
                        if (!j.path) {
                            throw new Error('Invalid upload response');
                        }
                        return j.path;
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                var pfx = @json($mediaIdPfx);
                var bannerInput = document.getElementById(pfx + '-banner_path');
                var bannerFile = document.getElementById(pfx + '-banner_path-file');
                if (bannerFile && bannerInput) {
                    bannerFile.addEventListener('change', function () {
                        var f = bannerFile.files && bannerFile.files[0];
                        showErr(pfx + '-banner_path-err', '');
                        if (!f) {
                            return;
                        }
                        uploadOne(f, 'banner').then(function (path) {
                            bannerInput.value = path;
                            bannerFile.value = '';
                        }).catch(function (e) {
                            showErr(pfx + '-banner_path-err', e.message || 'Upload failed');
                            bannerFile.value = '';
                        });
                    });
                }

                var mainInput = document.getElementById(pfx + '-main_image_path');
                var mainFile = document.getElementById(pfx + '-main_image_path-file');
                if (mainFile && mainInput) {
                    mainFile.addEventListener('change', function () {
                        var f = mainFile.files && mainFile.files[0];
                        showErr(pfx + '-main_image_path-err', '');
                        if (!f) {
                            return;
                        }
                        uploadOne(f, 'main_media').then(function (path) {
                            mainInput.value = path;
                            mainFile.value = '';
                        }).catch(function (e) {
                            showErr(pfx + '-main_image_path-err', e.message || 'Upload failed');
                            mainFile.value = '';
                        });
                    });
                }
            });
        })();
    </script>
@endpush
