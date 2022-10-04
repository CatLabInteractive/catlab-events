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

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Class WaitingListController
 * @package App\Http\Controllers
 */
class WaitingListController
{
    /**
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function waitingList($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        if (!$event->hasSaleStarted()) {
            return $this->notifyList($event);
        }

        $waitingListItem = false;
        $goingGroup = null;
        $adminLink = null;

        $user = \Auth::user();
        if ($user) {

            // Check for group
            foreach ($user->groups as $group) {
                if (
                    $event
                        ->orders()
                        ->accepted()
                        ->where('group_id', '=', $group->id)
                        ->first()
                ) {
                    $goingGroup = $group;
                    break;
                }
            }

            // Check if we're on the waiting list already
            $waitingListItem = $event
                ->waitingList()
                ->where('user_id', '=', $user->id)
                ->withPivot('created_at')
                ->first();

            if ($user->isAdmin()) {
                $adminLink = action('WaitingListController@viewList', [ $event->id ]);
            }
        }

        return view('events/waitingList', [
            'event' => $event,
            'waitingListItem' => $waitingListItem,
            'goingGroup' => $goingGroup,
            'adminLink' => $adminLink
        ]);
    }

    /**
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function addToWaitinglist($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $user = \Auth::user();

        // maybe we are already subscribed?
        if (
            $event
                ->waitingList()
                ->where('user_id', '=', $user->id)
                ->count() === 0
        ) {
            $event->registerToWaitingList($user);
        }

        return $this->waitingList($eventId);
    }

    /**
     * @param Event $event
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    protected function notifyList(Event $event)
    {
        $waitingListItem = false;
        $goingGroup = null;

        $user = \Auth::user();
        $adminLink = null;

        if ($user) {

            // Check for group
            foreach ($user->groups as $group) {
                if (
                    $event
                        ->orders()
                        ->accepted()
                        ->where('group_id', '=', $group->id)
                        ->first()
                ) {
                    $goingGroup = $group;
                    break;
                }
            }

            // Check if we're on the waiting list already
            $waitingListItem = $event
                ->waitingList()
                ->where('user_id', '=', $user->id)
                ->withPivot('created_at')
                ->first();

            if ($user->isAdmin()) {
                $adminLink = action('WaitingListController@viewList', [ $event->id ]);
            }
        }

        $startDate = $event->getSaleStartDate();
        /*
        if (!$startDate) {
            abort(400, 'No ticket information found for this event.');
        }
        */

        return view('events/notifyMeList', [
            'event' => $event,
            'waitingListItem' => $waitingListItem,
            'goingGroup' => $goingGroup,
            'startDate' => $startDate,
            'adminLink' => $adminLink
        ]);
    }

    /**
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function viewList($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $waitingList = [];
        $index = 0;

        $users = $event
            ->waitingList()
            ->withPivot('access_token')
            ->get();

        foreach ($users as $user) {
            // Check for group
            $goingGroup = $this->getAttendingGroup($event, $user);

            $waitingList[] = [
                'index' => ++ $index,
                'user' => $user,
                'group' => $goingGroup
            ];
        }

        return view('events/waitingListView', [
            'event' => $event,
            'waitingList' => $waitingList
        ]);
    }

    /**
     * @param $eventId
     * @param $userId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function generateAccessToken($eventId, $userId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $user = $event->waitingList()
            ->where('user_id', '=', $userId)
            ->withPivot('created_at', 'access_token')
            ->first();

        if ($user === null) {
            return redirect('WaitingListController@notifyList', [ $event ]);
        }

        $this->generatePivotAccessToken($user);
        return view('events/waitingListInvitation', [
            'event' => $event,
            'user' => $user,
            'url' => $this->getAccessTokenUrl($event, $user)
        ]);
    }

    /**
     * @param $eventId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function massGenerateAccessTokens($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $waitingList = [];
        $index = 0;

        $users = $event
            ->waitingList()
            ->withPivot('access_token')
            ->get();

        foreach ($users as $user) {
            if (!$this->getAttendingGroup($event, $user)) {
                $this->generatePivotAccessToken($user);

                $waitingList[] = [
                    'index' => ++$index,
                    'user' => $user,
                    'url' => $this->getAccessTokenUrl($event, $user)
                ];
            }
        }

        return view('events.waitingListMassGeneration', [
            'event' => $event,
            'waitingList' => $waitingList
        ]);
    }

    /**
     * @param Event $event
     * @param User $user
     * @return Order|null
     */
    protected function getAttendingGroup(Event $event, User $user)
    {
        foreach ($user->groups as $group) {
            if (
                $event
                    ->orders()
                    ->accepted()
                    ->where('group_id', '=', $group->id)
                    ->first()
            ) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @param User $user
     * @return void
     */
    private function generatePivotAccessToken(User $user)
    {
        if (!$user->pivot->access_token) {
            $token = Str::random(12);
            $user->pivot->access_token = $token;
            $user->pivot->save();
        }
    }

    /**
     * @param Event $event
     * @param $user
     * @return string
     */
    private function getAccessTokenUrl(Event $event, $user)
    {
        return action('EventController@selectTicketCategory', [ $event->id, 'wt' => $user->pivot->access_token ]);
    }
}
