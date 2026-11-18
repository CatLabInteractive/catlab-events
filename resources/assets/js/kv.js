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
                    if (e.target === dialog) {
                        dialog.close();
                    }
                });
                dialog.addEventListener('close', function () {
                    dialog.querySelector('.kv-dialog-body').innerHTML = '';
                });
                document.body.appendChild(dialog);
            }
            var body = dialog.querySelector('.kv-dialog-body');
            body.innerHTML = '';
            body.appendChild(content);
            dialog.setAttribute('aria-label', label || '');
            dialog.showModal();
        }

        document.addEventListener('click', function (e) {
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
        initTeamSelect();
        initCopyButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
