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

namespace App\Http\Api\V1\Controllers\Groups;

use App\Enum\GroupMemberRoles;
use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\Groups\GroupResourceDefinition;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class GroupController
 * @package App\Http\Api\V1\Controllers
 */
class GroupController extends ResourceController
{
    const RESOURCE_DEFINITION = GroupResourceDefinition::class;
    const RESOURCE_ID = 'group';
    const PARENT_RESOURCE_ID = 'user';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController;

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->childResource(
            static::RESOURCE_DEFINITION,
            'users/{' . self::PARENT_RESOURCE_ID . '}/groups',
            'groups',
            'Groups\GroupController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        )->tag('groups');

        $routes->link('groups/{groupId}/merge', 'Groups\GroupController@merge')
            ->tag('groups')
            ->summary('Merge selected group with another group.')
            ->returns(GroupResourceDefinition::class)->describe('Resulting group (could have a new ID)')
            ->parameters()
                ->path('groupId')->int()->describe('Group ID')

            ->parameters()
                ->resource(GroupResourceDefinition::class, Action::IDENTIFIER)
                ->one()
                ->describe('Group identifier to merge with.')
        ;
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var User $user */
        $user = $this->getParent($request);
        return $user->groups();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $parentId = $request->route(self::PARENT_RESOURCE_ID);
        return User::findOrFail($parentId);
    }


    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    /**
     * Merge two groups.
     * @param Request $request
     * @param $groupId
     * @return ResourceResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \App\Exceptions\Groups\GroupMergeEventOverlapException
     */
    public function merge(Request $request, $groupId)
    {
        /** @var Group $group */
        $group = Group::findOrFail($groupId);

        $otherGroup = $this->bodyIdentifiersToEntities(
            $this->getContext(Action::CREATE),
            GroupResourceDefinition::class
        );

        if (count($otherGroup) !== 1) {
            return $this->notFound(null, GroupResourceDefinition::class);
        }

        $otherGroup = $otherGroup[0];

        // Check if we are allowed to merge
        $this->authorize('merge', $group);
        $this->authorize('merge', $otherGroup);

        // Try to merge
        $context = $this->getContext(Action::VIEW);
        $resultingGroup = $group->merge($otherGroup);

        $resource = $this->toResource($resultingGroup, $context);

        // Return the resulting group
        return new ResourceResponse($resource, $context);
    }

    /**
     * Called before saveEntity
     */
    protected function beforeSaveEntity(Request $request, Group $group, $isNew = false)
    {
        if ($isNew) {
            $user = $this->getParent($request);

            $groupMember = new GroupMember([
                'role' => GroupMemberRoles::ADMIN
            ]);

            $groupMember->user()->associate($user);
            //$groupMember->group()->associate($user);

            $group->addChildrenToEntity('members', [$groupMember]);
        }

        return $group;
    }
}
