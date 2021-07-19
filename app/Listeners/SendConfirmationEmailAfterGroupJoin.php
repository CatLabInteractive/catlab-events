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

use App\Events\GroupMemberJoined;
use App\Models\Order;

/**
 * Class SendConfirmationEmailAfterGroupJoin
 * @package App\Listeners
 */
class SendConfirmationEmailAfterGroupJoin extends SendEmail
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
     * @param GroupMemberJoined $e
     * @return void
     */
    public function handle(GroupMemberJoined $e)
    {
        $group = $e->group;
        $member = $e->member;

        /** @var Order[] $orders */
        $orders = $group->orders()
            ->leftJoin('events', 'events.id', '=', 'orders.event_id')
            ->where('orders.state', '=', Order::STATE_ACCEPTED)
            ->where('events.endDate', '>', new \DateTime())
            ->get();

        foreach ($orders as $order) {
            $this->sendConfirmationEmail($order, $order->event, $member->user);
        }
    }
}
