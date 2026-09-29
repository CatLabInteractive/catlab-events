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

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Group;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Class VipRegistrationController
 *
 * Registering guests (famous attendees, sponsors, press) straight from the
 * admin panel, without asking them to create an account first. A VIP
 * registration is a regular accepted order with no user and no accounts
 * order behind it, flagged `is_vip`. On team events it gets a team of its
 * own (with no members), so it shows up in attendee lists and scores like
 * any other team.
 *
 * Admins may register a VIP even when the event is sold out: that is
 * usually the point of a VIP ticket. The seat still counts as sold.
 *
 * @package App\Http\Controllers\Admin
 */
class VipRegistrationController extends Controller
{
    /**
     * The form, plus every VIP registration made for this event so far.
     *
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index($eventId)
    {
        $event = $this->getEventInOrganisation($eventId);

        $orders = $event->orders()
            ->where('is_vip', '=', true)
            ->with([ 'group', 'ticketCategory' ])
            ->orderBy('id')
            ->get();

        return view('admin.vip.index', [
            'event' => $event,
            'ticketCategories' => $event->getTicketCategoriesChronologically(),
            'orders' => $orders,
            'soldOut' => $event->isSoldOut()
        ]);
    }

    /**
     * Register a VIP.
     *
     * @param Request $request
     * @param $eventId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, $eventId)
    {
        $event = $this->getEventInOrganisation($eventId);

        $this->validate($request, [
            'ticketCategory' => 'required|integer',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:2000'
        ]);

        $ticketCategory = $event->ticketCategories()->findOrFail($request->input('ticketCategory'));
        $name = trim($request->input('name'));

        $order = new Order();
        $order->event()->associate($event);
        $order->ticketCategory()->associate($ticketCategory);

        if ($event->doesRequireTeam()) {
            $group = new Group();
            $group->name = $name;
            $group->save();

            $order->group()->associate($group);
        }

        $order->is_vip = true;
        $order->vip_name = $name;
        $order->vip_email = $request->input('email') ?: null;
        $order->vip_notes = $request->input('notes') ?: null;
        $order->save();

        \Log::info('VIP registration created', [
            'order' => $order->id,
            'event' => $event->id,
            'admin' => \Auth::id(),
        ]);

        $back = redirect(action('Admin\VipRegistrationController@index', [ $event->id ]));

        // Confirming fetches the play link and sends the confirmation mail.
        // The registration itself is saved either way, so a failing external
        // service must not read as "nothing happened" (and invite a double).
        try {
            $order->changeState(Order::STATE_ACCEPTED);
        } catch (\Throwable $e) {
            \Log::error('VIP registration saved, but confirming it failed', [
                'order' => $order->id,
                'error' => $e->getMessage()
            ]);

            // changeState() saves the state before any of the side effects run.
            return $back->with(
                'message',
                $name . ' is ingeschreven, maar de speellink of bevestigingsmail kon niet verwerkt worden.'
            );
        }

        return $back->with('message', $name . ' is ingeschreven als VIP.');
    }

    /**
     * Cancel a VIP registration.
     *
     * @param $eventId
     * @param $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancel($eventId, $orderId)
    {
        $event = $this->getEventInOrganisation($eventId);

        /** @var Order $order */
        $order = $event->orders()
            ->where('is_vip', '=', true)
            ->findOrFail($orderId);

        $back = redirect(action('Admin\VipRegistrationController@index', [ $event->id ]));

        if ($order->isCancelled()) {
            return $back->with('message', 'Deze inschrijving was al geannuleerd.');
        }

        $order->changeState(Order::STATE_CANCELLED);

        return $back->with('message', 'De inschrijving van ' . $order->getAttendeeName() . ' is geannuleerd.');
    }

    /**
     * The event, or 404. Scoped to the acting admin's active organisation and
     * an admin role within it, like RefundController::getOrderInOrganisation():
     * `admin` is a global flag.
     *
     * @param $eventId
     * @return Event
     */
    protected function getEventInOrganisation($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $organisation = \Auth::user()->getActiveOrganisation();
        if (!$organisation
            || (int) $event->organisation_id !== (int) $organisation->id
            || !$organisation->isAdmin(\Auth::user())) {
            abort(404);
        }

        return $event;
    }
}
