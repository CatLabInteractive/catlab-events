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
 *   [data-cms-action="up|down|remove|add-block|add-row"]
 *   textarea.cms-html                      rich text (TinyMCE)
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
