<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2017 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Cms\Import;

use App\Cms\HtmlSanitizer;
use App\Cms\PageWriter;
use App\Cms\PostWriter;
use App\Models\CmsRedirect;
use App\Models\Organisation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Imports posts and pages of a WordPress site through its public REST API
 * (see the "WordPress import" section of the CMS design spec):
 *
 * - posts become Dutch posts, upserted on (organisation, wp_post_id), so a
 *   re-run updates them and never duplicates;
 * - pages become one unpublished Dutch translation with a single rich_text
 *   block: raw material for editors, never overwritten by a re-run;
 * - uploads used by either are copied to central storage (MediaImporter);
 * - redirects() lists the sitemap URLs that do not map onto an import.
 *
 * Only the site given to the constructor is ever fetched.
 *
 * Class WordPressImporter
 * @package App\Cms\Import
 */
class WordPressImporter
{
    const LOCALE = 'nl';

    const SITE_TIMEZONE = 'Europe/Brussels';

    /**
     * Page slugs skipped by default (drafts of the redesign, a one-off).
     */
    const DEFAULT_SKIP = [ 'sko_milk' ];

    /**
     * @var MediaUrl
     */
    private $site;

    /**
     * @var PostWriter
     */
    private $posts;

    /**
     * @var PageWriter
     */
    private $pages;

    /**
     * @var HtmlSanitizer
     */
    private $sanitizer;

    /**
     * @var ContentRewriter
     */
    private $rewriter;

    /**
     * @var MediaImporter
     */
    private $media;

    /**
     * @var ImportReport
     */
    private $report;

    /**
     * Paths (no slashes around) this run imported or, on a dry run, would
     * import: the redirect check counts them as mapped.
     * @var bool[]
     */
    private $planned = [];

    /**
     * WordPress page id => planned path, for the children's paths.
     * @var string[]
     */
    private $pagePaths = [];

    /**
     * @param string $site e.g. https://www.quizfabriek.be
     */
    public function __construct(string $site)
    {
        $this->site = new MediaUrl($site);
        $this->report = new ImportReport();
        $this->posts = app(PostWriter::class);
        $this->pages = app(PageWriter::class);
        $this->sanitizer = app(HtmlSanitizer::class);
        $this->rewriter = new ContentRewriter($this->site);
        $this->media = new MediaImporter($this->site, app(\App\Cms\CmsAssets::class), $this->report);
    }

    /**
     * @return ImportReport
     */
    public function report(): ImportReport
    {
        return $this->report;
    }

    /**
     * @param Organisation $organisation
     * @param bool $dryRun
     */
    public function importPosts(Organisation $organisation, bool $dryRun = false): void
    {
        foreach ($this->paginate('/wp-json/wp/v2/posts', [ '_embed' => 1 ]) as $item) {
            try {
                $this->importPost($organisation, $item, $dryRun);
            } catch (ValidationException $e) {
                $this->report->warnings[] = 'post ' . ($item['id'] ?? '?') . ' not imported: '
                    . implode(' ', array_merge(...array_values($e->errors())));
            }
        }
    }

