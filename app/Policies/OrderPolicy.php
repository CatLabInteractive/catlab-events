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

namespace App\Policies;

use App\Models\Order;
use App\Models\Organisation;
use App\Models\Series;
use App\Models\User;

/**
 * Class OrderPolicy
 * @package App\Policies
 */
class OrderPolicy
{
    /**
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function index(User $user, Organisation $organisation)
    {
        return $organisation->isAdmin($user);
    }

    /**
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function create(User $user, Organisation $organisation)
    {
        return $organisation->isAdmin($user);
    }

    /**
     * Who may read an order: the buyer, a member of the order's group, a
     * global admin, or an admin of the organisation that runs the event.
     * Shared by the public orders/{id} page and the backoffice API; the API
     * resource exposes nothing the buyer does not already see on that page.
     * (Security audit 2026-08-27: orders/{id} was readable by any logged-in
     * user, exposing team, items and the livestream link, fetched with the
     * OWNER's stored accounts token.)
     *
     * @param User $user
     * @param Order $order
     * @return bool
     */
    public function view(User $user, Order $order)
    {
        if ($user->isAdmin() || (int)$order->user_id === (int)$user->id) {
            return true;
        }
        if ($order->group && $order->group->isMember($user)) {
            return true;
        }
        return $this->isAdmin($user, $order);
    }

    /**
     * @param User $user
     * @param Order $order
     * @return bool
     */
    public function destroy(User $user, Order $order)
    {
        return false;
    }

    /**
     * @param User $user
     * @param Order $order
     * @return bool
     */
    public function edit(User $user, Order $order)
    {
        return $this->isAdmin($user, $order);
    }

    /**
     * @param User $user
     * @param Order $order
     * @return bool
     */
    protected function isAdmin(User $user, Order $order)
    {
        return $order->event->organisation->isAdmin($user);
    }
}
