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

namespace App\Http\Controllers\Admin;

use App\Cms\PostWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostTranslationRequest;
use App\Models\Organisation;
use App\Models\Post;
use App\Models\PostTranslation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Admin editor for blog posts: the list, and one form per translation (the
 * post's date and featured image are shared by every translation). All
 * writes go through App\Cms\PostWriter, like the API.
 *
 * Every post is resolved within the acting admin's active organisation, and
 * a post of another organisation is a 404 (as in GuestRegistrationController).
 *
 * Class PostController
 * @package App\Http\Controllers\Admin
 */
class PostController extends Controller
{
    /**
     * @var PostWriter
     */
    private $writer;

    /**
     * @param PostWriter $writer
     */
    public function __construct(PostWriter $writer)
    {
        $this->writer = $writer;
    }

    /**
     * Register the admin routes (inside the auth + admin group).
     */
    public static function routes()
    {
        $locales = implode('|', array_map('preg_quote', config('cms.locales')));

        \Route::get('posts', 'Admin\PostController@index');
        \Route::get('posts/create', 'Admin\PostController@create');
        \Route::post('posts', 'Admin\PostController@store');

        \Route::get('posts/{post}/edit/{locale}', 'Admin\PostController@edit')
            ->where([ 'post' => '[0-9]+', 'locale' => $locales ]);
        \Route::put('posts/{post}/edit/{locale}', 'Admin\PostController@update')
            ->where([ 'post' => '[0-9]+', 'locale' => $locales ]);

        \Route::get('posts/{post}/translations/{locale}/create', 'Admin\PostController@createTranslation')
            ->where([ 'post' => '[0-9]+', 'locale' => $locales ]);
        \Route::delete('posts/{post}/translations/{locale}', 'Admin\PostController@destroyTranslation')
            ->where([ 'post' => '[0-9]+', 'locale' => $locales ]);

        \Route::delete('posts/{post}', 'Admin\PostController@destroy')->where('post', '[0-9]+');
    }

    /**
     * The organisation's posts, newest first (undated drafts on top).
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $organisation = $this->getActiveOrganisation();
        $this->authorize('index', [ Post::class, $organisation ]);

        $posts = Post::forOrganisation($organisation)
            ->with('translations')
            ->orderByRaw('posts.published_at IS NULL DESC')
            ->orderBy('published_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.cms.posts.index', [
            'organisation' => $organisation,
            'posts' => $posts,
            'locales' => config('cms.locales'),
            'defaultLocale' => config('cms.default_locale'),
        ]);
    }

    /**
     * Form for a new post, starting with its default-locale translation.
     * @return \Illuminate\Contracts\View\View
     */
    public function create()
    {
        $organisation = $this->getActiveOrganisation();
        $this->authorize('create', [ Post::class, $organisation ]);

        $post = new Post();
        $post->organisation()->associate($organisation);
        $post->published_at = Carbon::now()->second(0);
        $post->setRelation('translations', new Collection());

        $translation = new PostTranslation();
        $translation->locale = config('cms.default_locale');

        return $this->form($organisation, $post, $translation);
    }

    /**
     * @param PostTranslationRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(PostTranslationRequest $request)
    {
        $organisation = $this->getActiveOrganisation();
        $this->authorize('create', [ Post::class, $organisation ]);

        $translation = $this->writer->createPost(
            $organisation,
            $request->postAttributes(),
            config('cms.default_locale'),
            $request->translationAttributes()
        );

        return $this->afterSave($request, $translation, 'Het bericht is aangemaakt.');
    }

    /**
     * @param int $postId
     * @param string $locale
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit($postId, $locale)
    {
        $post = $this->getPostInOrganisation($postId);
        $this->authorize('edit', $post);

        $translation = $post->translation($locale);
        if (!$translation) {
            return redirect(action('Admin\PostController@createTranslation', [ $post->id, $locale ]));
        }

        return $this->form($post->organisation, $post, $translation);
    }

    /**
     * Save the post fields and the translation in $locale (creating it when
     * it does not exist yet).
     * @param PostTranslationRequest $request
     * @param int $postId
     * @param string $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(PostTranslationRequest $request, $postId, $locale)
    {
        $post = $this->getPostInOrganisation($postId);
        $this->authorize('edit', $post);

        $isNew = !$post->translation($locale);

        $translation = \DB::transaction(function () use ($request, $post, $locale) {
            $this->writer->updatePost($post, $request->postAttributes());
            return $this->writer->saveTranslation($post, $locale, $request->translationAttributes());
        });

        return $this->afterSave(
            $request,
            $translation,
            $isNew ? 'De vertaling is aangemaakt.' : 'Het bericht is opgeslagen.'
        );
    }

    /**
     * Form for a translation that does not exist yet.
     * @param int $postId
     * @param string $locale
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function createTranslation($postId, $locale)
    {
        $post = $this->getPostInOrganisation($postId);
        $this->authorize('edit', $post);

        if ($post->translation($locale)) {
            return redirect(action('Admin\PostController@edit', [ $post->id, $locale ]));
        }

        $translation = new PostTranslation();
        $translation->locale = $locale;

        return $this->form($post->organisation, $post, $translation);
    }

    /**
     * @param int $postId
     * @param string $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroyTranslation($postId, $locale)
    {
        $post = $this->getPostInOrganisation($postId);
        $this->authorize('edit', $post);

        $translation = $post->translation($locale);
        if (!$translation) {
            abort(404);
        }

        $this->writer->deleteTranslation($translation);

        return redirect(action('Admin\PostController@index'))
            ->with('message', 'De vertaling "' . $locale . '" is verwijderd.');
    }

    /**
     * @param int $postId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($postId)
    {
        $post = $this->getPostInOrganisation($postId);
        $this->authorize('destroy', $post);

        $this->writer->deletePost($post);

        return redirect(action('Admin\PostController@index'))
            ->with('message', 'Het bericht is verwijderd.');
    }

    /**
     * @param Request $request
     * @param PostTranslation $translation
     * @param string $message
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function afterSave(Request $request, PostTranslation $translation, string $message)
    {
        if ($request->input('preview')) {
            return redirect($translation->getUrl() . '?preview=1');
        }

        return redirect(action('Admin\PostController@edit', [ $translation->post_id, $translation->locale ]))
            ->with('message', $message);
    }

    /**
     * @param Organisation $organisation
     * @param Post $post
     * @param PostTranslation $translation
     * @return \Illuminate\Contracts\View\View
     */
    protected function form(Organisation $organisation, Post $post, PostTranslation $translation)
    {
        $translations = $post->exists ? $post->translations()->get()->keyBy('locale') : collect();

        return view('admin.cms.posts.edit', [
            'organisation' => $organisation,
            'post' => $post,
            'translation' => $translation,
            'translations' => $translations,
            'locale' => $translation->locale,
            'locales' => config('cms.locales'),
            'defaultLocale' => config('cms.default_locale'),
            'publishedAtValue' => $post->published_at
                ? $post->published_at->copy()->setTimezone(config('app.timezone'))->format(PostTranslationRequest::DATE_FORMAT)
                : '',
        ]);
    }

    /**
     * @return Organisation
     */
    protected function getActiveOrganisation()
    {
        $organisation = \Auth::user()->getActiveOrganisation();
        if (!$organisation) {
            abort(404);
        }

        return $organisation;
    }

    /**
     * The post, or 404. Scoped to the acting admin's active organisation and
     * an admin role within it, like GuestRegistrationController.
     * @param int $postId
     * @return Post
     */
    protected function getPostInOrganisation($postId)
    {
        /** @var Post $post */
        $post = Post::findOrFail($postId);

        $organisation = \Auth::user()->getActiveOrganisation();
        if (!$organisation
            || (int) $post->organisation_id !== (int) $organisation->id
            || !$organisation->isAdmin(\Auth::user())) {
            abort(404);
        }

        $post->setRelation('organisation', $organisation);

        return $post;
    }
}
