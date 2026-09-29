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
use App\Cms\Redirects;
use App\Models\Organisation;
use App\Models\PostTranslation;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Public blog posts at /{locale/}YYYY/MM/DD/slug (the WordPress permalink
 * form). The route is registered by PageController::routes(), before the
 * page catch-all.
 *
 * Class PostController
 * @package App\Http\Controllers
 */
class PostController extends Controller
{
    /**
     * @param Request $request
     * @param Redirects $redirects
     * @param string $year
     * @param string $month
     * @param string $day
     * @param string $slug
     * @return mixed
     */
    public function show(Request $request, Redirects $redirects, $year, $month, $day, $slug)
    {
        $organisation = $this->getOrganisation();
        if (!$organisation) {
            abort(404);
        }

        /** @var PostTranslation|null $translation */
        $translation = PostTranslation::query()
            ->where('organisation_id', '=', $organisation->id)
            ->where('locale', '=', app()->getLocale())
            ->where('slug', '=', $slug)
            ->whereHas('post', function ($query) use ($organisation) {
                $query->where('organisation_id', '=', $organisation->id);
            })
            ->with('post.featuredImage')
            ->first();

        $preview = $request->query('preview') && $this->canPreview($organisation);

        if (!$translation || (!$preview && !$this->isVisible($translation))) {
            if ($redirect = $redirects->resolve($organisation, $request)) {
                return $redirect;
            }
            abort(404);
        }

        $post = $translation->post;

        // A typo'd or timezone-shifted old link still lands: 301 to the real date.
        if ($post->published_at) {
            $date = $post->published_at->copy()->setTimezone(PostTranslation::URL_TIMEZONE)->format('Y/m/d');
            if ($date !== $year . '/' . $month . '/' . $day) {
                $query = $request->getQueryString();
                return redirect()->to($translation->getUrl() . ($query ? '?' . $query : ''), 301);
            }
        }

        View::share('cmsLocale', $translation->locale);

        $alternates = [];
        $translations = $post->translations()->published()->get()->sortBy(function (PostTranslation $t) {
            return array_search($t->locale, config('cms.locales'));
        });
        foreach ($translations as $alternate) {
            $alternates[$alternate->locale] = $alternate->getUrl();
        }

        $image = $post->featuredImage;
        $publishedAt = $post->published_at
            ? $post->published_at->copy()->setTimezone(PostTranslation::URL_TIMEZONE)
            : null;

        return view('cms.blog.show', [
            'organisation' => $organisation,
            'translation' => $translation,
            'post' => $post,
            'publishedAt' => $publishedAt,
            'byline' => Blog::byline($translation, $organisation),
            'alternates' => $alternates,
            'otherTranslations' => array_diff_key($alternates, [ $translation->locale => true ]),
            'canonicalUrl' => $translation->getUrl(),
            'pageTitle' => $translation->meta_title ?: $translation->title,
            'ogTitle' => $translation->meta_title ?: $translation->title,
            'ogType' => 'article',
            'description' => $translation->meta_description ?: ($translation->excerpt ?: null),
            'ogImageUrl' => $image ? $image->getUrl([ 'width' => 1200, 'height' => 630 ]) : null,
            'imageUrl' => $image ? $image->getUrl([ 'width' => 1280 ]) : null,
            'preview' => $preview,
        ]);
    }

    /**
     * Published translation of a post whose date has passed.
     * @param PostTranslation $translation
     * @return bool
     */
    protected function isVisible(PostTranslation $translation): bool
    {
        $post = $translation->post;

        return $translation->is_published
            && $post
            && $post->published_at
            && $post->published_at->lte(now());
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
}
