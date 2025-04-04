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

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\Groups\GroupMemberResourceDefinition;
use App\Models\Group;
use App\Models\GroupMember;
use CatLab\Accounts\Client\ApiClient;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Class GroupMemberController
 * @package App\Http\Api\V1\Controllers
 */
class GroupMemberController extends ResourceController
{
    const RESOURCE_DEFINITION = GroupMemberResourceDefinition::class;
    const RESOURCE_ID = 'member';
    const PARENT_RESOURCE_ID = 'group';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController;

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->childResource(
            static::RESOURCE_DEFINITION,
            'groups/{' . self::PARENT_RESOURCE_ID . '}/members',
            'groups',
            'Groups\GroupMemberController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        )->tag('groups');
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Group $group */
        $group = $this->getParent($request);
        return $group->members();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $parentId = $request->route(self::PARENT_RESOURCE_ID);
        return Group::findOrFail($parentId);
    }


    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    /**
     * Called before saveEntity
     */
    protected function beforeSaveEntity(Request $request, GroupMember $member)
    {
        $parent = $this->getParent($request);
        $member->group()->associate($parent);

        $member->token = Str::random(12);

        return $member;
    }

    /**
     * Called before saveEntity
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @return Model
     */
    protected function afterSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity)
    {
        if ($entity->email && filter_var( $entity->email, FILTER_VALIDATE_EMAIL )) {
            $apiClient = new ApiClient(\Auth::getUser());

            $attributes = [
                'from' => \Auth::getUser(),
                'group' => $entity->group,
                'invitation' => $entity,
                'inviteUrl' => action('GroupController@viewInvitation', [ $entity->id, $entity->token ])
            ];

            $view = \View::make('emails/groups/invite', $attributes);

            $response = $apiClient->sendEmail(
                'Uitnodiging ' . $entity->group->name,
                $view->render(),
                $entity->email
            );

        }

        return $entity;
    }
}
