/**
 * CMS page editor: section blocks and repeater rows (add, remove, move up
 * and down) and TinyMCE on rich text fields. The form itself is a plain
 * server-rendered form: nothing is posted from here, the only job is to
 * keep the `name` attributes (blocks[3][data][items][1][title]) in order.
 *
 * Markup contract (resources/views/admin/cms):
 *   form[data-cms-editor]                 the editor
 *   [data-cms-blocks] > .cms-block          one block each
 *   template[data-block-template="hero"]   a fresh block, with __INDEX__ / __ID__
 *   .cms-repeater[data-repeater][data-max]  a list of rows inside a block
 *     .cms-repeater-rows > .cms-repeater-row
 *     > template[data-repeater-template]   a fresh row, with __ROW__
 *   [data-cms-action="up|down|remove|add-block|add-row|pick-image|clear-image"]
 *   textarea.cms-html                      rich text (TinyMCE)
 *   .cms-image-field                       an image (assets.id): [data-cms-image-input],
 *                                          [data-cms-image-preview], [data-cms-image-empty]
 *   [data-cms-image-picker]                the picker modal (list, search, upload)
 *   form[data-cms-upload-url]              POST admin/cms/upload -> { id, location, thumbnail }
 *   form[data-cms-assets-url]              GET admin/cms/assets?q= -> { data: [ ... ] }
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
            // A form without blocks (the post editor): nothing to number.
            return;
        }

        children(container, '.cms-block').forEach(function (block, i) {
            block.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/^blocks\[[^\]]*\]/, 'blocks[' + i + ']');
            });

            block.querySelectorAll('.cms-repeater').forEach(function (repeater) {
                var prefix = 'blocks[' + i + '][data][' + repeater.getAttribute('data-repeater') + ']';
                var rows = children(repeater.querySelector('.cms-repeater-rows'), '.cms-repeater-row');

                rows.forEach(function (row, j) {
                    row.querySelectorAll('[name]').forEach(function (field) {
                        if (field.name.indexOf(prefix + '[') === 0) {
                            field.name = prefix + field.name.slice(prefix.length).replace(/^\[[^\]]*\]/, '[' + j + ']');
                        }
                    });
                });

                var max = parseInt(repeater.getAttribute('data-max'), 10);
                var add = children(repeater, '[data-cms-action="add-row"]')[0];
                if (add && max) {
                    add.disabled = rows.length >= max;
                }
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
    }

    var picker = {
        modal: null,
        editor: null,
        field: null,
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
        if (picker.field) {
            setImage(picker.field, asset);
        }
        closePicker();
    }

    function renderImages(assets) {
        var grid = pickerElement('grid');
        grid.innerHTML = '';

        assets.forEach(function (asset) {
            var button = document.createElement('button');
            button.type = 'button';
            button.title = asset.name || '';

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

    function openPicker(editor, field) {
        picker.modal = picker.modal || document.querySelector('[data-cms-image-picker]');
        if (!picker.modal) {
            return;
        }

        picker.editor = editor;
        picker.field = field;
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

        var upload = pickerElement('upload');
        upload.addEventListener('change', function () {
            var file = upload.files && upload.files[0];
            if (!file || !picker.editor) {
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
            height: textarea.rows > 6 ? 400 : 220,
            plugins: 'link image lists table media code',
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

            case 'up':
            case 'down':
                if (item) {
                    move(editor, item, action);
                }
                break;

            case 'pick-image':
                openPicker(editor, button.closest('.cms-image-field'));
                break;

            case 'clear-image':
                setImage(button.closest('.cms-image-field'), null);
                break;

            case 'remove':
                if (item && window.confirm(item.matches('.cms-block') ? 'Deze sectie verwijderen?' : 'Deze rij verwijderen?')) {
                    removeEditors(item);
                    item.parentNode.removeChild(item);
                    reindex(editor);
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

        editor.addEventListener('submit', function () {
            if (window.tinymce) {
                window.tinymce.triggerSave();
            }
        });

        initPicker();
        reindex(editor);
        initEditors(editor, editor);
    }

    function boot() {
        document.querySelectorAll('form[data-cms-editor]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
