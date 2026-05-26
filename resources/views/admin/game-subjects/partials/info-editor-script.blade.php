<script src="https://cdn.jsdelivr.net/npm/tinymce@7.8.0/tinymce.min.js" crossorigin="anonymous"></script>
<script>
    (function () {
        'use strict';

        var editorIds = {};

        function syncEditors() {
            if (window.tinymce) {
                window.tinymce.triggerSave();
            }
        }

        function initEditor(textarea) {
            if (!textarea || !textarea.id || editorIds[textarea.id]) {
                return;
            }
            if (!window.tinymce) {
                return;
            }
            editorIds[textarea.id] = true;
            window.tinymce.init({
                target: textarea,
                menubar: false,
                statusbar: false,
                plugins: 'lists link',
                toolbar: 'undo redo | bold italic underline | bullist numlist | link removeformat',
                height: 220,
                license_key: 'gpl',
                promotion: false,
                setup: function (editor) {
                    editor.on('change input undo redo', function () {
                        editor.save();
                    });
                },
            });
        }

        function initEditorsIn(root) {
            root.querySelectorAll('textarea.mall-rich-text').forEach(initEditor);
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.modal[data-mall-modal="1"]').forEach(function (modalEl) {
                modalEl.addEventListener('shown.bs.modal', function () {
                    initEditorsIn(modalEl);
                });
            });
            initEditorsIn(document);

            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('submit', syncEditors);
            });
        });
    })();
</script>
