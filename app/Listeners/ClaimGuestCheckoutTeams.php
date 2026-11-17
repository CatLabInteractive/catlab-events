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

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Class ClaimGuestCheckoutTeams
 *
 * A guest checkout's team has no members. Logging in through accounts proves
 * the buyer owns the address the order was filed under, so on login the
 * buyer becomes admin of the memberless teams of their paid guest orders.
 *
 * @package App\Listeners
 */
class ClaimGuestCheckoutTeams
{
    public function handle(Login $event)
    {
        $user = $event->user;
        if (!$user instanceof User) {
            return;
        }

        try {
            $orders = Order::query()
                ->where('user_id', '=', $user->id)
                ->where('is_guest_checkout', '=', true)
                ->where('state', '=', Order::STATE_ACCEPTED)
                ->whereNotNull('group_id')
                ->with('group')
                ->get();

            foreach ($orders as $order) {
                if ($order->group && !$order->group->hasMembers()) {
                    $order->group->addAdmin($user);
                }
            }
        } catch (\Throwable $e) {
            // Never block a login over this; the mail's claim link still works.
            \Log::error('Could not attach guest checkout teams on login', [
                'user' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
