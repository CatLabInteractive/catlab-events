<?php

return [

    /**
     * Fallback meta description for pages without one of their own, and the
     * organisation summary in its Organization JSON-LD and llms.txt.
     */
    'default_description' => env(
        'SEO_DEFAULT_DESCRIPTION',
        'De Quizfabriek organiseert elk jaar een aantal quizzes waarbij zowel nadruk wordt gelegd op kennis als op entertainment. Onze quizzes zijn dan ook wat minder traditioneel. Natuurlijk staat kennis centraal, maar laat je niet vangen door een ronde armworstelen, retoriek of zelfs bier-proeven.'
    ),

    /**
     * Whether search engines and AI crawlers may index this site. Set
     * SEO_INDEXABLE=false on staging (it serves the production database, so
     * it would show up as a copy of the real site). When false, every
     * response gets "X-Robots-Tag: noindex, nofollow" and robots.txt
     * disallows everything. On by default so a production box with an
     * unexpected APP_ENV can never drop out of search results.
     */
    'indexable' => filter_var(env('SEO_INDEXABLE', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * 301 every GET/HEAD request on a non-canonical organisation domain (an
     * organisation_domains row with is_canonical not set) to the same path on
     * the organisation's canonical domain (is_canonical = 1). Off until the
     * WordPress cutover, when tickets.quizfabriek.be moves to www.
     */
    'canonical_redirect' => filter_var(env('CANONICAL_DOMAIN_REDIRECT', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Paths that never redirect to the canonical domain: health checks, the
     * API, and login round trips that may already be in flight on the old
     * host (their state lives in a session cookie on that host).
     */
    'canonical_redirect_except' => [
        'status',
        'api/*',
        'login',
        'login/*',
        'logout',
    ],

];
