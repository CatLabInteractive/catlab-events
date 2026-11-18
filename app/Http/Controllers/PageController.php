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

namespace App\Http\Controllers;

use App\Cms\Blog;
use App\Cms\BlockRenderer;
use App\Cms\Blocks\BlockContext;
use App\Cms\Redirects;
use App\Models\Organisation;
use App\Models\PageTranslation;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Public CMS pages. Routes are registered by routes() at the very end of
 * routes/web.php: once under /{locale} for the non-default locales, once at
 * the root for the default locale.
 *
 * Class PageController
 * @package App\Http\Controllers
 */
class PageController extends Controller
{
    /**
     * Register the public CMS routes. Must be called last in routes/web.php:
     * the page route is a catch-all. The prefixed group goes first so that
     * /en/... is not taken for a Dutch path starting with "en".
     */
    public static function routes()
    {
        $prefixedLocales = array_values(array_diff(config('cms.locales'), [ config('cms.default_locale') ]));

        if (count($prefixedLocales) > 0) {
            Route::prefix('{locale}')
                ->where([ 'locale' => implode('|', array_map('preg_quote', $prefixedLocales)) ])
                ->middleware('cms.locale')
                ->group(function () {
                    Route::get('/', 'PageController@home');
                    self::localisedRoutes();
                });
        }

        Route::middleware('cms.locale')->group(function () {
            self::localisedRoutes();
        });
    }

    /**
     * Routes that exist once per locale.
     */
    protected static function localisedRoutes()
    {
        Route::get('blog', 'PageController@blogIndex');

        // Before the page catch-all, which would otherwise take these paths.
        Route::get('{year}/{month}/{day}/{slug}', 'PostController@show')
            ->where([ 'year' => '[0-9]{4}', 'month' => '[0-9]{2}', 'day' => '[0-9]{2}', 'slug' => '[a-z0-9\-]+' ]);

        Route::get('{path}', 'PageController@show')->where('path', '[a-z0-9\-]+(?:/[a-z0-9\-]+)*');
    }

    /**
     * The organisation's published home page translation in $locale, or null
     * (no home page selected, or not published in that locale).
     * @param Organisation $organisation
     * @param string $locale
     * @return PageTranslation|null
     */
    public static function homeFor(Organisation $organisation, string $locale): ?PageTranslation
    {
        if (!$organisation->home_page_id) {
            return null;
        }

        return PageTranslation::forOrganisation($organisation)
            ->published()
            ->where('page_id', '=', $organisation->home_page_id)
            ->where('locale', '=', $locale)
            ->whereHas('page')
            ->first();
    }

    /**
     * /en, /fr: the home page in that locale.
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function home(Request $request)
    {
        $organisation = $this->getOrganisation();
        $translation = $organisation ? self::homeFor($organisation, app()->getLocale()) : null;

        if (!$translation) {
            abort(404);
        }

        return $this->render($request, $translation);
    }

    /**
     * @param Request $request
     * @param Redirects $redirects
     * @param string $path
     * @return mixed
     */
    public function show(Request $request, Redirects $redirects, string $path)
    {
        $organisation = $this->getOrganisation();
        if (!$organisation) {
            abort(404);
        }

        /** @var PageTranslation|null $translation */
        $translation = PageTranslation::forOrganisation($organisation)
            ->where('locale', '=', app()->getLocale())
            ->where('path', '=', $path)
            ->whereHas('page')
            ->first();

        $preview = $request->query('preview') && $this->canPreview($organisation);

        if (!$translation || (!$translation->is_published && !$preview)) {
            if ($redirect = $redirects->resolve($organisation, $request)) {
                return $redirect;
            }
            abort(404);
        }

        // One URL per home page: its own path redirects to the locale root.
        if (!$preview && $this->isHomePage($organisation, $translation)) {
            $target = self::localeRootUrl($translation->locale);
            $query = $request->getQueryString();

            return redirect()->to($target . ($query ? '?' . $query : ''), 301);
        }

        return $this->render($request, $translation, $preview);
    }

