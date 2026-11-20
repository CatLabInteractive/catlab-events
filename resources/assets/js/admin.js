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

// CMS page editor (section blocks, repeaters, rich text).
require('./cms-editor');

// Asset fields in the admin forms (resources/views/admin/widgets/asset-file.blade.php):
// the picked or uploaded image shows in the preview, the search filters the grid.
(function () {
    function fieldOf(element) {
        return element.closest('[data-asset-file]');
    }

    function showPreview(field, url) {
        var img = field.querySelector('[data-charon-file-preview]');
        img.src = url || '';
        img.hidden = !url;
        field.querySelector('[data-charon-file-empty]').hidden = !!url;
    }

    function markChecked(field) {
        field.querySelectorAll('.asset-file-tile').forEach(function (tile) {
            tile.classList.toggle('is-checked', tile.querySelector('input').checked);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-asset-file]').forEach(markChecked);
    });

    document.addEventListener('change', function (event) {
        var target = event.target;
        var field = fieldOf(target);
        if (!field) {
            return;
        }

        if (target.matches('[data-asset-file-upload]')) {
            if (target.files && target.files[0]) {
                showPreview(field, URL.createObjectURL(target.files[0]));
            }
        } else if (target.matches('.asset-file-tile input')) {
            markChecked(field);
            // An upload wins over a picked asset, so the preview keeps it.
            var upload = field.querySelector('[data-asset-file-upload]');
            if (!upload || !upload.files || !upload.files.length) {
                showPreview(field, target.getAttribute('data-preview'));
            }
        }
    });

    document.addEventListener('input', function (event) {
        if (!event.target.matches('[data-asset-file-search]')) {
            return;
        }
        var field = fieldOf(event.target);
        var query = event.target.value.trim().toLowerCase();
        var shown = 0;
        field.querySelectorAll('[data-asset-file-name]').forEach(function (tile) {
            var match = query === '' || tile.getAttribute('data-asset-file-name').indexOf(query) !== -1;
            tile.hidden = !match;
            shown += match ? 1 : 0;
        });
        field.querySelector('[data-asset-file-nothing]').hidden = shown > 0;
    });
})();
