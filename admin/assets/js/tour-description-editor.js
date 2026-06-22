(function () {
    var editorInstance = null;

    var premiumPluginsToRemove = [
        'AIAssistant',
        'CKBox',
        'CKFinder',
        'EasyImage',
        'MultiLevelList',
        'RealTimeCollaborativeComments',
        'RealTimeCollaborativeTrackChanges',
        'RealTimeCollaborativeRevisionHistory',
        'PresenceList',
        'Comments',
        'TrackChanges',
        'TrackChangesData',
        'RevisionHistory',
        'Pagination',
        'WProofreader',
        'MathType',
        'SlashCommand',
        'Template',
        'DocumentOutline',
        'FormatPainter',
        'TableOfContents',
        'PasteFromOfficeEnhanced',
        'CaseChange',
        'ExportPdf',
        'ExportWord',
        'ImportWord',
        'MergeFields'
    ];

    function getEditorClass() {
        if (window.CKEDITOR && window.CKEDITOR.ClassicEditor) {
            return window.CKEDITOR.ClassicEditor;
        }
        if (window.ClassicEditor) {
            return window.ClassicEditor;
        }
        return null;
    }

    function getEditorConfig() {
        var useSuperBuild = !!(window.CKEDITOR && window.CKEDITOR.ClassicEditor);
        var config = {
            toolbar: {
                items: useSuperBuild
                    ? [
                        'undo', 'redo', '|',
                        'heading', '|',
                        'fontSize', '|',
                        'bold', 'italic', 'underline', '|',
                        'bulletedList', 'numberedList', '|',
                        'link', '|',
                        'removeFormat'
                    ]
                    : [
                        'undo', 'redo', '|',
                        'heading', '|',
                        'bold', 'italic', '|',
                        'link', '|',
                        'bulletedList', 'numberedList'
                    ],
                shouldNotGroupWhenFull: true
            },
            heading: {
                options: [
                    { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                    { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                    { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
                ]
            }
        };

        if (useSuperBuild) {
            config.fontSize = {
                options: [10, 12, 14, 'default', 18, 20, 24]
            };
            config.removePlugins = premiumPluginsToRemove;
        }

        return config;
    }

    function showEditorError(wrap) {
        if (!wrap || wrap.querySelector('.tour-description-editor-error')) {
            return;
        }

        var notice = document.createElement('div');
        notice.className = 'alert alert-warning mt-2 mb-0 tour-description-editor-error';
        notice.textContent = 'Rich text editor could not load. Please refresh the page or check your internet connection.';
        wrap.appendChild(notice);
    }

    function initTourDescriptionEditor() {
        var textarea = document.getElementById('tour-description-editor');
        var EditorClass = getEditorClass();

        if (!textarea || !EditorClass || editorInstance) {
            return;
        }

        var wrap = textarea.closest('.tour-description-editor-wrap');
        textarea.removeAttribute('required');

        EditorClass.create(textarea, getEditorConfig())
            .then(function (editor) {
                editorInstance = editor;

                var form = textarea.closest('form');
                if (form) {
                    form.addEventListener('submit', function () {
                        editor.updateSourceElement();
                    });
                }
            })
            .catch(function (error) {
                console.error('CKEditor failed to initialize:', error);
                showEditorError(wrap);
            });
    }

    function boot() {
        var attempts = 0;

        function tryInit() {
            attempts += 1;

            if (getEditorClass()) {
                initTourDescriptionEditor();
                return;
            }

            if (attempts < 50) {
                window.setTimeout(tryInit, 100);
            }
        }

        tryInit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