    /**
     * @param Organisation $organisation
     * @param string[] $skip page slugs to leave out (slugs ending in -nieuw always are)
     * @param string|null $home slug of the page to make the organisation's home page
     * @param bool $dryRun
     */
    public function importPages(Organisation $organisation, array $skip = self::DEFAULT_SKIP, ?string $home = null, bool $dryRun = false): void
    {
        $items = [];
        foreach ($this->paginate('/wp-json/wp/v2/pages') as $item) {
            $slug = (string) ($item['slug'] ?? '');
            if (Str::endsWith($slug, '-nieuw') || in_array($slug, $skip, true)) {
                $this->report->items[] = 'skipped page ' . $item['id'] . ' (' . $slug . ')';
                continue;
            }
            $items[(int) $item['id']] = $item;
        }

        // Parents before children.
        $depth = function (array $item) use ($items) {
            $depth = 0;
            $seen = [];
            while (!empty($item['parent']) && isset($items[(int) $item['parent']]) && !isset($seen[$item['parent']])) {
                $seen[$item['parent']] = true;
                $item = $items[(int) $item['parent']];
                $depth++;
            }
            return $depth;
        };
        uasort($items, function ($a, $b) use ($depth) {
            return [ $depth($a), $a['menu_order'] ?? 0, $a['id'] ] <=> [ $depth($b), $b['menu_order'] ?? 0, $b['id'] ];
        });

        $imported = [];
        foreach ($items as $wpId => $item) {
            try {
                $page = $this->importPage($organisation, $item, $imported, $dryRun);
                if ($page) {
                    $imported[$wpId] = $page;
                }
            } catch (ValidationException $e) {
                $this->report->warnings[] = 'page ' . $wpId . ' (' . ($item['slug'] ?? '') . ') not imported: '
                    . implode(' ', array_merge(...array_values($e->errors())));
            }
        }

        if ($home !== null) {
            $homeItem = collect($items)->first(function ($item) use ($home) {
                return ($item['slug'] ?? null) === $home;
            });
            $homePage = $homeItem ? ($imported[(int) $homeItem['id']] ?? null) : null;
            if (!$homeItem) {
                $this->report->warnings[] = 'home page "' . $home . '" not found';
            } elseif ($dryRun) {
                $this->planned[''] = true;
                $this->report->items[] = 'would set home page to "' . $home . '"';
            } elseif ($homePage) {
                $this->pages->setHomePage($organisation, $homePage);
                $this->report->items[] = 'home page set to "' . $home . '"';
            }
        }
    }

    /**
     * Put every URL of the site's post and page sitemaps that does not map
     * onto an imported post or page in the report's unmapped list.
     * @param Organisation $organisation
     */
    public function redirects(Organisation $organisation): void
    {
        foreach ($this->sitemapUrls() as $loc) {
            $path = trim((string) parse_url(html_entity_decode($loc), PHP_URL_PATH), '/');
            if (!$this->resolves($organisation, $path)) {
                $this->report->unmapped[] = '/' . $path . ($path === '' ? '' : '/');
            }
        }
    }

