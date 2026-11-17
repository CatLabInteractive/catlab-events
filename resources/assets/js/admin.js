window.Popper = require('popper.js').default;
window.$ = window.jQuery = require('jquery');

require('bootstrap');

// File fields (admin/crud/asset-field): preview a picked upload before saving.
document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input.matches || !input.matches('[data-asset-file]') || !input.files || !input.files[0]) {
        return;
    }

    var field = input.closest('[data-asset-field]');
    var preview = field.querySelector('[data-asset-preview]');
    var empty = field.querySelector('[data-asset-empty]');

    preview.src = URL.createObjectURL(input.files[0]);
    preview.hidden = false;
    empty.hidden = true;
});
