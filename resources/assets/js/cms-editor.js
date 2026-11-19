/**
 * CMS editor widgets of the admin forms: section blocks and repeater rows
 * (add, remove, move up and down), image fields and TinyMCE on rich text
 * fields. The forms themselves are plain server-rendered forms (the Charon
 * admin forms; see App\Http\Controllers\Admin\Widgets): nothing is posted
 * from here, the only job is to keep the `name` attributes
 * (fields[blocks][3][data][items][1][title]) in order.
 *
 * Markup contract (resources/views/admin/cms):
 *   [data-cms-editor]                      one widget (blocks, an image, rich text)
 *   [data-cms-editor][data-cms-name]       name prefix of the blocks ("fields[blocks]")
 *   [data-cms-blocks] > .cms-block          one block each
 *   template[data-block-template="hero"]   a fresh block, with __INDEX__ / __ID__
 *   .cms-repeater[data-repeater][data-max]  a list of rows inside a block
 *     .cms-repeater-rows > .cms-repeater-row
 *     > template[data-repeater-template]   a fresh row, with __ROW__
 *   [data-cms-action="up|down|remove|add-block|add-row|add-images|pick-image|clear-image|toggle|toggle-all"]
 *                                          add-images: pick (or upload) several images, one new row each
 *   .cms-block-collapsed                   a block folded to its header; [data-cms-block-summary]
 *                                          shows the start of its first text
 *
 * Every change made here (and in TinyMCE) dispatches an "input" event, so
 * the form knows it has unsaved changes.
 *   textarea.cms-html                      rich text (TinyMCE)
 *   .cms-image-field                       an image (assets.id): [data-cms-image-input],
 *                                          [data-cms-image-preview], [data-cms-image-empty]
 *   [data-cms-image-picker]                the picker modal (list, search, upload)
 *   [data-cms-editor][data-cms-upload-url] POST admin/cms/upload -> { id, location, thumbnail }
 *   [data-cms-editor][data-cms-assets-url] GET admin/cms/assets?q= -> { data: [ ... ] }
 */
