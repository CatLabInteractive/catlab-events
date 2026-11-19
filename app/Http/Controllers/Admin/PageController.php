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

use App\Http\Api\V1\ResourceDefinitions\Cms\PageTranslationResourceDefinition;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\RESTResource;
use Illuminate\Http\Request;
use App\Cms\PageTree;
use App\Cms\PageWriter;
use App\Cms\Widgets\PageLinkWidget;
use App\Cms\Widgets\ParentPageWidget;
use App\Models\Page;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Interfaces\Context as ContextContract;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Laravel\Table\Table;

/**
 * CMS pages: the page itself (place in the tree, menu, footer, home page)
 * is a Charon admin resource; its content lives in its translations
 * (PageTranslationController), one per language, on the Talen tab.
 *
 * @package App\Http\Controllers\Admin
 */
class PageController extends CmsAdminController
{
    public function __construct()
    {
        parent::__construct();

        // The translations: a tab on the show page, links in the list.
        $this->setChildController(PageTranslationResourceDefinition::class, PageTranslationController::class);

        $this->widgets()
            ->forField('parent_id', new ParentPageWidget())
            ->forField('link', new PageLinkWidget());
    }

    /**
     * @return \App\Http\Api\V1\Controllers\Cms\PageController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\Cms\PageController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'page';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\Cms\PageController::RESOURCE_ID;
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
        return 'pagina';
    }

    protected function getIndexTitle(): string
    {
        return 'Pagina\'s';
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
        if (!($related->getResourceDefinition() instanceof PageTranslationResourceDefinition)) {
            return parent::getRelatedResourceUrl($request, $related);
        }

        $parentId = $related->toArray()['page_id'] ?? null;
        if (!$parentId || !$request->user() || !$request->user()->can(Action::EDIT, $related->getSource())) {
            return null;
        }

        return action('Admin\PageTranslationController@edit', [ 'page' => $parentId, 'id' => $this->idOf($related) ]);
    }

    /**
     * @param Request $request
     * @param RESTResource $related
     * @return string|null
     */
    protected function getRelatedResourceLabel(Request $request, RESTResource $related): ?string
    {
        if ($related->getResourceDefinition() instanceof PageTranslationResourceDefinition) {
            return $this->translationLabel($related);
        }

        return parent::getRelatedResourceLabel($request, $related);
    }

    /**
     * A new pagina has no content yet: on to its first translation.
     * @param Request $request
     * @param RESTResource $newResource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function afterStore(Request $request, RESTResource $newResource)
    {
        \Session::flash('message', 'Pagina toegevoegd. Vul nu de inhoud in.');

        return redirect(action('Admin\PageTranslationController@create', [ 'page' => $this->idOf($newResource) ]));
    }

    /**
     * @param $path
     * @param $controller
     */
    public static function routes($path, $controller)
    {
        \Route::post($path . '/fixed-links', $controller . '@addFixedLinks');

        static::traitRoutes($path, $controller);
    }

    /**
     * The list, with the offer to manage the fixed parts of the menu
     * (quiz series, calendar, blog) as menu links while some aren't.
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        // The whole tree on one page.
        if (!$request->query->has('records')) {
            $request->query->set('records', 500);
        }

        $view = parent::index($request);

        $managed = Page::forOrganisation($this->getActiveOrganisation($request))
            ->whereIn('link', Page::FIXED_LINKS)
            ->pluck('link')
            ->all();

        return $view->with('unmanagedLinks', array_values(array_diff(Page::FIXED_LINKS, $managed)));
    }

    /**
     * Add the fixed parts of the menu that no menu link manages yet as menu
     * links, so they can be moved, renamed or hidden.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addFixedLinks(Request $request)
    {
        $organisation = $this->getActiveOrganisation($request);
        $this->authorize(Action::CREATE, [ Page::class, $organisation ]);

        app(PageWriter::class)->addFixedLinks($organisation);

        return redirect(action('Admin\PageController@index'))
            ->with('message', 'De vaste onderdelen staan nu als menulinks in de lijst.');
    }

    /**
     * The list of pages is a tree: children follow their parent, indented.
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @return Table
     */
    public function getTableForResourceCollection(
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        ContextContract $context
    ): Table {
        $pages = [];
        foreach ($collection as $resource) {
            if ($resource->getSource() instanceof Page) {
                $pages[] = $resource->getSource();
            }
        }

        $tree = app(PageTree::class);
        $rows = $tree->rows($pages);
        $position = array_flip(array_keys($rows));

        $collection->sort(function (RESTResource $a, RESTResource $b) use ($position) {
            return ($position[$this->idOf($a)] ?? PHP_INT_MAX) <=> ($position[$this->idOf($b)] ?? PHP_INT_MAX);
        });

        foreach ($collection as $resource) {
            $row = $rows[$this->idOf($resource)] ?? null;
            foreach ($resource->getProperties()->getResourceFields()->getValues() as $property) {
                if ($row && $row['depth'] > 0 && $property->getField()->getDisplayName() === 'title') {
                    $property->setValue(str_repeat('— ', $row['depth']) . $property->getValue());
                }
            }
        }

        return parent::getTableForResourceCollection($request, $collection, $resourceDefinition, $context);
    }

    /**
     * @param $action
     * @return string
     */
    protected function getView($action)
    {
        return $action === 'index' ? 'admin.cms.pages.index' : parent::getView($action);
    }
}
