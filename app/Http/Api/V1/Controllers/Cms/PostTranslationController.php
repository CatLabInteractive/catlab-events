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

namespace App\Http\Api\V1\Controllers\Cms;

use App\Cms\PostWriter;
use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\Cms\PostTranslationResourceDefinition;
use App\Models\Post;
use App\Models\PostTranslation;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Translations of a blog post (slug, title, excerpt, author, body, meta,
 * published). Every write goes through App\Cms\PostWriter, like the admin
 * editor: the slug must be free in the organisation and locale, the body
 * is sanitised.
 *
 * Class PostTranslationController
 * @package App\Http\Api\V1\Controllers\Cms
 */
class PostTranslationController extends ResourceController
{
    const RESOURCE_DEFINITION = PostTranslationResourceDefinition::class;
    const RESOURCE_ID = 'postTranslation';
    const PARENT_RESOURCE_ID = 'post';

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
            'posts/{' . self::PARENT_RESOURCE_ID . '}/translations',
            'postTranslations',
            'Cms\PostTranslationController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        )->tag('cms');
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Post $post */
        $post = $this->getParent($request);
        return $post->translations();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $parentId = $request->route(self::PARENT_RESOURCE_ID);
        return Post::findOrFail($parentId);
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

        /** @var PostTranslation $entity */
        return $this->throughWriter(function () use ($entity) {
            return app(PostWriter::class)->saveFilledTranslation($entity);
        });
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
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(Request $request)
    {
        $this->request = $request;

        /** @var PostTranslation $translation */
        $translation = $this->findEntity($request);
        $this->authorizeDestroy($request, $translation);

        try {
            app(PostWriter::class)->deleteTranslation($translation);
        } catch (ValidationException $e) {
            return $this->getRefusedResponse($e);
        }

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
            'error' => [ 'message' => 'Delete translations one by one.' ]
        ])->setStatusCode(405);
    }
}
