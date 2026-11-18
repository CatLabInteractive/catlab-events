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
use App\Http\Api\V1\ResourceDefinitions\EventMailResourceDefinition;
use App\Models\Event;
use App\Models\EventMail;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The mails of an event, by type: which version each one sends, and the
 * event's own choice (a library template or an own text).
 *
 * @package App\Http\Api\V1\Controllers
 */
class EventMailController extends ResourceController
{
    const RESOURCE_DEFINITION = EventMailResourceDefinition::class;
    const RESOURCE_ID = 'mailType';
    const PARENT_RESOURCE_ID = 'event';

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
            'events/{' . self::PARENT_RESOURCE_ID . '}/mails',
            'events/{' . self::PARENT_RESOURCE_ID . '}/mails',
            'EventMailController',
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
        /** @var Event $event */
        $event = $this->getParent($request);
        return $event->emails();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        return Event::findOrFail($request->route(self::PARENT_RESOURCE_ID));
    }

    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    /**
     * Every mail the event sends or has a choice for, filtered or sorted by type; nothing to page.
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request)
    {
        $this->request = $request;
        $this->authorizeIndex($request);

        /** @var Event $event */
        $event = $this->getParent($request);
        $context = $this->getContext(Action::INDEX);

        return $this->getResourceResponse(
            $this->toResources($this->filterAndSortByType($request, EventMail::allOf($event)), $context, $this->resourceDefinition),
            $context
        );
    }

    /**
     * @param Request $request
     * @return EventMail
     */
    protected function findEntity(Request $request)
    {
        /** @var Event $event */
        $event = $this->getParent($request);
        $type = $request->route()->parameter($this->getIdParameter());

        $mail = EventMail::of($event, $type);
        if (!$mail) {
            $this->notFound($type, EventMail::class);
        }

        return $mail;
    }

    /**
     * A template when one is given, otherwise the own text.
     * @param Request $request
     * @param Model $entity
     * @return Model
     * @throws \CatLab\Requirements\Exceptions\ResourceValidationException
     */
    protected function saveEntity(Request $request, Model $entity)
    {
        /** @var EventMail $entity */
        return $this->throughWriter(function () use ($entity) {
            if ($entity->email_template_id) {
                Validator::make([ 'email_template_id' => $entity->email_template_id ], [
                    'email_template_id' => [
                        'integer',
                        Rule::exists('email_templates', 'id')
                            ->where('organisation_id', $entity->event->organisation_id)
                            ->where('type', $entity->type)
                            ->where(function ($query) {
                                $query->where('name', '<>', '');
                            })
                    ]
                ], [
                    'email_template_id.exists' => 'Dit sjabloon bestaat niet voor deze mail.'
                ])->validate();

                $entity->subject = null;
                $entity->content = null;
            } else {
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

                $entity->email_template_id = null;
            }

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
