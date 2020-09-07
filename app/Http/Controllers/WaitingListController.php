<?php

namespace App\Http\Controllers;

use App\Models\Event;
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
            $goingGroup = null;
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

        if (!$user->pivot->access_token) {
            $token = Str::random(12);
            $user->pivot->access_token = $token;
            $user->pivot->save();
        }

        return view('events/waitingListInvitation', [
            'event' => $event,
            'user' => $user
        ]);
    }
}