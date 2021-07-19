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

use App\Events\OrderCancelled;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Order;
use App\Models\User;
use CatLab\Accounts\Client\ApiClient;

class SendCancelConfirmation extends SendEmail
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
    public function handle(OrderCancelled $event)
    {
        // Only trigger event when the order was previously 'confirmed'.
        if (!$event->wasConfirmed) {
            return;
        }

        $order = $event->order;

        // Send email
        if ($order->group) {
            foreach ($order->group->members as $member) {
                $this->sendCancellationEmail($order, $member->user);
            }
        } else {
            $this->sendCancellationEmail($order, $order->user);
        }
    }
}
