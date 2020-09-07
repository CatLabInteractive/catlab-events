<?php

namespace App\Http\Api\V1\Controllers\Groups;

use App\Enum\GroupMemberRoles;
use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\Groups\GroupResourceDefinition;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\ResourceResponse;
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
            $groupMember->group()->associate($user);

            $group->addChildrenToEntity('members', [$groupMember]);
        }

        return $group;
    }
}
