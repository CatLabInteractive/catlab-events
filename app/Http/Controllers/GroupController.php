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

namespace App\Http\Controllers;

use App\Events\GroupMemberJoined;
use App\Exceptions\Groups\GroupMergeEventOverlapException;
use App\Http\Api\V1\ResourceDefinitions\Groups\GroupMemberResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\ScoreResourceDefinition;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Http\Request;

/**
 * Class GroupController
 * @package App\Http\Controllers
 */
class GroupController extends Controller implements FrontCrudControllerContract
{
    use FrontCrudController;

    /**
     * GroupController constructor.
     */
    public function __construct()
    {
        $this->setLayout('layouts.front');
        $this->setChildController(GroupMemberResourceDefinition::class, GroupMemberController::class);
        $this->setChildController(ScoreResourceDefinition::class, ScoreController::class);
    }

    /**
     * @return \App\Http\Api\V1\Controllers\Groups\GroupController
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\Groups\GroupController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\Groups\GroupController::RESOURCE_ID;
    }

    /**
     * @param string $action
     * @param string $view
     * @param array $properties
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    protected function createFormView($action, $view, $properties)
    {
        return view($view, array_merge($this->getAdditionalFormViewProperties(), $properties));
    }

    /**
     * @return array
     */
    protected function getAdditionalFormViewProperties()
    {
        if (!request()->query('event')) {
            return [];
        }
        return [
            'event' => Event::find(request()->query('event'))
        ];
    }

    /**
     * @param $invitationId
     * @param $token
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function viewInvitation($invitationId, $token)
    {
        /** @var GroupMember $invitation */
        $invitation = GroupMember::find($invitationId);
        if (!$invitation) {
            abort(403, 'Deze uitnodiging is niet geldig.');
        }

        if ($invitation->token !== $token) {
            return redirect(action('GroupController@show', [ $invitation->group->id ]));
        }

        return view('groups/invitation', [
            'invitation' => $invitation
        ]);
    }

    /**
     * @param $invitationId
     * @param $token
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function acceptInvitation($invitationId, $token)
    {
        /** @var GroupMember $invitation */
        $invitation = GroupMember::findOrFail($invitationId);
        if ($invitation->token !== $token) {
            return redirect(action('GroupController@show', [ $invitation->group->id ]));
        }

        // Are we already member?

        /** @var Group $group */
        $group = $invitation->group;
        $user = \Auth::getUser();

        if ($group->isMember($user)) {
            $invitation->delete();
            return redirect(action('GroupController@show', [ $invitation->group->id ]));
        }

        $invitation->user()->associate(\Auth::getUser());
        $invitation->token = null;
        $invitation->save();

        event(new GroupMemberJoined($group, $invitation));

        return redirect(action('GroupController@show', [ $invitation->group->id ]));
    }

    /**
     * @param $groupId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function mergeGroup($groupId)
    {
        $group = Group::findOrFail($groupId);
        $this->authorize('merge', $group);

        $user = \Auth::getUser();

        if ($user->can('mergeAnyGroup', Group::class)) {
            $otherGroups = Group::query()
                ->where('groups.id', '!=', $group->id)
                ->orderBy('name')
                ->get()
                ->filter(function(Group $group) use ($user) {
                    return $user->can('merge', $group);
                });
        } else {
            $otherGroups = $user->groups()
                ->where('groups.id', '!=', $group->id)
                ->orderBy('name')
                ->get()
                ->filter(function(Group $group) use ($user) {
                    return $user->can('merge', $group);
                });
        }

        $otherGroups = $otherGroups->mapWithKeys(function($v) {
            return [ $v->id => $v->name ];
        });

        return view('groups/merge', [
            'group' => $group,
            'otherGroups' => $otherGroups
        ]);
    }

    /**
     * @param Request $request
     * @param $groupId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function confirmMergeGroup(Request $request, $groupId)
    {
        /** @var Group $group */
        $group = Group::findOrFail($groupId);
        $this->authorize('merge', $group);

        /** @var Group $otherGroup */
        $otherGroup = Group::findOrFail($request->input('id'));

        try {
            $group->checkMerge($otherGroup);
        } catch (GroupMergeEventOverlapException $e) {
            return redirect(action( 'GroupController@mergeGroup', $group->id) )
                ->with('message', "Deze teams kunnen niet samengevoegd worden. Ze hebben beide deelgenemen aan " . $e->event->name . ".")
                ->withInput();
        }

        return view('groups/confirmMerge', [
            'group' => $group,
            'otherGroup' => $otherGroup
        ]);
    }

    /**
     * @param Request $request
     * @param $groupId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function processMergeGroup(Request $request, $groupId)
    {
        /** @var Group $group */
        $group = Group::findOrFail($groupId);

        /** @var ResourceResponse $response */
        $response = $this->callApiMethod('merge', $request, [ $group->id ]);
        if ($response instanceof ResourceResponse) {
            return redirect(action('GroupController@show', [ $response->getResource()->getSource()->id ]));
        } else {
            return redirect(action('GroupController@index'));
        }
    }

    /**
     * Get any parameters that might be required by the controller.
     * @param $method
     * @return array
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:

                return [
                    'user' => \Request::user()->id
                ];

                break;
        }

        return [];
    }

    /**
     * @param $action
     * @return string
     */
    protected function getView($action)
    {
        switch ($action) {
            case Action::VIEW:
                return 'groups.view';

            case Action::INDEX:
                return 'groups.index';

            case 'form':
                return 'groups.create';

            default:
                return 'charonfrontend::crud.' . $action;
        }
    }
}
