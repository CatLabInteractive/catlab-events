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

use App\Http\Api\V1\ResourceDefinitions\Cms\PostTranslationResourceDefinition;
use App\Cms\Widgets\PublicationDateWidget;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\RESTResource;
use Illuminate\Http\Request;

/**
 * Blog posts: the post itself (publication date, featured image) is a
 * Charon admin resource; its text lives in its translations
 * (PostTranslationController), one per language, on the Talen tab.
 *
 * @package App\Http\Controllers\Admin
 */
class PostController extends CmsAdminController
{
    public function __construct()
    {
        parent::__construct();

        // The translations: a tab on the show page, links in the list.
        $this->setChildController(PostTranslationResourceDefinition::class, PostTranslationController::class);

        $this->widgets()->forField('published_at', new PublicationDateWidget());
    }

    /**
     * @return \App\Http\Api\V1\Controllers\Cms\PostController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\Cms\PostController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'post';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\Cms\PostController::RESOURCE_ID;
    }

    /**
     * Listed and created within the active organisation.
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
                    'organisation' => $this->getActiveOrganisation($request)->id
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
                    $this->getActiveOrganisation($request)
                ];
        }

        return null;
    }

    protected function getEntityLabel(): string
    {
        return 'blogbericht';
    }

    protected function getIndexTitle(): string
    {
        return 'Blogberichten';
    }

    /**
     * @param Request $request
     * @param RESTResource $resource
     * @return string
     */
    protected function getResourceTitle(Request $request, RESTResource $resource): string
    {
        $title = $resource->toArray()['title'] ?? null;

        return is_string($title) && $title !== ''
            ? $title
            : ucfirst($this->getEntityLabel()) . ' #' . $this->idOf($resource) . ' (zonder vertaling)';
    }

    /**
     * A translation in the list links straight to its editor.
     * @param Request $request
     * @param RESTResource $related
     * @return string|null
     */
    protected function getRelatedResourceUrl(Request $request, RESTResource $related)
    {
        if (!($related->getResourceDefinition() instanceof PostTranslationResourceDefinition)) {
            return parent::getRelatedResourceUrl($request, $related);
        }

        $parentId = $related->toArray()['post_id'] ?? null;
        if (!$parentId || !$request->user() || !$request->user()->can(Action::EDIT, $related->getSource())) {
            return null;
        }

        return action('Admin\PostTranslationController@edit', [ 'post' => $parentId, 'id' => $this->idOf($related) ]);
    }

    /**
     * @param Request $request
     * @param RESTResource $related
     * @return string|null
     */
    protected function getRelatedResourceLabel(Request $request, RESTResource $related): ?string
    {
        if ($related->getResourceDefinition() instanceof PostTranslationResourceDefinition) {
            return $this->translationLabel($related);
        }

        return parent::getRelatedResourceLabel($request, $related);
    }

    /**
     * A new blogbericht has no content yet: on to its first translation.
     * @param Request $request
     * @param RESTResource $newResource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function afterStore(Request $request, RESTResource $newResource)
    {
        \Session::flash('message', 'Blogbericht toegevoegd. Schrijf nu de tekst.');

        return redirect(action('Admin\PostTranslationController@create', [ 'post' => $this->idOf($newResource) ]));
    }
}
