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

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use CatLab\Accounts\Client\ApiClient;
use LogicException;

/**
 * Class SendConfirmationEmail
 * @package App\Listeners
 */
class SendConfirmationEmail extends SendEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(OrderConfirmed $event)
    {
        $order = $event->order;

        // Send email
        try {
            if ($order->group && $order->group->hasMembers()) {
                foreach ($order->group->members as $member) {
                    if ($member->user) {
                        $this->sendConfirmationEmail($order, $order->event, $member->user);
                    }
                }
            } elseif ($order->group && $order->user) {
                // A guest checkout's team has no members until its buyer logs
                // in and claims it: mail the buyer, with the claim link.
                $this->sendConfirmationEmail(
                    $order,
                    $order->event,
                    $order->user,
                    null,
                    \View::make('emails.tickets.claimTeam', [
                        'order' => $order,
                        'group' => $order->group,
                        'claimUrl' => action('OrderController@claim', [ $order->id ])
                    ])->render()
                );
            } elseif ($order->user) {
                $this->sendConfirmationEmail($order, $order->event, $order->user);
            }

            // Guest registrations have no account to mail; the admin may have
            // entered an address for the guest instead.
            if ($order->is_guest && $order->guest_email) {
                $this->sendConfirmationEmail($order, $order->event, null, $order->guest_email);
            }
        } catch (LogicException $e) {
            \Log::error($e->getMessage());
        }
    }
}
