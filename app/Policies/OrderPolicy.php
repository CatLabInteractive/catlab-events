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
     * @param User $user
     * @param Order $order
     * @return bool
     */
    public function view(User $user, Order $order)
    {
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
