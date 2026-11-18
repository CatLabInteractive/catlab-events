window.Popper = require('popper.js').default;
window.$ = window.jQuery = require('jquery');

require('bootstrap');

// Rich text editor for Charon html() fields (descriptions, teasers, ...).
// The form library marks those textareas with .html-richtext-input.
require('summernote/dist/summernote-bs4');

$(function () {
    var $editors = $('textarea.html-richtext-input');

    $editors.summernote({
        minHeight: 200,
        disableDragAndDrop: true,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link', 'hr']],
            ['view', ['codeview']]
        ]
    });

    // Edits made in code view only reach the textarea when leaving it.
    $editors.closest('form').on('submit', function () {
        $(this).find('textarea.html-richtext-input').each(function () {
            if ($(this).summernote('codeview.isActivated')) {
                $(this).summernote('codeview.deactivate');
            }
        });
    });
});
