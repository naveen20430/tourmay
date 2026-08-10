(function () {
    var editorInstances = [];

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

    function initOneEditor(textarea) {
        var EditorClass = getEditorClass();
        if (!textarea || !EditorClass || textarea.dataset.ckReady === '1') {
            return;
        }

        var wrap = textarea.closest('.tour-description-editor-wrap');
        textarea.removeAttribute('required');
        textarea.dataset.ckReady = '1';

        EditorClass.create(textarea, getEditorConfig())
            .then(function (editor) {
                editorInstances.push(editor);

                var form = textarea.closest('form');
                if (form && !form.dataset.ckSubmitBound) {
                    form.dataset.ckSubmitBound = '1';
                    form.addEventListener('submit', function () {
                        editorInstances.forEach(function (ed) {
                            try { ed.updateSourceElement(); } catch (e) {}
                        });
                    });
                }
            })
            .catch(function (error) {
                console.error('CKEditor failed to initialize:', error);
                textarea.dataset.ckReady = '0';
                showEditorError(wrap);
            });
    }

    function initTourDescriptionEditors() {
        var textareas = document.querySelectorAll('#tour-description-editor, #tour-useful-info-editor, [data-tour-rich-editor]');
        textareas.forEach(function (textarea) {
            initOneEditor(textarea);
        });
    }

    function boot() {
        var attempts = 0;

        function tryInit() {
            attempts += 1;

            if (getEditorClass()) {
                initTourDescriptionEditors();
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
