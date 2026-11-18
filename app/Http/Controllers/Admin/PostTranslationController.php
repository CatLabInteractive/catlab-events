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

use App\Cms\Widgets\LocaleWidget;
use App\Models\Post;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\RESTResource;
use Illuminate\Http\Request;

/**
 * The translations of a blog post: its content in one language. Nested under
 * /posts/{post}; there is no separate show page, a translation opens in
 * its editor.
 *
 * @package App\Http\Controllers\Admin
 */
class PostTranslationController extends CmsAdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->setParentController(PostController::class, 'post');

        // A new translation: one of the languages that are still missing.
        $this->widgets()->forField('locale', new LocaleWidget(function (Request $request) {
            return array_values(array_diff(config('cms.locales'), $this->getExistingLocales($request)));
        }));

        $this->addFieldGroup('Zoekmachines en delen', [ 'meta_title', 'meta_description' ]);
    }

    /**
     * @return \App\Http\Api\V1\Controllers\Cms\PostTranslationController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\Cms\PostTranslationController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\Cms\PostTranslationController::RESOURCE_ID;
    }

    /**
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getRouteParameters(Request $request, $method)
    {
        return [
            'post' => $request->route('post')
        ];
    }

    /**
     * @param Request $request
     * @param $method
     * @return array|null
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    'post' => $request->route('post')
                ];
        }

        return null;
    }

    /**
     * @param Request $request
     * @param $method
     * @return array|null
     */
    protected function getAuthorizeParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    Post::find($request->route('post'))
                ];
        }

        return null;
    }

    /**
     * @param Request $request
     * @return string[]
     */
    protected function getExistingLocales(Request $request): array
    {
        $parent = Post::find($request->route('post'));

        return $parent ? $parent->translations()->pluck('locale')->all() : [];
    }

    /**
     * A translation opens in its editor.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show(Request $request)
    {
        return redirect($this->action('edit'));
    }

    /**
     * No "view" next to "edit" in the lists.
     * @param Request $request
     * @param $model
     * @return bool
     */
    protected function canViewModel(Request $request, $model)
    {
        return false;
    }

    /**
     * Saving keeps the editor open.
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function redirectAfterSave(Request $request, RESTResource $resource)
    {
        return redirect($this->resourceUrl($request, Action::EDIT, $resource, $this->getEditRouteParameters($request)));
    }

    /**
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function redirectAfterDestroy(Request $request, RESTResource $resource)
    {
        return redirect($this->getParentUrl($request));
    }

    /**
     * @param Request $request
     * @param RESTResource|null $resource
     * @return string|null
     */
    protected function getCancelUrl(Request $request, ?RESTResource $resource = null): ?string
    {
        return $this->getParentUrl($request);
    }

    /**
     * The Talen tab of the blog post.
     * @param Request $request
     * @return string
     */
    protected function getParentUrl(Request $request): string
    {
        return action('Admin\PostController@show', [ 'post' => $request->route('post') ]) . '?tab=translations';
    }

    /**
     * @param Request $request
     * @return string|null
     */
    protected function getIndexUrl(Request $request): ?string
    {
        return $this->getParentUrl($request);
    }

    /**
     * On the editor: open the public page (drafts too, as a preview).
     * @param Request $request
     * @param string $action
     * @param RESTResource|null $resource
     * @return array
     */
    protected function getPageActions(Request $request, string $action, ?RESTResource $resource = null): array
    {
        $actions = parent::getPageActions($request, $action, $resource);

        $url = $resource ? ($resource->toArray()['url'] ?? null) : null;
        if ($action === Action::EDIT && is_string($url) && $url !== '') {
            $actions[] = [
                'label' => 'Bekijk op de site',
                'url' => $url . '?preview=1',
                'style' => 'secondary',
            ];
        }

        return $actions;
    }

    protected function getEntityLabel(): string
    {
        return 'vertaling';
    }

    protected function getIndexTitle(): string
    {
        return 'Talen';
    }

    /**
     * @param Request $request
     * @param RESTResource $resource
     * @return string
     */
    protected function getResourceTitle(Request $request, RESTResource $resource): string
    {
        return $this->translationLabel($resource);
    }
}
