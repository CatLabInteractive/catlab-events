/*
 * Kleurveld: the scripts of the public site, without dependencies.
 * Loaded with defer on every page (layouts/blocks/head); each part only runs
 * when its markup is on the page.
 */
(function () {
    'use strict';

    var each = function (selector, fn, root) {
        Array.prototype.forEach.call((root || document).querySelectorAll(selector), fn);
    };

    /* ---------- Header menu (layouts/blocks/header) ---------- */

    function initHeader() {
        var header = document.getElementById('header');
        if (!header) {
            return;
        }
        var button = header.querySelector('.kv-nav-button');
        var groups = header.querySelectorAll('.kv-nav-group');

        function setGroup(group, open) {
            group.classList.toggle('is-open', open);
            group.querySelector('.kv-nav-toggle').setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function closeGroups(except) {
            Array.prototype.forEach.call(groups, function (group) {
                if (group !== except) {
                    setGroup(group, false);
                }
            });
        }

        if (button) {
            button.addEventListener('click', function () {
                var open = !header.classList.contains('kv-nav-open');
                header.classList.toggle('kv-nav-open', open);
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        Array.prototype.forEach.call(groups, function (group) {
            group.querySelector('.kv-nav-toggle').addEventListener('click', function (e) {
                e.stopPropagation();
                closeGroups(group);
                setGroup(group, !group.classList.contains('is-open'));
            });
        });

        document.addEventListener('click', function (e) {
            if (!header.contains(e.target)) {
                closeGroups(null);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeGroups(null);
            }
        });
    }

    /* ---------- Dropdowns in shared markup (Mijn teams tables) ---------- */

    function initDropdowns() {
        each('[data-toggle="dropdown"]', function (toggle) {
            var menu = toggle.parentNode.querySelector('.dropdown-menu');
            if (!menu) {
                return;
            }
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var open = !menu.classList.contains('show');
                each('.dropdown-menu.show', function (other) { other.classList.remove('show'); });
                menu.classList.toggle('show', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
        document.addEventListener('click', function () {
            each('.dropdown-menu.show', function (menu) { menu.classList.remove('show'); });
        });
    }

    /* ---------- Hero slideshow (blocks/event-hero) ---------- */

    // A slow crossfade between the series photos. Each photo only loads just
    // before it is shown; reduced motion keeps the first one.
    function initSlideshows() {
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        each('[data-kv-slideshow]', function (show) {
            var images = show.querySelectorAll('img');
            var current = 0;
            if (images.length < 2) {
                return;
            }

            function prepare(img) {
                if (img.dataset.src) {
                    img.srcset = img.dataset.srcset;
                    img.src = img.dataset.src;
                    delete img.dataset.src;
                }
            }

            prepare(images[1]);
            setInterval(function () {
                if (document.hidden) {
                    return;
                }
                var next = (current + 1) % images.length;
                var img = images[next];
                if (!img.complete) {
                    return;
                }
                images[current].classList.remove('is-shown');
                img.classList.add('is-shown');
                current = next;
                prepare(images[(current + 1) % images.length]);
            }, 6500);
        });
    }

    /* ---------- Scroll reveal (every page) ---------- */

    // Content below the fold fades in and rises a little the first time it
    // scrolls into view, like the main site. What is on screen when the page
    // loads is never hidden, nor is anything without JS, with reduced motion,
    // or inside a form. Split blocks and review rows come in from the side.
    var REVEAL = [
        '.kv-sec-title', '.kv-cms-head', '.kv-cms-intro',
        '.kv-card', '.kv-tile', '.kv-post', '.kv-past', '.kv-ticket', '.kv-faq-item', '.kv-logo',
        '.kv-review-row', '.kv-review',
        '.kv-cms-split-text', '.kv-cms-split-image', '.kv-cms-split-video',
        '.kv-cms-cta-inner', '.kv-video', '.kv-prose', '.kv-text', '.kv-list', '.kv-gallery'
    ].join(',');

    function initReveal() {
        if (!('IntersectionObserver' in window) ||
            (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
            return;
        }

        var fold = window.innerHeight;
        var targets = [];
        each(REVEAL, function (el) {
            // Nested targets ride along with their parent.
            if (el.closest('form, [data-kv-no-reveal]') ||
                (el.parentElement && el.parentElement.closest(REVEAL)) ||
                el.getBoundingClientRect().top < fold) {
                return;
            }
            targets.push(el);
        });

        // Review rows alternate sides; a split block's image comes in from
        // its own side and the text from the other.
        each('.kv-reviews', function (list) {
            Array.prototype.forEach.call(list.children, function (row, i) {
                row.classList.add(i % 2 ? 'kv-reveal-right' : 'kv-reveal-left');
            });
        });
        each('.kv-cms-text-image', function (block) {
            var left = block.classList.contains('kv-cms-image-left');
            each('.kv-cms-split-text', function (el) {
                el.classList.add(left ? 'kv-reveal-right' : 'kv-reveal-left');
            }, block);
            each('.kv-cms-split-image, .kv-cms-split-video', function (el) {
                el.classList.add(left ? 'kv-reveal-left' : 'kv-reveal-right');
            }, block);
        });

        function done(el) {
            el.classList.remove('kv-reveal', 'kv-reveal-in', 'kv-reveal-left', 'kv-reveal-right');
            el.style.transitionDelay = '';
        }

        var observer = new IntersectionObserver(function (entries) {
            var step = 0;
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                var el = entry.target;
                observer.unobserve(el);
                // Items that arrive together follow each other closely.
                var delay = Math.min(step++, 4) * 80;
                el.style.transitionDelay = delay + 'ms';
                el.classList.add('kv-reveal-in');
                // Back to its own styles (and transitions) once it has landed.
                setTimeout(function () { done(el); }, delay + 800);
            });
        }, { rootMargin: '0px 0px -8% 0px' });

        targets.forEach(function (el) {
            el.classList.add('kv-reveal');
            observer.observe(el);
        });
    }

    /* ---------- Phone order bar (events/view) ---------- */

    // Shown while neither the hero button nor the ticket list is on screen.
    function initBuyBar() {
        var bar = document.getElementById('kv-buybar');
        if (!bar || !('IntersectionObserver' in window)) {
            return;
        }
        var visible = {};
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                visible[entry.target.id] = entry.isIntersecting;
            });
            var show = !visible['kv-cta'] && !visible.tickets;
            bar.classList.toggle('kv-buybar-visible', show);
            bar.setAttribute('aria-hidden', show ? 'false' : 'true');
            // Lets the chat bubble step up above the bar (see .kv-buybar-on in _kleurveld.scss).
            document.documentElement.style.setProperty('--kv-buybar-h', bar.offsetHeight + 'px');
            document.documentElement.classList.toggle('kv-buybar-on', show);
        });
        [ document.getElementById('kv-cta'), document.getElementById('tickets') ].forEach(function (el) {
            if (el) {
                observer.observe(el);
            }
        });
    }

    /* ---------- Venue map (blocks/venue) ---------- */

    // The Google Maps API only loads on pages that show a map.
    function initMap() {
        var mapElement = document.getElementById('map');
        if (!mapElement || !mapElement.getAttribute('data-maps-key')) {
            return;
        }
        window.kvInitMap = function () {
            var position = JSON.parse(mapElement.getAttribute('data-coordinates'));
            var map = new google.maps.Map(mapElement, { zoom: 16, center: position, scrollwheel: false });
            var marker = new google.maps.Marker({ position: position, map: map });
            var info = new google.maps.InfoWindow({ content: mapElement.getAttribute('data-address') });
            marker.addListener('click', function () {
                info.open(map, marker);
            });
        };
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(mapElement.getAttribute('data-maps-key')) + '&callback=kvInitMap&loading=async';
        document.head.appendChild(script);
    }

    /* ---------- Poster and video popups ---------- */

    // a.image-popup opens its image, a.popup its page (a video) in a dialog.
    // Without dialog support the link just opens normally.
    function initPopups() {
        if (typeof HTMLDialogElement !== 'function') {
            return;
        }
        var dialog = null;

        function open(content, label) {
            if (!dialog) {
                dialog = document.createElement('dialog');
                dialog.className = 'kv-dialog';
                dialog.innerHTML = '<button type="button" class="kv-dialog-close" aria-label="Sluiten">&times;</button><div class="kv-dialog-body"></div>';
                dialog.querySelector('.kv-dialog-close').addEventListener('click', function () { dialog.close(); });
                dialog.addEventListener('click', function (e) {
                    if (e.target === dialog || e.target.classList.contains('kv-lightbox-stage')) {
                        dialog.close();
                    }
                });
                dialog.addEventListener('close', function () {
                    dialog.querySelector('.kv-dialog-body').innerHTML = '';
                    dialog.classList.remove('kv-dialog-gallery');
                    gallery = null;
                });
                document.body.appendChild(dialog);
            }
            var body = dialog.querySelector('.kv-dialog-body');
            body.innerHTML = '';
            body.appendChild(content);
            dialog.setAttribute('aria-label', label || '');
            dialog.showModal();
        }

        /* Gallery: one photo at a time, with previous / next (buttons, arrow
           keys, swiping) and the caption and position under it. */
        var gallery = null;

        function show(index) {
            var links = gallery.links;
            gallery.index = (index + links.length) % links.length;
            var link = links[gallery.index];
            var img = gallery.element.querySelector('.kv-lightbox-image');
            var thumb = link.querySelector('img');
            img.src = link.href;
            img.alt = thumb ? thumb.alt : '';
            var caption = link.getAttribute('data-kv-caption') || '';
            gallery.element.querySelector('.kv-lightbox-caption').textContent = caption;
            gallery.element.querySelector('.kv-lightbox-count').textContent =
                links.length > 1 ? (gallery.index + 1) + ' / ' + links.length : '';
            dialog.setAttribute('aria-label', caption || ('Foto ' + (gallery.index + 1) + ' van ' + links.length));
            // The neighbours load while this one is looked at.
            [ 1, -1 ].forEach(function (step) {
                if (links.length > 1) {
                    (new Image()).src = links[(gallery.index + step + links.length) % links.length].href;
                }
            });
        }

        function openGallery(link) {
            var name = link.getAttribute('data-kv-gallery');
            var links = Array.prototype.filter.call(document.querySelectorAll('a[data-kv-gallery]'), function (a) {
                return a.getAttribute('data-kv-gallery') === name;
            });
            var element = document.createElement('figure');
            element.className = 'kv-lightbox';
            element.innerHTML =
                '<div class="kv-lightbox-stage"><img class="kv-dialog-image kv-lightbox-image" alt=""></div>' +
                '<figcaption class="kv-lightbox-bar"><span class="kv-lightbox-caption"></span><span class="kv-lightbox-count"></span></figcaption>' +
                (links.length > 1
                    ? '<button type="button" class="kv-lightbox-nav kv-lightbox-prev" aria-label="Vorige foto"></button>' +
                      '<button type="button" class="kv-lightbox-nav kv-lightbox-next" aria-label="Volgende foto"></button>'
                    : '');
            gallery = { links: links, index: 0, element: element };
            each('.kv-lightbox-nav', function (button) {
                button.addEventListener('click', function () {
                    show(gallery.index + (button.classList.contains('kv-lightbox-next') ? 1 : -1));
                });
            }, element);

            var startX = null;
            element.addEventListener('touchstart', function (e) {
                startX = e.touches.length === 1 ? e.touches[0].clientX : null;
            }, { passive: true });
            element.addEventListener('touchend', function (e) {
                if (startX === null || !gallery) {
                    return;
                }
                var dx = e.changedTouches[0].clientX - startX;
                startX = null;
                if (Math.abs(dx) > 40) {
                    show(gallery.index + (dx < 0 ? 1 : -1));
                }
            });

            open(element, '');
            dialog.classList.add('kv-dialog-gallery');
            show(links.indexOf(link));
        }

        document.addEventListener('keydown', function (e) {
            if (!gallery || !dialog || !dialog.open || gallery.links.length < 2) {
                return;
            }
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                e.preventDefault();
                show(gallery.index + (e.key === 'ArrowRight' ? 1 : -1));
            }
        });

        document.addEventListener('click', function (e) {
            var galleryLink = e.target.closest && e.target.closest('a[data-kv-gallery]');
            if (galleryLink && !(e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1)) {
                e.preventDefault();
                openGallery(galleryLink);
                return;
            }
            var link = e.target.closest && e.target.closest('a.image-popup, a.popup');
            if (!link || e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) {
                return;
            }
            e.preventDefault();
            var content;
            if (link.classList.contains('image-popup')) {
                content = document.createElement('img');
                content.src = link.href;
                var img = link.querySelector('img');
                content.alt = img ? img.alt : '';
                content.className = 'kv-dialog-image';
            } else {
                content = document.createElement('iframe');
                content.src = link.href;
                content.className = 'kv-dialog-frame';
                content.allow = 'autoplay; fullscreen; picture-in-picture';
                content.allowFullscreen = true;
            }
            open(content, link.getAttribute('title') || link.textContent.trim());
        });
    }

    /* ---------- Long galleries (cms/blocks/gallery) ---------- */

    // The first `visible` photos show; a button shows the rest. The
    // lightbox steps through all of them either way.
    function initGalleries() {
        each('[data-kv-gallery-visible]', function (list) {
            var visible = parseInt(list.getAttribute('data-kv-gallery-visible'), 10);
            var button = document.querySelector('[data-kv-gallery-more="' + list.id + '"]');
            if (!visible || !button) {
                return;
            }
            Array.prototype.forEach.call(list.children, function (item, i) {
                if (i >= visible) {
                    item.hidden = true;
                }
            });
            button.hidden = false;
            button.addEventListener('click', function () {
                var first = null;
                Array.prototype.forEach.call(list.children, function (item) {
                    if (item.hidden) {
                        item.hidden = false;
                        first = first || item;
                    }
                });
                button.hidden = true;
                var link = first && first.querySelector('a');
                if (link) {
                    link.focus({ preventScroll: true });
                }
            });
        });
    }

    /* ---------- Register form: new team field (events/registerOnePage) ---------- */

    function initTeamSelect() {
        var select = document.getElementById('group');
        var field = document.getElementById('new-team');
        var input = document.getElementById('team');
        if (!select || !field || !input) {
            return;
        }
        function sync() {
            var isNew = select.value === 'new';
            field.hidden = !isNew;
            input.disabled = !isNew;
            input.required = isNew;
        }
        select.addEventListener('change', function () {
            sync();
            if (select.value === 'new') {
                input.focus();
            }
        });
        sync();
    }

    /* ---------- Copy buttons (orders/thanks) ---------- */

    function initCopyButtons() {
        each('[data-copy]', function (button) {
            button.addEventListener('click', function () {
                var input = document.querySelector(button.getAttribute('data-copy'));
                var done = function () { button.textContent = 'Gekopieerd!'; };
                input.select();
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
                } else {
                    document.execCommand('copy');
                    done();
                }
            });
        });
    }

    function init() {
        initHeader();
        initDropdowns();
        initSlideshows();
        initBuyBar();
        initMap();
        initPopups();
        initGalleries();
        initTeamSelect();
        initCopyButtons();
        initReveal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
