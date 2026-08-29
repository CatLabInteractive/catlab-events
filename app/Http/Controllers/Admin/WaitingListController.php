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

namespace App\Http\Controllers\Admin;

use App\Events\InvitedFromWaitingList;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Class WaitingListController
 *
 * Admin side of the waiting list: see who is waiting, and invite them to buy
 * a ticket that came free. Inviting generates a personal access token and
 * mails it out; the mail goes through the InvitedFromWaitingList event so
 * that eukles sees the invitation too.
 *
 * @package App\Http\Controllers\Admin
 */
class WaitingListController extends Controller
{
    /**
     * Show everyone on the waiting list and their invitation state.
     *
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index($eventId)
    {
        $event = $this->getEvent($eventId);

        $waitingList = [];
        $index = 0;

        foreach ($this->getWaitingListEntries($event) as $user) {
            $waitingList[] = [
                'index' => ++ $index,
                'user' => $user,
                'group' => $this->getAttendingGroup($event, $user),
                'attending' => $this->isAttending($event, $user)
            ];
        }

        return view('admin.waitinglist.index', [
            'event' => $event,
            'waitingList' => $waitingList
        ]);
    }

    /**
     * Preview the invitation mail for a single person. Sends nothing.
     *
     * @param $eventId
     * @param $userId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function invite($eventId, $userId)
    {
        $event = $this->getEvent($eventId);
        $user = $this->getWaitingListEntry($event, $userId);

        if ($user === null) {
            return redirect(action('Admin\WaitingListController@index', [ $event->id ]))
                ->with('message', 'Deze gebruiker staat niet op de wachtlijst.');
        }

        // Render against the token the invitation will use, without persisting
        // one: previewing must not hand out access to a ticket.
        $url = $this->getAccessTokenUrl($event, $user->pivot->access_token ?: 'VOORBEELD');

        return view('admin.waitinglist.invite', [
            'event' => $event,
            'user' => $user,
            'url' => $url,
            'body' => $this->renderInvitationMail($event, $user, $url),
            'subject' => 'Wachtlijst ' . $event->name
        ]);
    }

    /**
     * Generate the access token and mail the invitation.
     *
     * @param $eventId
     * @param $userId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendInvite($eventId, $userId)
    {
        $event = $this->getEvent($eventId);
        $user = $this->getWaitingListEntry($event, $userId);

        $back = redirect(action('Admin\WaitingListController@index', [ $event->id ]));

        if ($user === null) {
            return $back->with('message', 'Deze gebruiker staat niet op de wachtlijst.');
        }

        if ($this->sendInvitation($event, $user)) {
            return $back->with('message', 'Uitnodiging verstuurd naar ' . $user->email . '.');
        }

        return $back->with(
            'message',
            'De uitnodiging naar ' . $user->email . ' kon niet verstuurd worden. Probeer het later opnieuw.'
        );
    }

    /**
     * Preview a mass invite: how many tickets are free, who would be mailed.
     *
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function massInvite($eventId)
    {
        $event = $this->getEvent($eventId);

        $eligible = $this->getEligibleEntries($event);
        $available = $this->countAvailableTickets($event);
        $outstanding = $this->countOutstandingInvitations($event);
        $proposed = $this->getProposedInviteCount($event, $eligible);

        return view('admin.waitinglist.massInvite', [
            'event' => $event,
            'eligible' => $eligible,
            'available' => $available,
            'outstanding' => $outstanding,
            'proposed' => $proposed,
            'attending' => $this->countAttending($event),
            'hasFiniteTickets' => $event->hasFiniteTickets()
        ]);
    }

    /**
     * Mail the first N people on the waiting list that can still be invited.
     *
     * @param Request $request
     * @param $eventId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendMassInvite(Request $request, $eventId)
    {
        $event = $this->getEvent($eventId);

        $this->validate($request, [
            'amount' => 'required|integer|min:1'
        ]);

        $eligible = $this->getEligibleEntries($event);

        // Recompute against live numbers: the form may have been sitting open
        // while tickets sold, and we must not invite past what is free.
        $amount = min(
            (int) $request->input('amount'),
            $this->getProposedInviteCount($event, $eligible)
        );

        if ($amount < 1) {
            return redirect(action('Admin\WaitingListController@index', [ $event->id ]))
                ->with('message', 'Er zijn geen tickets meer vrij om uit te nodigen.');
        }

        $sent = 0;
        $failed = 0;

        foreach ($eligible->take($amount) as $user) {
            if ($this->sendInvitation($event, $user)) {
                $sent ++;
            } else {
                $failed ++;
            }
        }

        $message = $sent . ' ' . ($sent === 1 ? 'uitnodiging' : 'uitnodigingen') . ' verstuurd.';
        if ($failed > 0) {
            $message .= ' ' . $failed . ' mislukt, probeer het later opnieuw.';
        }

        return redirect(action('Admin\WaitingListController@index', [ $event->id ]))
            ->with('message', $message);
    }

    /**
     * Generate a token if there is none yet and fire the invitation event.
     *
     * @param Event $event
     * @param User $user
     * @return bool whether the invitation mail went out
     */
    private function sendInvitation(Event $event, User $user)
    {
        $this->generatePivotAccessToken($user);

        event(new InvitedFromWaitingList(
            $event,
            $user,
            $this->getAccessTokenUrl($event, $user->pivot->access_token)
        ));

        // The listener stamps invitation_sent_at only when accounts accepted
        // the mail, so a fresh read tells us whether it actually went out.
        return $this->getWaitingListEntry($event, $user->id)->pivot->invitation_sent_at !== null;
    }