    /**
     * /blog (and /{locale}/blog): the organisation's visible posts in the
     * locale, newest first, paginated.
     * @param Request $request
     * @param Blog $blog
     * @return \Illuminate\Contracts\View\View
     */
    public function blogIndex(Request $request, Blog $blog)
    {
        $organisation = $this->getOrganisation();
        if (!$organisation) {
            abort(404);
        }

        $locale = app()->getLocale();

        $posts = $blog->visible($organisation, $locale)
            ->paginate((int) config('cms.posts_per_page', 12))
            ->withPath(Blog::indexUrl($locale));

        if ($posts->currentPage() > 1 && $posts->currentPage() > $posts->lastPage()) {
            abort(404);
        }

        // hreflang: the blog index of every locale that has posts.
        $alternates = [];
        foreach (config('cms.locales') as $alternateLocale) {
            if ($alternateLocale === $locale ? $posts->total() > 0 : $blog->hasPosts($organisation, $alternateLocale)) {
                $alternates[$alternateLocale] = Blog::indexUrl($alternateLocale);
            }
        }

        $canonicalUrl = $posts->currentPage() > 1 ? $posts->url($posts->currentPage()) : Blog::indexUrl($locale);

        return view('cms.blog.index', [
            'organisation' => $organisation,
            'posts' => $posts,
            'alternates' => $alternates,
            'canonicalUrl' => $canonicalUrl,
            'ogTitle' => __('cms.blog'),
            'preview' => false,
        ]);
    }

    /**
     * Render a page translation (also used by EventController@index for the
     * default-locale home page).
     * @param Request $request
     * @param PageTranslation $translation
     * @param bool $preview
     * @return \Illuminate\Contracts\View\View
     */
    public function render(Request $request, PageTranslation $translation, bool $preview = false)
    {
        $organisation = $translation->organisation;
        $page = $translation->page;

        View::share('cmsLocale', $translation->locale);

        $context = new BlockContext($organisation, $translation->locale, $translation, $request);
        $blocks = app(BlockRenderer::class)->render($translation, $context);

        $isHome = $this->isHomePage($organisation, $translation);

        $alternates = [];
        $translations = $page->translations()->published()->get()->sortBy(function (PageTranslation $t) {
            return array_search($t->locale, config('cms.locales'));
        });
        foreach ($translations as $alternate) {
            $alternates[$alternate->locale] = $this->urlFor($alternate, $isHome);
        }

        return view('cms.page', [
            'organisation' => $organisation,
            'translation' => $translation,
            'blocks' => $blocks,
            'alternates' => $alternates,
            'canonicalUrl' => $this->urlFor($translation, $isHome),
            'pageTitle' => $translation->meta_title ?: $translation->title,
            'ogTitle' => $translation->meta_title ?: $translation->title,
            'description' => $this->describe($translation, $blocks),
            'ogImageUrl' => $this->ogImageUrl($translation, $blocks),
            'preview' => $preview,
            'startsWithHero' => count($blocks) > 0 && $blocks[0]['block']['type'] === 'hero',
        ]);
    }

    /**
     * Absolute URL of the default locale root ('/') or of /{locale}.
     * @param string $locale
     * @return string
     */
    public static function localeRootUrl(string $locale): string
    {
        return $locale === config('cms.default_locale') ? url('/') : url('/' . $locale);
    }

    /**
     * @param Organisation $organisation
     * @return bool
     */
    protected function canPreview(Organisation $organisation): bool
    {
        $user = Auth::user();

        return $user && $organisation->isAdmin($user);
    }

    /**
     * @param Organisation $organisation
     * @param PageTranslation $translation
     * @return bool
     */
    protected function isHomePage(Organisation $organisation, PageTranslation $translation): bool
    {
        return $organisation->home_page_id && (int) $organisation->home_page_id === (int) $translation->page_id;
    }

    /**
     * @param PageTranslation $translation
     * @param bool $isHome
     * @return string
     */
    protected function urlFor(PageTranslation $translation, bool $isHome): string
    {
        return $isHome ? self::localeRootUrl($translation->locale) : $translation->getUrl();
    }

    /**
     * meta_description, else the start of the first text block.
     * @param PageTranslation $translation
     * @param array $blocks
     * @return string|null
     */
    protected function describe(PageTranslation $translation, array $blocks): ?string
    {
        if ($translation->meta_description) {
            return $translation->meta_description;
        }

        foreach ($blocks as $block) {
            $text = $block['data']['html'] ?? $block['data']['text'] ?? $block['data']['subtitle'] ?? null;
            if (is_string($text)) {
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($text !== '') {
                    return Str::limit($text, 157);
                }
            }
        }

        return null;
    }

    /**
     * The OG image, else the first hero image.
     * @param PageTranslation $translation
     * @param array $blocks
     * @return string|null
     */
    protected function ogImageUrl(PageTranslation $translation, array $blocks): ?string
    {
        $size = [ 'width' => 1200, 'height' => 630 ];

        if ($translation->ogImage) {
            return $translation->ogImage->getUrl($size);
        }

        foreach ($blocks as $block) {
            if ($block['block']['type'] === 'hero' && !empty($block['data']['image'])) {
                return $block['data']['image']->getUrl($size);
            }
        }

        return null;
    }
}
