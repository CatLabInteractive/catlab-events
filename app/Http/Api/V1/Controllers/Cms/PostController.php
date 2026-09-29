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
use App\Http\Api\V1\ResourceDefinitions\Cms\PostResourceDefinition;
use App\Models\Organisation;
use App\Models\Post;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Blog posts of an organisation. Every write (and delete) goes through
 * App\Cms\PostWriter, like the admin editor.
 *
 * Class PostController
 * @package App\Http\Api\V1\Controllers\Cms
 */
class PostController extends ResourceController
{
    const RESOURCE_DEFINITION = PostResourceDefinition::class;
    const RESOURCE_ID = 'post';
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
            'organisations/{' . self::PARENT_RESOURCE_ID . '}/posts',
            'posts',
            'Cms\PostController',
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
        /** @var Organisation $organisation */
        $organisation = $this->getParent($request);
        return $organisation->posts();
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
     * Save through the writer (validation, same-organisation featured image,
     * cache busting).
     * @param Request $request
     * @param Model $entity
     * @return Model
     * @throws \CatLab\Requirements\Exceptions\ResourceValidationException
     */
    protected function saveEntity(Request $request, Model $entity)
    {
        $isNew = !$entity->exists;
        $entity = $this->traitBeforeSaveEntity($request, $entity, $isNew);

        /** @var Post $entity */
        return $this->throughWriter(function () use ($entity) {
            return app(PostWriter::class)->savePost($entity);
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

        /** @var Post $post */
        $post = $this->findEntity($request);
        $this->authorizeDestroy($request, $post);

        try {
            app(PostWriter::class)->deletePost($post);
        } catch (ValidationException $e) {
            return $this->getRefusedResponse($e);
        }

        return $this->toResponse([
            'success' => true,
            'message' => 'Successfully deleted entity.'
        ]);
    }

    /**
     * Bulk delete is not offered for posts: deletes go one by one through
     * the writer, like the admin.
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function bulkDestroy(Request $request)
    {
        return $this->toResponse([
            'error' => [ 'message' => 'Delete posts one by one.' ]
        ])->setStatusCode(405);
    }
}