    /**
     * Everyone on the waiting list, longest waiting first.
     *
     * @param Event $event
     * @return Collection|User[]
     */
    private function getWaitingListEntries(Event $event)
    {
        return $event
            ->waitingList()
            ->withPivot('access_token', 'invitation_sent_at')
            ->orderBy('event_waitinglist.created_at')
            ->get();
    }

    /**
     * @param Event $event
     * @param $userId
     * @return User|null
     */
    private function getWaitingListEntry(Event $event, $userId)
    {
        return $event
            ->waitingList()
            ->withPivot('access_token', 'invitation_sent_at')
            ->where('user_id', '=', $userId)
            ->first();
    }

    /**
     * People we can still invite: not registered yet, no invitation out yet.
     *
     * @param Event $event
     * @return Collection|User[]
     */
    private function getEligibleEntries(Event $event)
    {
        return $this->getWaitingListEntries($event)
            ->filter(function(User $user) use ($event) {
                return !$user->pivot->access_token
                    && !$this->isAttending($event, $user);
            })
            ->values();
    }

    /**
     * Invitations that were handed out but not yet turned into a registration:
     * each one can still claim a free ticket, so it counts against capacity.
     *
     * @param Event $event
     * @return int
     */
    private function countOutstandingInvitations(Event $event)
    {
        return $this->getWaitingListEntries($event)
            ->filter(function(User $user) use ($event) {
                return $user->pivot->access_token
                    && !$this->isAttending($event, $user);
            })
            ->count();
    }

    /**
     * @param Event $event
     * @return int
     */
    private function countAttending(Event $event)
    {
        return $this->getWaitingListEntries($event)
            ->filter(function(User $user) use ($event) {
                return $this->isAttending($event, $user);
            })
            ->count();
    }

    /**
     * Free tickets, pending orders counted as taken. Null when the event has
     * no ticket limit at all.
     *
     * @param Event $event
     * @return int|null
     */
    private function countAvailableTickets(Event $event)
    {
        if (!$event->hasFiniteTickets()) {
            // countAvailableTickets() sums per event date and treats an
            // unlimited date as 0, which would read as "sold out" here.
            return null;
        }

        return $event->countAvailableTickets(true);
    }

    /**
     * How many invitations we suggest sending: the free tickets, minus the
     * invitations that are already out there waiting to be used.
     *
     * @param Event $event
     * @param Collection $eligible
     * @return int
     */
    private function getProposedInviteCount(Event $event, Collection $eligible)
    {
        $available = $this->countAvailableTickets($event);

        if ($available === null) {
            return $eligible->count();
        }

        return max(0, min(
            $available - $this->countOutstandingInvitations($event),
            $eligible->count()
        ));
    }

    /**
     * @param Event $event
     * @param User $user
     * @param string $url
     * @return string
     */
    private function renderInvitationMail(Event $event, User $user, string $url)
    {
        return \View::make('emails.tickets.waitingListInvitation', [
            'event' => $event,
            'user' => $user,
            'url' => $url
        ])->render();
    }

    /**
     * @param $eventId
     * @return Event
     */
    private function getEvent($eventId)
    {
        return Event::findOrFail($eventId);
    }

    /**
     * Whether this person already got a ticket, either on their own or
     * through one of their teams. Team events are the common case, but an
     * event with requires_team off has orders with no group at all.
     *
     * @param Event $event
     * @param User $user
     * @return bool
     */
    private function isAttending(Event $event, User $user)
    {
        $ownOrder = $event
            ->orders()
            ->accepted()
            ->where('user_id', '=', $user->id)
            ->first();

        if ($ownOrder) {
            return true;
        }

        return $this->getAttendingGroup($event, $user) !== null;
    }

    /**
     * The team this person is attending with, if any. Only used to link to
     * the team from the overview; use isAttending() to decide whether someone
     * still needs an invitation.
     *
     * @param Event $event
     * @param User $user
     * @return Group|null
     */
    private function getAttendingGroup(Event $event, User $user)
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
            $user->pivot->access_token = Str::random(12);
            $user->pivot->save();
        }
    }

    /**
     * @param Event $event
     * @param string $accessToken
     * @return string
     */
    private function getAccessTokenUrl(Event $event, string $accessToken)
    {
        return action('EventController@selectTicketCategory', [ $event->id, 'wt' => $accessToken ]);
    }
}
