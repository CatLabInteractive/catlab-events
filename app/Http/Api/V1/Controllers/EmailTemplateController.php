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
use App\Http\Api\V1\ResourceDefinitions\EmailTemplateResourceDefinition;
use App\Models\EmailTemplate;
use App\Models\Organisation;
use App\Services\EmailTemplates;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The library of named mail templates of an organisation.
 *
 * @package App\Http\Api\V1\Controllers
 */
class EmailTemplateController extends ResourceController
{
    const RESOURCE_DEFINITION = EmailTemplateResourceDefinition::class;
    const RESOURCE_ID = 'emailTemplate';
    const PARENT_RESOURCE_ID = 'organisation';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
        store as traitStore;
        edit as traitEdit;
        patch as traitPatch;
    }
    use CmsApiValidation;

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->childResource(
            static::RESOURCE_DEFINITION,
            'organisations/{' . self::PARENT_RESOURCE_ID . '}/emailTemplates',
            'emailTemplates',
            'EmailTemplateController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        )->tag('emails');
    }

    /**
     * Only the named templates: a template without a name is the
     * organisation's own version of a standard mail.
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Organisation $organisation */
        $organisation = $this->getParent($request);
        return $organisation->emailTemplates()->library();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $parentId = $request->route(self::PARENT_RESOURCE_ID);
        return Organisation::findOrFail($parentId);
    }

    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    /**
     * @param Request $request
     * @param Model $entity
     * @return Model
     * @throws \CatLab\Requirements\Exceptions\ResourceValidationException
     */
    protected function saveEntity(Request $request, Model $entity)
    {
        $isNew = !$entity->exists;
        $entity = $this->traitBeforeSaveEntity($request, $entity, $isNew);

        /** @var EmailTemplate $entity */
        return $this->throughWriter(function () use ($entity) {
            $this->validateTemplate($entity);
            $entity->save();

            return $entity;
        });
    }

    /**
     * @param EmailTemplate $template
     * @throws ValidationException
     */
    protected function validateTemplate(EmailTemplate $template)
    {
        $unique = Rule::unique('email_templates', 'name')
            ->where('organisation_id', $template->organisation_id)
            ->where('type', $template->type);

        if ($template->exists) {
            $unique->ignore($template->id);
        }

        Validator::make([
            'type' => $template->type,
            'name' => $template->name,
            'subject' => $template->subject,
            'content' => $template->content,
        ], [
            'type' => [ 'required', Rule::in(EmailTemplates::EVENT_TYPES) ],
            'name' => [ 'required', 'string', 'max:100', $unique ],
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
        ], [
            'name.unique' => 'Er is al een sjabloon met deze naam voor deze mail.',
        ], [
            'name' => 'naam',
            'subject' => 'onderwerp',
            'content' => 'inhoud',
            'type' => 'mail',
        ])->validate();
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function store(Request $request)
    {
        return $this->answerMalformedInput(function () use ($request) {
            return $this->traitStore($request);
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
     * A template an event still uses is not deleted.
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(Request $request)
    {
        $this->request = $request;

        /** @var EmailTemplate $template */
        $template = $this->findEntity($request);
        $this->authorizeDestroy($request, $template);

        $names = $template->getEventNames();
        if (count($names) > 0) {
            return $this->getRefusedResponse(ValidationException::withMessages([
                'used_by' => 'Dit sjabloon wordt nog gebruikt door: ' . implode(', ', $names) . '.',
            ]));
        }

        // The foreign key restricts deleting a template that is still
        // referenced (by the mails of deleted events).
        $template->eventEmails()->delete();
        $template->delete();

        return $this->toResponse([
            'success' => true,
            'message' => 'Successfully deleted entity.'
        ]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function bulkDestroy(Request $request)
    {
        return $this->toResponse([
            'error' => [ 'message' => 'Delete templates one by one.' ]
        ])->setStatusCode(405);
    }
}
