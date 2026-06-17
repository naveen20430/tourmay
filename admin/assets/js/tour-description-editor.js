(function () {
    function initTourDescriptionEditor() {
        var textarea = document.getElementById('tour-description-editor');
        if (!textarea || typeof window.tinymce === 'undefined') {
            return;
        }

        if (window.tinymce.get('tour-description-editor')) {
            return;
        }

        window.tinymce.init({
            selector: '#tour-description-editor',
            license_key: 'gpl',
            height: 360,
            menubar: false,
            branding: false,
            promotion: false,
            statusbar: false,
            plugins: 'lists link autoresize',
            toolbar: 'undo redo | bold italic underline | fontsize | bullist numlist | removeformat',
            font_size_formats: '12px 14px 16px 18px 20px 24px 28px',
            content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #0f172a; }',
            setup: function (editor) {
                var form = textarea.closest('form');
                if (!form) {
                    return;
                }

                form.addEventListener('submit', function () {
                    editor.save();
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTourDescriptionEditor);
    } else {
        initTourDescriptionEditor();
    }
})();