    /**
     * Page URLs of the site's sitemaps: WordPress core's wp-sitemap.xml index
     * (falling back to Yoast's post/page sitemaps), following only sitemaps
     * on the site itself.
     * @return string[]
     */
    private function sitemapUrls(): array
    {
        $sitemaps = [];
        $index = $this->get('/wp-sitemap.xml');
        if ($index) {
            foreach ($this->locs($index->body()) as $loc) {
                $path = (string) parse_url($loc, PHP_URL_PATH);
                if ($this->isOwnUrl($loc) && preg_match('#^(/[a-z]{2})?/wp-sitemap-[a-z0-9_\-]+\.xml$#', $path)) {
                    $sitemaps[] = $path;
                }
            }
        } else {
            $sitemaps = [ '/post-sitemap.xml', '/page-sitemap.xml' ];
        }

        $urls = [];
        foreach ($sitemaps as $sitemap) {
            $response = $this->get($sitemap);
            if (!$response) {
                $this->report->warnings[] = 'sitemap not readable: ' . $sitemap;
                continue;
            }
            foreach ($this->locs($response->body()) as $loc) {
                $urls[] = $loc;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param string $xml
     * @return string[]
     */
    private function locs(string $xml): array
    {
        preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $xml, $matches);

        return array_map(function ($loc) {
            return html_entity_decode($loc, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }, $matches[1]);
    }

    /**
     * @param string $url
     * @return bool
     */
    private function isOwnUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $own = strtolower((string) parse_url($this->site->origin(), PHP_URL_HOST));

        return preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', $own);
    }

    /**
     * @param Organisation $organisation
     * @param array $item a post from the REST API (with _embedded)
     * @param bool $dryRun
     */
    private function importPost(Organisation $organisation, array $item, bool $dryRun): void
    {
        $wpId = (int) $item['id'];
        $wpSlug = (string) $item['slug'];
        $slug = $this->slug($wpSlug);
        $publishedAt = Carbon::parse($item['date'], self::SITE_TIMEZONE);
        $html = (string) ($item['content']['rendered'] ?? '');
        $featuredUrl = $this->site->normalise($item['_embedded']['wp:featuredmedia'][0]['source_url'] ?? null);

        $existing = Post::withTrashed()
            ->where('organisation_id', '=', $organisation->id)
            ->where('wp_post_id', '=', $wpId)
            ->first();
        if ($existing && $existing->trashed()) {
            $this->report->items[] = 'skipped post ' . $wpId . ' (deleted here)';
            return;
        }

        // Media first and outside the transaction: a stored asset and its
        // redirect stay valid even when the post itself fails.
        foreach (array_merge($this->rewriter->uploadUrls($html), $featuredUrl ? [ $featuredUrl ] : []) as $url) {
            $this->media->import($url, $organisation, $dryRun);
        }

        $body = $this->body($html, false, $organisation, $dryRun);
        $this->reportDropped('post ' . $wpId, $body, $dryRun);

        $featured = $featuredUrl ? $this->media->import($featuredUrl, $organisation, $dryRun) : null;

        $postAttributes = [
            'published_at' => $publishedAt,
            'featured_image_id' => $featured ? $featured->id : ($existing ? $existing->featured_image_id : null),
        ];
        $translationAttributes = [
            'slug' => $slug,
            'title' => Str::limit($this->text($item['title']['rendered'] ?? ''), 250, '…') ?: $slug,
            'excerpt' => $this->excerpt($item['excerpt']['rendered'] ?? ''),
            'author' => $this->text($item['_embedded']['author'][0]['name'] ?? '') ?: null,
            'body' => $body,
            'is_published' => ($item['status'] ?? 'publish') === 'publish',
        ];

        $url = '/' . $publishedAt->format('Y/m/d') . '/' . $slug;
        $this->planned[trim($url, '/')] = true;
        if ($dryRun) {
            $this->report->items[] = ($existing ? 'would update' : 'would create') . ' post ' . $wpId . ' → ' . $url;
            return;
        }

        DB::transaction(function () use ($organisation, $existing, $wpId, $postAttributes, $translationAttributes) {
            if ($existing) {
                $this->posts->updatePost($existing, $postAttributes);
                $this->posts->saveTranslation($existing, self::LOCALE, $translationAttributes);
                return;
            }

            $translation = $this->posts->createPost($organisation, $postAttributes, self::LOCALE, $translationAttributes);
            $post = $translation->post;
            $post->wp_post_id = $wpId;
            $post->save();
        });

        // WordPress served the post under its own (percent-encoded) slug.
        if ($slug !== $wpSlug) {
            $this->redirect($organisation, $publishedAt->format('Y/m/d') . '/' . $wpSlug, $url);
        }

        $this->report->items[] = ($existing ? 'updated' : 'created') . ' post ' . $wpId . ' → ' . $url;
    }

    /**
     * @param Organisation $organisation
     * @param array $item a page from the REST API
     * @param Page[] $imported wp id => Page, for the parents
     * @param bool $dryRun
     * @return Page|null
     */
    private function importPage(Organisation $organisation, array $item, array $imported, bool $dryRun): ?Page
    {
        $wpId = (int) $item['id'];
        $slug = $this->slug((string) $item['slug']);

        // A top-level slug the ticket site uses itself (home, blog, ...).
        if (empty($item['parent']) && in_array($slug, config('cms.reserved_slugs', []), true)) {
            $this->report->warnings[] = 'page ' . $wpId . ': "' . $slug . '" is reserved here, imported as "wp-' . $slug . '"';
            $slug = 'wp-' . $slug;
        }

        $parentPath = !empty($item['parent']) ? ($this->pagePaths[(int) $item['parent']] ?? null) : null;
        $this->pagePaths[$wpId] = ($parentPath !== null ? $parentPath . '/' : '') . $slug;
        $this->planned[$this->pagePaths[$wpId]] = true;

        $existing = Page::withTrashed()
            ->where('organisation_id', '=', $organisation->id)
            ->where('wp_post_id', '=', $wpId)
            ->first();
        if ($existing) {
            // Editors rebuild imported pages: a re-run never overwrites them.
            $this->report->items[] = 'kept page ' . $wpId . ' (' . $slug . ', already imported)';
            return $existing->trashed() ? null : $existing;
        }

        $parentId = null;
        if (!empty($item['parent'])) {
            $parent = $imported[(int) $item['parent']] ?? null;
            if ($parent) {
                $parentId = $parent->id;
            } elseif (!$dryRun) {
                $this->report->warnings[] = 'page ' . $wpId . ': parent ' . $item['parent'] . ' not imported, placed at the top';
            }
        }

        $html = (string) ($item['content']['rendered'] ?? '');
        foreach ($this->rewriter->uploadUrls($html) as $url) {
            $this->media->import($url, $organisation, $dryRun);
        }
        $body = $this->body($html, true, $organisation, $dryRun);
        $this->reportDropped('page ' . $wpId, $body, $dryRun);

        if ($dryRun) {
            $this->report->items[] = 'would create page ' . $wpId . ' (' . $slug . ', unpublished)';
            return null;
        }

        $page = DB::transaction(function () use ($organisation, $wpId, $slug, $item, $parentId, $body) {
            $translation = $this->pages->createPage($organisation, [
                'parent_id' => $parentId,
                'sort_order' => (int) ($item['menu_order'] ?? 0),
                'show_in_menu' => false,
            ], self::LOCALE, [
                'slug' => $slug,
                'title' => Str::limit($this->text($item['title']['rendered'] ?? ''), 250, '…') ?: $slug,
                'is_published' => false,
                'blocks' => [
                    [ 'id' => PageWriter::newBlockId(), 'type' => 'rich_text', 'data' => [ 'html' => $body ] ],
                ],
            ]);

            $page = $translation->page;
            $page->wp_post_id = $wpId;
            $page->save();

            return $page;
        });

        $this->report->items[] = 'created page ' . $wpId . ' → /' . $page->translation(self::LOCALE)->path . ' (unpublished)';

        return $page;
    }

    /**
     * Rewritten (upload URLs → asset URLs), not yet sanitised content; the
     * writers sanitise.
     * @param string $html
     * @param bool $unwrapLayout
     * @param Organisation $organisation
     * @param bool $dryRun
     * @return string
     */
    private function body(string $html, bool $unwrapLayout, Organisation $organisation, bool $dryRun): string
    {
        return $this->rewriter->rewrite($html, function (string $url) use ($organisation, $dryRun) {
            $asset = $this->media->import($url, $organisation, $dryRun);
            return $asset ? $asset->getUrl() : null;
        }, $unwrapLayout);
    }

    /**
     * Record the elements the sanitiser will remove from $html.
     * @param string $label
     * @param string $html
     * @param bool $dryRun images still point at WordPress then: not counted
     */
    private function reportDropped(string $label, string $html, bool $dryRun): void
    {
        $before = $this->countTags($html);
        $after = $this->countTags($this->sanitizer->sanitize($html));

        $lost = [];
        foreach ($before as $tag => $count) {
            $missing = $count - ($after[$tag] ?? 0);
            if ($missing > 0 && !($dryRun && $tag === 'img')) {
                $lost[] = $tag . ' ×' . $missing;
            }
        }

        if ($lost) {
            $this->report->dropped[] = $label . ': ' . implode(', ', $lost);
        }
    }

    /**
     * @param string $html
     * @return int[] tag => count (h1 counted as h2: the sanitiser demotes it)
     */
    private function countTags(string $html): array
    {
        preg_match_all('/<([a-zA-Z][a-zA-Z0-9]*)\b/', $html, $matches);
        $counts = [];
        foreach ($matches[1] as $tag) {
            $tag = strtolower($tag);
            $tag = $tag === 'h1' ? 'h2' : $tag;
            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Does a sitemap path map onto an imported post or page?
     * @param Organisation $organisation
     * @param string $path no leading or trailing slash
     * @return bool
     */
    private function resolves(Organisation $organisation, string $path): bool
    {
        if (isset($this->planned[$path]) || $path === 'blog') {
            return true;
        }

        if ($path === '') {
            return $organisation->home_page_id !== null;
        }

        if (preg_match('#^(\d{4})/(\d{2})/(\d{2})/([^/]+)$#', $path, $match)) {
            $translation = PostTranslation::query()
                ->where('organisation_id', '=', $organisation->id)
                ->where('locale', '=', self::LOCALE)
                ->where('slug', '=', $this->slug($match[4]))
                ->first();
            if ($translation && trim((string) parse_url($translation->getUrl(), PHP_URL_PATH), '/') === implode('/', array_slice($match, 1, 3)) . '/' . $translation->slug) {
                return true;
            }
        }

        if (PageTranslation::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('locale', '=', self::LOCALE)
            ->where('path', '=', $path)
            ->exists()) {
            return true;
        }

        return CmsRedirect::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('from_path', '=', $path)
            ->exists();
    }

    /**
     * @param Organisation $organisation
     * @param string $from
     * @param string $to
     */
    private function redirect(Organisation $organisation, string $from, string $to): void
    {
        if (strlen($from) > 191) {
            return;
        }

        $exists = CmsRedirect::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('from_path', '=', $from)
            ->exists();
        if ($exists) {
            return;
        }

        $redirect = new CmsRedirect();
        $redirect->organisation_id = $organisation->id;
        $redirect->from_path = $from;
        $redirect->to_url = $to;
        $redirect->status_code = 301;
        $redirect->save();
    }

    /**
     * Every item of a paginated collection endpoint.
     * @param string $path
     * @param array $query
     * @return \Generator
     */
    private function paginate(string $path, array $query = []): \Generator
    {
        $page = 1;
        do {
            $response = $this->get($path, array_merge($query, [ 'per_page' => 100, 'page' => $page ]));
            if (!$response) {
                throw new \RuntimeException('Could not read ' . $this->site->origin() . $path . ' (page ' . $page . ')');
            }

            foreach ((array) $response->json() as $item) {
                if (is_array($item) && isset($item['id'])) {
                    yield $item;
                }
            }

            $totalPages = max(1, (int) $response->header('X-WP-TotalPages'));
            $page++;
        } while ($page <= $totalPages);
    }

    /**
     * GET on the site, three attempts; null when it fails.
     * @param string $path
     * @param array $query
     * @return \Illuminate\Http\Client\Response|null
     */
    private function get(string $path, array $query = [])
    {
        try {
            $response = Http::timeout(60)
                ->acceptJson()
                ->retry(3, function (int $attempt) {
                    return $attempt * 1000;
                }, null, false)
                ->get($this->site->origin() . $path, $query);
        } catch (\Throwable $e) {
            return null;
        }

        return $response->successful() ? $response : null;
    }

    /**
     * Rendered WordPress text (title, author) as plain text.
     * @param string $html
     * @return string
     */
    private function text(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @param string $html
     * @return string|null
     */
    private function excerpt(string $html): ?string
    {
        $text = $this->text($html);
        $text = trim(preg_replace([ '/\s*\[(…|\.\.\.)\]\s*$/u', '/\s+/u' ], [ '', ' ' ], $text));

        return $text === '' ? null : Str::limit($text, 990, '…');
    }

    /**
     * A WordPress slug (percent-encoded, may contain underscores) as a slug
     * the CMS accepts.
     * @param string $slug
     * @return string
     */
    private function slug(string $slug): string
    {
        $decoded = rawurldecode($slug);
        if (preg_match('/^[a-z0-9\-]+$/', $decoded)) {
            return $decoded;
        }

        return Str::slug($decoded) ?: 'wp-' . md5($slug);
    }
}
