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

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\Controllers\Cms\CmsApiValidation;
use App\Http\Api\V1\ResourceDefinitions\StandardMailResourceDefinition;
use App\Models\Organisation;
use App\Models\StandardMail;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The automatic mails of an organisation, by type. There is always one of
 * each: editing writes the organisation's own version, deleting removes it
 * so the standard text goes out again.
 *
 * @package App\Http\Api\V1\Controllers
 */
class StandardMailController extends ResourceController
{
    const RESOURCE_DEFINITION = StandardMailResourceDefinition::class;
    const RESOURCE_ID = 'mailType';
    const PARENT_RESOURCE_ID = 'organisation';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController {
        edit as traitEdit;
        patch as traitPatch;
    }
    use CmsApiValidation;
    use ListsMailsByType;

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->childResource(
            static::RESOURCE_DEFINITION,
            'organisations/{' . self::PARENT_RESOURCE_ID . '}/standardMails',
            'organisations/{' . self::PARENT_RESOURCE_ID . '}/standardMails',
            'StandardMailController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID,
                RouteCollection::OPTIONS_ONLY_INCLUDE_METHODS => [
                    RouteCollection::OPTIONS_METHOD_INDEX,
                    RouteCollection::OPTIONS_METHOD_VIEW,
                    RouteCollection::OPTIONS_METHOD_EDIT,
                    RouteCollection::OPTIONS_METHOD_PATCH,
                    RouteCollection::OPTIONS_METHOD_DESTROY,
                ],
            ]
        )->tag('emails');
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Organisation $organisation */
        $organisation = $this->getParent($request);
        return $organisation->emailTemplates()->organisationVersion();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        return Organisation::findOrFail($request->route(self::PARENT_RESOURCE_ID));
    }

    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    /**
     * Every mail, rewritten or not, filtered or sorted by type; there is nothing to page.
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request)
    {
        $this->request = $request;
        $this->authorizeIndex($request);

        /** @var Organisation $organisation */
        $organisation = $this->getParent($request);
        $context = $this->getContext(Action::INDEX);

        return $this->getResourceResponse(
            $this->toResources($this->filterAndSortByType($request, StandardMail::allOf($organisation)), $context, $this->resourceDefinition),
            $context
        );
    }

    /**
     * @param Request $request
     * @return StandardMail
     */
    protected function findEntity(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $this->getParent($request);
        $type = $request->route()->parameter($this->getIdParameter());

        $mail = StandardMail::of($organisation, $type);
        if (!$mail) {
            $this->notFound($type, StandardMail::class);
        }

        return $mail;
    }

    /**
     * @param Request $request
     * @param Model $entity
     * @return Model
     * @throws \CatLab\Requirements\Exceptions\ResourceValidationException
     */
    protected function saveEntity(Request $request, Model $entity)
    {
        /** @var StandardMail $entity */
        return $this->throughWriter(function () use ($entity) {
            Validator::make([
                'subject' => $entity->subject,
                'content' => $entity->content,
            ], [
                'subject' => 'required|string|max:255',
                'content' => 'required|string',
            ], [], [
                'subject' => 'onderwerp',
                'content' => 'inhoud',
            ])->validate();

            $entity->name = '';
            $entity->save();

            return $entity;
        });
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function edit(Request $request)
    {
        return $this->answerMalformedInput(function () use ($request) {
            return $this->traitEdit($request);
        });
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function patch(Request $request)
    {
        return $this->answerMalformedInput(function () use ($request) {
            return $this->traitPatch($request);
        });
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function bulkDestroy(Request $request)
    {
        return $this->toResponse([
            'error' => [ 'message' => 'Reset mails one by one.' ]
        ])->setStatusCode(405);
    }
}
