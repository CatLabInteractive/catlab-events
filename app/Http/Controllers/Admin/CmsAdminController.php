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

use App\Cms\Widgets\BlocksWidget;
use App\Cms\Widgets\HtmlWidget;
use App\Cms\Widgets\ImageWidget;
use App\Cms\Widgets\LocaleWidget;
use App\Cms\Widgets\SlugWidget;
use App\Models\Organisation;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Models\RESTResource;
use CatLab\CharonFrontend\Widgets\TextareaWidget;
use CatLab\CharonFrontend\Widgets\TextWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/**
 * Shared base of the CMS admin (pages, blog posts and their translations):
 * Charon admin controllers like the rest of the admin, with the CMS widgets
 * (section blocks, images, rich text, slugs) registered for the fields that
 * need them. Everything is read and written through the CMS api controllers,
 * and so through App\Cms\PageWriter / PostWriter.
 *
 * @package App\Http\Controllers\Admin
 */
abstract class CmsAdminController extends BaseAdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->widgets()
            ->forField('title', new TextWidget([ 'maxlength' => 255 ]))
            ->forField('slug', new SlugWidget())
            ->forField('blocks', new BlocksWidget())
            ->forField('body', new HtmlWidget())
            ->forField('excerpt', new TextareaWidget(false, [ 'rows' => 2, 'maxlength' => 1000 ]))
            ->forField('meta_title', new TextWidget([ 'maxlength' => 255 ]))
            ->forField('meta_description', new TextareaWidget(false, [ 'rows' => 2, 'maxlength' => 320 ]))
            ->forField('og_image_id', new ImageWidget())
            ->forField('featured_image_id', new ImageWidget());
    }

    /**
     * The writers refuse some deletes (the home page, a page with child
     * pages): say so instead of "deleted".
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function destroy(Request $request)
    {
        $resource = $this->dispatchToApi(Action::VIEW, $request)->getResource();
        $response = $this->dispatchToApi(Action::DESTROY, $request);

        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            return $this->handleErrorResponse($response, 'destroy');
        }

        return $this->afterDestroy($request, $resource);
    }

    /**
     * The api's validation messages (the writers' own, in Dutch), without
     * its generic English first line.
     * @param JsonResponse $response
     * @param $redirectMethod
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleErrorResponse(JsonResponse $response, $redirectMethod)
    {
        $data = $response->getData(true);

        $lines = [];
        foreach (($data['error']['issues'] ?? []) as $errors) {
            foreach ((array) $errors as $error) {
                $lines[] = e($error);
            }
        }

        if (count($lines) === 0) {
            $lines[] = e($data['error']['message'] ?? 'Er ging iets mis.');
        }

        return redirect()->back()
            ->with('message', new HtmlString('Niet opgeslagen:<br>' . implode('<br>', $lines)))
            ->withInput();
    }

    /**
     * "Pagina toevoegen", also on the buttons of the lists.
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getCreateActionLabel(ResourceDefinition $resourceDefinition)
    {
        return __('charonfrontend::crud.create_entity', [ 'entity' => $this->getEntityLabel() ]);
    }

    /**
     * @param Request $request
     * @return Organisation
     */
    protected function getActiveOrganisation(Request $request): Organisation
    {
        $organisation = $request->user() ? $request->user()->getActiveOrganisation() : null;
        if (!$organisation) {
            abort(404);
        }

        return $organisation;
    }

    /**
     * @param RESTResource $resource
     * @return mixed
     */
    protected function idOf(RESTResource $resource)
    {
        $identifiers = $resource->getIdentifiers()->getValues();
        return count($identifiers) ? $identifiers[0]->getValue() : null;
    }

    /**
     * How a translation is named where it appears next to its page or post:
     * the language, and "concept" while it isn't published.
     * @param RESTResource $translation
     * @return string
     */
    protected function translationLabel(RESTResource $translation): string
    {
        $values = $translation->toArray();
        $locale = (string) ($values['locale'] ?? '');

        return LocaleWidget::name($locale) . (empty($values['is_published']) ? ' (concept)' : '');
    }
}
