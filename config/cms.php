<?php

/**
 * CMS pages and blog (see docs/superpowers/specs/2026-09-29-cms-pages-blog-design.md).
 */
return [

    /**
     * Locales a page or post can be translated in. The default locale is
     * served at the root, every other one under /{locale}/.
     */
    'locales' => [ 'nl', 'en', 'fr' ],

    'default_locale' => 'nl',

    /**
     * Top-level page slugs that would be shadowed by (or shadow) a route of
     * the ticketing application. ReservedSlugsTest checks that every
     * top-level segment of the route collection is listed here.
     */
    'reserved_slugs' => [
        'admin', 'api', 'archive', 'author', 'blog', 'calendar', 'catlabaccount',
        'competitions', 'docs', 'documents', 'donate', 'doneer', 'e', 'en', 'events',
        'fr', 'groups', 'home', 'invitations', 'livestreams', 'login', 'logout', 'nl',
        'orders', 'organisations', 'password', 'press', 'register', 's', 'sitemap.xml',
        'status', 'venues', 'css', 'js', 'fonts', 'images', 'storage',
    ],

    /**
     * Block type registry: type => class extending App\Cms\Blocks\BlockType.
     */
    'blocks' => [],

    /**
     * Maximum number of blocks on one page translation.
     */
    'max_blocks' => 40,

    'posts_per_page' => 12,

    /**
     * External hosts <img> tags and image URLs may point at, on top of the
     * central storage front host (which is always allowed). Comma separated.
     */
    'allowed_image_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CMS_ALLOWED_IMAGE_HOSTS', ''))
    ))),

    /**
     * Hosts an <iframe> may embed.
     */
    'video_hosts' => [
        'www.youtube-nocookie.com',
        'www.youtube.com',
        'player.vimeo.com',
    ],

    /**
     * Rich text allow-list, applied by App\Cms\HtmlSanitizer.
     */
    'sanitizer' => [

        // element => allowed attributes ('class' is handled separately)
        'elements' => [
            'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [],
            'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
            'a' => [ 'href', 'title', 'target' ],
            'ul' => [], 'ol' => [], 'li' => [],
            'blockquote' => [], 'pre' => [], 'code' => [], 'hr' => [],
            'img' => [ 'src', 'alt', 'width', 'height', 'loading' ],
            'figure' => [], 'figcaption' => [],
            'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
            'th' => [ 'colspan', 'rowspan' ],
            'td' => [ 'colspan', 'rowspan' ],
            'iframe' => [ 'src', 'width', 'height', 'allow', 'allowfullscreen', 'title' ],
            'span' => [], 'div' => [],
        ],

        // Only classes starting with one of these survive.
        'class_prefixes' => [ 'wp-block-', 'align', 'has-text-align-', 'cms-' ],

        'link_schemes' => [ 'http', 'https', 'mailto', 'tel' ],

        'max_input_length' => 2 * 1024 * 1024,
    ],

    /**
     * WordPress-only paths answered with 410 Gone by the fallback route.
     */
    'gone' => [
        'wp-login.php',
        'xmlrpc.php',
        'wp-admin',
        'wp-json',
    ],

];