(function () {
    'use strict';

    var YOUTUBE = /^https?:\/\/(?:(?:www\.|m\.)?youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|(?:www\.)?youtube-nocookie\.com\/embed\/|youtu\.be\/)([A-Za-z0-9_\-]{11})(?:[?&#\/].*)?$/;

    function newId() {
        var bytes = new Uint8Array(3);
        (window.crypto || window.msCrypto).getRandomValues(bytes);
        return Array.prototype.map.call(bytes, function (b) {
            return ('0' + b.toString(16)).slice(-2);
        }).join('');
    }

    function children(element, selector) {
        return Array.prototype.filter.call(element.children, function (child) {
            return child.matches(selector);
        });
    }

    /**
     * Tell the form something changed (laravel-charon-frontend's save bar).
     */
    function changed(element) {
        element.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function textOf(html) {
        return (new DOMParser().parseFromString(html, 'text/html').body.textContent || '').replace(/\s+/g, ' ').trim();
    }

    /**
     * The start of a block's first text, for its folded header line.
     */
    function blockSummary(block) {
        var body = children(block, '.card-body')[0];
        if (!body) {
            return '';
        }

        var fields = body.querySelectorAll('input[type="text"], input:not([type]), input[type="url"], textarea');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var value = field.value || '';
            if (field.matches('textarea.cms-html')) {
                var instance = window.tinymce && field.id ? window.tinymce.get(field.id) : null;
                value = instance ? instance.getContent({ format: 'text' }) : textOf(value);
            }
            value = value.replace(/\s+/g, ' ').trim();
            if (value !== '') {
                return value.length > 90 ? value.slice(0, 90) + '…' : value;
            }
        }

        return body.querySelector('[data-cms-image-input]') && body.querySelector('[data-cms-image-input]').value ? 'Afbeelding' : '';
    }

    function updateSummary(block) {
        var summary = block.querySelector('[data-cms-block-summary]');
        if (summary) {
            summary.textContent = blockSummary(block);
        }
    }

    function setCollapsed(block, collapsed) {
        block.classList.toggle('cms-block-collapsed', collapsed);

        var toggle = block.querySelector('[data-cms-action="toggle"]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }

        if (collapsed) {
            updateSummary(block);
            return;
        }

        // Editors that were hidden size themselves now.
        if (window.tinymce) {
            block.querySelectorAll('textarea.cms-html[data-cms-tinymce]').forEach(function (textarea) {
                var instance = window.tinymce.get(textarea.id);
                if (instance) {
                    instance.execCommand('mceAutoResize');
                }
            });
        }
        if (window.CharonFrontend && window.CharonFrontend.autogrow) {
            window.CharonFrontend.autogrow(block);
        }
    }

    function toggleAll(editor) {
        var blocks = editor.querySelectorAll('[data-cms-blocks] > .cms-block');
        var anyOpen = Array.prototype.some.call(blocks, function (block) {
            return !block.classList.contains('cms-block-collapsed');
        });
        Array.prototype.forEach.call(blocks, function (block) {
            setCollapsed(block, anyOpen);
        });
    }

    function fromTemplate(template, replacements) {
        var html = template.innerHTML;
        Object.keys(replacements).forEach(function (key) {
            html = html.split(key).join(replacements[key]);
        });

        var holder = document.createElement('div');
        holder.innerHTML = html.trim();
        return holder.firstElementChild;
    }

    /**
     * Re-number every name: the block index, then each repeater row index.
     */
    function reindex(editor) {
        var container = editor.querySelector('[data-cms-blocks]');
        if (!container) {
            // A widget without blocks (an image, rich text): nothing to number.
            return;
        }

        var name = editor.getAttribute('data-cms-name') || 'blocks';

        children(container, '.cms-block').forEach(function (block, i) {
            block.querySelectorAll('[name]').forEach(function (field) {
                if (field.name.indexOf(name + '[') === 0) {
                    field.name = name + field.name.slice(name.length).replace(/^\[[^\]]*\]/, '[' + i + ']');
                }
            });

            block.querySelectorAll('.cms-repeater').forEach(function (repeater) {
                var prefix = name + '[' + i + '][data][' + repeater.getAttribute('data-repeater') + ']';
                var rows = children(repeater.querySelector('.cms-repeater-rows'), '.cms-repeater-row');

                rows.forEach(function (row, j) {
                    row.querySelectorAll('[name]').forEach(function (field) {
                        if (field.name.indexOf(prefix + '[') === 0) {
                            field.name = prefix + field.name.slice(prefix.length).replace(/^\[[^\]]*\]/, '[' + j + ']');
                        }
                    });
                });

                var max = parseInt(repeater.getAttribute('data-max'), 10);
                children(repeater, '[data-cms-action="add-row"], [data-cms-action="add-images"]').forEach(function (add) {
                    if (max) {
                        add.disabled = rows.length >= max;
                    }
                });
            });
        });
    }

    // ---------------------------------------------------------------------
    // Uploads
    // ---------------------------------------------------------------------

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function readJson(response) {
        return response.json().catch(function () {
            return {};
        }).then(function (body) {
            if (!response.ok) {
                throw new Error(body.message || ('Er ging iets mis (' + response.status + ').'));
            }
            return body;
        });
    }

    /**
     * Upload an image to the organisation's assets.
     * @return Promise<{ id, location, thumbnail, name }>
     */
    function uploadImage(editor, file, filename) {
        var data = new FormData();
        data.append('file', file, filename || file.name);

        return window.fetch(editor.getAttribute('data-cms-upload-url'), {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
        }).then(readJson);
    }

    function listImages(editor, query) {
        var url = editor.getAttribute('data-cms-assets-url') + (query ? '?q=' + encodeURIComponent(query) : '');

        return window.fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(readJson);
    }

    // ---------------------------------------------------------------------
    // Image fields and the picker
    // ---------------------------------------------------------------------

    function setImage(field, asset) {
        var input = field.querySelector('[data-cms-image-input]');
        var preview = field.querySelector('[data-cms-image-preview]');
        var empty = field.querySelector('[data-cms-image-empty]');
        var clear = field.querySelector('[data-cms-action="clear-image"]');

        input.value = asset ? asset.id : '';
        preview.src = asset ? asset.thumbnail : '';
        preview.classList.toggle('d-none', !asset);
        if (empty) {
            empty.classList.toggle('d-none', !!asset);
        }
        if (clear) {
            clear.classList.toggle('d-none', !asset);
        }

        changed(input);
    }

    var picker = {
        modal: null,
        editor: null,
        field: null,
        // Several mode: the repeater the picked images become rows of.
        repeater: null,
        selected: [],
        room: 0,
        searchTimer: null
    };

    function pickerElement(name) {
        return picker.modal.querySelector('[data-cms-picker-' + name + ']');
    }

    function pickerMessage(error, status) {
        var errorBox = pickerElement('error');
        var statusBox = pickerElement('status');

        errorBox.textContent = error || '';
        errorBox.classList.toggle('d-none', !error);
        statusBox.textContent = status || '';
        statusBox.classList.toggle('d-none', !status);
    }

    function choose(asset) {
        if (picker.repeater) {
            toggleSelected(asset);
            return;
        }
        if (picker.field) {
            setImage(picker.field, asset);
        }
        closePicker();
    }

    function isSelected(asset) {
        return picker.selected.some(function (item) {
            return item.id === asset.id;
        });
    }

    function toggleSelected(asset) {
        if (isSelected(asset)) {
            picker.selected = picker.selected.filter(function (item) {
                return item.id !== asset.id;
            });
        } else if (picker.selected.length < picker.room) {
            picker.selected.push(asset);
        }
        updateSelection();
    }

    function updateSelection() {
        pickerElement('grid').querySelectorAll('button[data-asset-id]').forEach(function (button) {
            var on = picker.selected.some(function (item) {
                return String(item.id) === button.getAttribute('data-asset-id');
            });
            button.classList.toggle('is-selected', on);
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        var count = picker.selected.length;
        pickerElement('count').textContent = (count === 1 ? '1 foto gekozen' : count + ' foto\'s gekozen') +
            (count >= picker.room ? ' (maximum bereikt)' : '');
        var confirm = pickerElement('confirm');
        confirm.disabled = count === 0;
        confirm.textContent = count ? count + ' toevoegen' : 'Toevoegen';
    }

    /**
     * Several mode: one new row per picked image, in the order picked.
     */
    function addPicked() {
        var editor = picker.editor;
        var repeater = picker.repeater;
        picker.selected.forEach(function (asset) {
            var row = addRow(editor, repeater);
            var field = row && row.querySelector('.cms-image-field');
            if (field) {
                setImage(field, asset);
            }
        });
        picker.selected = [];
        closePicker();
    }

    function renderImages(assets) {
        var grid = pickerElement('grid');
        grid.innerHTML = '';

        assets.forEach(function (asset) {
            var button = document.createElement('button');
            button.type = 'button';
            button.title = asset.name || '';
            button.setAttribute('data-asset-id', asset.id);

            var img = document.createElement('img');
            img.src = asset.thumbnail;
            img.alt = '';
            img.loading = 'lazy';

            var name = document.createElement('span');
            name.textContent = asset.name || ('#' + asset.id);

            button.appendChild(img);
            button.appendChild(name);
            button.addEventListener('click', function () {
                choose(asset);
            });

            grid.appendChild(button);
        });

        pickerMessage(null, assets.length ? null : 'Geen afbeeldingen gevonden. Upload er een.');
        if (picker.repeater) {
            updateSelection();
        }
    }

    function loadImages() {
        var query = pickerElement('search').value.trim();
        pickerMessage(null, 'Afbeeldingen laden…');

        listImages(picker.editor, query).then(function (body) {
            renderImages(body.data || []);
        }, function (error) {
            pickerMessage(error.message, null);
        });
    }

    function openPicker(editor, field, repeater) {
        picker.modal = picker.modal || document.querySelector('[data-cms-image-picker]');
        if (!picker.modal) {
            return;
        }

        picker.editor = editor;
        picker.field = field;
        picker.repeater = repeater || null;
        picker.selected = [];
        if (repeater) {
            var max = parseInt(repeater.getAttribute('data-max'), 10) || 100;
            picker.room = Math.max(0, max - children(repeater.querySelector('.cms-repeater-rows'), '.cms-repeater-row').length);
        }

        var several = !!picker.repeater;
        pickerElement('title').textContent = several ? 'Kies foto\'s' : 'Kies een afbeelding';
        pickerElement('upload-label').textContent = several ? 'Foto\'s uploaden' : 'Nieuwe afbeelding uploaden';
        pickerElement('upload').multiple = several;
        pickerElement('footer').classList.toggle('d-none', !several);
        picker.modal.classList.toggle('cms-picker-several', several);
        pickerElement('search').value = '';
        loadImages();

        if (window.jQuery && window.jQuery.fn.modal) {
            window.jQuery(picker.modal).modal('show');
        } else {
            picker.modal.style.display = 'block';
            picker.modal.classList.add('show');
        }
    }

    function closePicker() {
        if (!picker.modal) {
            return;
        }

        if (window.jQuery && window.jQuery.fn.modal) {
            window.jQuery(picker.modal).modal('hide');
        } else {
            picker.modal.style.display = 'none';
            picker.modal.classList.remove('show');
        }
    }

    function initPicker() {
        var modal = document.querySelector('[data-cms-image-picker]');
        if (!modal || modal.getAttribute('data-cms-ready')) {
            return;
        }
        modal.setAttribute('data-cms-ready', '1');
        picker.modal = modal;

        pickerElement('search').addEventListener('input', function () {
            window.clearTimeout(picker.searchTimer);
            picker.searchTimer = window.setTimeout(loadImages, 250);
        });

        pickerElement('confirm').addEventListener('click', addPicked);

        var upload = pickerElement('upload');
        upload.addEventListener('change', function () {
            var file = upload.files && upload.files[0];
            if (!file || !picker.editor) {
                return;
            }

            if (picker.repeater) {
                // Several files: upload them one after the other, tick each.
                var files = Array.prototype.slice.call(upload.files, 0, Math.max(0, picker.room - picker.selected.length));
                var done = 0;
                var failed = [];
                var next = function () {
                    if (done >= files.length) {
                        upload.value = '';
                        loadImages();
                        pickerMessage(failed.length ? failed.join(' ') : null, failed.length ? null : 'Geüpload. Klik op Toevoegen.');
                        return;
                    }
                    pickerMessage(null, 'Uploaden… (' + (done + 1) + ' van ' + files.length + ')');
                    uploadImage(picker.editor, files[done]).then(function (asset) {
                        if (!isSelected(asset)) {
                            picker.selected.push(asset);
                        }
                        updateSelection();
                    }, function (error) {
                        failed.push(files[done].name + ': ' + error.message);
                    }).then(function () {
                        done++;
                        next();
                    });
                };
                next();
                return;
            }

            pickerMessage(null, 'Uploaden…');
            uploadImage(picker.editor, file).then(function (asset) {
                upload.value = '';
                choose(asset);
            }, function (error) {
                upload.value = '';
                pickerMessage(error.message, null);
            });
        });
    }

    // ---------------------------------------------------------------------
    // Rich text
    // ---------------------------------------------------------------------

    function tinymceConfig(editor, textarea) {
        return {
            // base_url and suffix: TinyMCE derives them from its own <script> tag.
            target: textarea,
            menubar: false,
            branding: false,
            promotion: false,
            // The editor grows with its text (like the other text fields of
            // the admin) and keeps its toolbar in view while you scroll.
            min_height: textarea.rows > 6 ? 300 : 180,
            autoresize_bottom_margin: 24,
            toolbar_sticky: true,
            plugins: 'link image lists table media code autoresize',
            toolbar: 'undo redo | blocks | bold italic underline | bullist numlist blockquote | link image media table | code',
            block_formats: 'Paragraaf=p; Kop 2=h2; Kop 3=h3; Kop 4=h4',
            // Client-side comfort only: the server sanitiser (App\Cms\HtmlSanitizer) is the authority.
            valid_elements: '@[class],p,br,h2,h3,h4,strong/b,em/i,u,s,a[href|title|target],ul,ol,li,blockquote,pre,code,hr,' +
                'img[src|alt|width|height|loading],figure,figcaption,table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],' +
                'iframe[src|width|height|allow|allowfullscreen|title],span,div',
            convert_urls: false,
            relative_urls: false,
            link_default_target: '',
            content_css: '/css/app.css',
            // Pasted and inserted images go to the organisation's assets.
            automatic_uploads: true,
            images_file_types: 'jpg,jpeg,png,gif,webp',
            images_upload_handler: function (blobInfo) {
                return uploadImage(editor, blobInfo.blob(), blobInfo.filename()).then(function (asset) {
                    return asset.location;
                }, function (error) {
                    throw { message: error.message, remove: true };
                });
            },
            body_class: 'cms-prose',
            setup: function (instance) {
                // Edits inside the editor's iframe count as changes of the form.
                instance.on('input change undo redo', function () {
                    instance.save();
                    changed(textarea);
                });
            },
            media_alt_source: false,
            media_poster: false,
            media_url_resolver: function (data) {
                var match = YOUTUBE.exec(data.url || '');
                if (!match) {
                    return Promise.reject({ msg: 'Alleen YouTube-video\'s kunnen ingevoegd worden.' });
                }
                return Promise.resolve({
                    html: '<iframe src="https://www.youtube-nocookie.com/embed/' + match[1] + '" width="560" height="315" ' +
                        'title="YouTube" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen="allowfullscreen"></iframe>'
                });
            }
        };
    }

    function initEditors(editor, scope) {
        if (!window.tinymce) {
            return;
        }

        scope.querySelectorAll('textarea.cms-html').forEach(function (textarea) {
            if (textarea.getAttribute('data-cms-tinymce')) {
                return;
            }
            if (!textarea.id) {
                textarea.id = 'cms-html-' + newId() + newId();
            }
            textarea.setAttribute('data-cms-tinymce', '1');
            window.tinymce.init(tinymceConfig(editor, textarea));
        });
    }

    /**
     * TinyMCE lives in an iframe that does not survive being moved in the
     * DOM: write its content back and tear it down first.
     */
    function removeEditors(scope) {
        if (!window.tinymce) {
            return;
        }

        scope.querySelectorAll('textarea.cms-html[data-cms-tinymce]').forEach(function (textarea) {
            var instance = window.tinymce.get(textarea.id);
            if (instance) {
                instance.save();
                instance.remove();
            }
            textarea.removeAttribute('data-cms-tinymce');
        });
    }

    // ---------------------------------------------------------------------
    // Actions
    // ---------------------------------------------------------------------

    function move(editor, item, direction) {
        var sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling || !sibling.matches('.cms-block, .cms-repeater-row')) {
            return;
        }

        removeEditors(item);
        removeEditors(sibling);

        if (direction === 'up') {
            item.parentNode.insertBefore(item, sibling);
        } else {
            item.parentNode.insertBefore(sibling, item);
        }

        reindex(editor);
        initEditors(editor, item);
        initEditors(editor, sibling);
        changed(item);
    }

    function addBlock(editor) {
        var select = editor.querySelector('[data-cms-block-select]');
        var template = editor.querySelector('template[data-block-template="' + select.value + '"]');
        if (!template) {
            return;
        }

        var block = fromTemplate(template, { '__ID__': newId() });
        editor.querySelector('[data-cms-blocks]').appendChild(block);

        reindex(editor);
        initEditors(editor, block);
        changed(block);
        block.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function addRow(editor, repeater) {
        var template = children(repeater, 'template[data-repeater-template]')[0];
        var rows = repeater.querySelector('.cms-repeater-rows');
        var max = parseInt(repeater.getAttribute('data-max'), 10);

        if (!template || (max && children(rows, '.cms-repeater-row').length >= max)) {
            return;
        }

        var row = fromTemplate(template, {});
        rows.appendChild(row);

        reindex(editor);
        initEditors(editor, row);
        changed(row);

        return row;
    }

    function onClick(editor, event) {
        var button = event.target.closest('[data-cms-action]');
        if (!button || !editor.contains(button)) {
            return;
        }

        var action = button.getAttribute('data-cms-action');
        var item = button.closest('.cms-repeater-row, .cms-block');

        switch (action) {
            case 'add-block':
                addBlock(editor);
                break;

            case 'add-row':
                addRow(editor, button.closest('.cms-repeater'));
                break;

            case 'toggle':
                if (item && item.matches('.cms-block')) {
                    setCollapsed(item, !item.classList.contains('cms-block-collapsed'));
                }
                break;

            case 'toggle-all':
                toggleAll(editor);
                break;

            case 'up':
            case 'down':
                if (item) {
                    move(editor, item, action);
                }
                break;

            case 'pick-image':
                openPicker(editor, button.closest('.cms-image-field'));
                break;

            case 'add-images':
                openPicker(editor, null, button.closest('.cms-repeater'));
                break;

            case 'clear-image':
                setImage(button.closest('.cms-image-field'), null);
                break;

            case 'remove':
                if (item && window.confirm(item.matches('.cms-block') ? 'Deze sectie verwijderen?' : 'Deze rij verwijderen?')) {
                    var parent = item.parentNode;
                    removeEditors(item);
                    parent.removeChild(item);
                    reindex(editor);
                    changed(parent);
                }
                break;

            default:
                return;
        }

        event.preventDefault();
    }

    function init(editor) {
        editor.addEventListener('click', function (event) {
            onClick(editor, event);
        });

        var form = editor.closest('form');
        if (form && !form.getAttribute('data-cms-save')) {
            form.setAttribute('data-cms-save', '1');
            form.addEventListener('submit', function () {
                if (window.tinymce) {
                    window.tinymce.triggerSave();
                }
            });
        }

        // The folded header line follows what is typed.
        editor.addEventListener('input', function (event) {
            var block = event.target.closest ? event.target.closest('.cms-block') : null;
            if (block && editor.contains(block)) {
                updateSummary(block);
            }
        });

        initPicker();
        reindex(editor);
        initEditors(editor, editor);
        editor.querySelectorAll('[data-cms-blocks] > .cms-block').forEach(updateSummary);
    }

    function boot() {
        document.querySelectorAll('[data-cms-editor]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
