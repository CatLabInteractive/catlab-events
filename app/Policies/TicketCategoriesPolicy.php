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

use App\Http\Controllers\EventController;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;

/**
 * Class TicketCategoriesPolicy
 * @package App\Policies
 */
class TicketCategoriesPolicy
{
    /**
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function index(User $user, Event $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function create(User $user, Event $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param TicketCategory $ticketCategory
     * @return bool
     */
    public function view(User $user, TicketCategory $ticketCategory)
    {
        return $this->isAdmin($user, $ticketCategory->event);
    }

    /**
     * @param User $user
     * @param TicketCategory $ticketCategory
     * @return bool
     */
    public function destroy(User $user, TicketCategory $ticketCategory)
    {
        return $this->isAdmin($user, $ticketCategory->event);
    }

    /**
     * @param User $user
     * @param TicketCategory $ticketCategory
     * @return bool
     */
    public function edit(User $user, TicketCategory $ticketCategory)
    {
        return $this->isAdmin($user, $ticketCategory->event);
    }

    /**
     * @param User $user
     * @param TicketCategory $ticketCategory
     * @return bool
     */
    public function buyBeforeStartDate(User $user, TicketCategory $ticketCategory)
    {
        if (EventController::hasValidWaitingListToken($ticketCategory->event)) {
            return true;
        }

        return false;
        //return $this->isAdmin($user, $ticketCategory->event);
    }

    /**
     * @param User $user
     * @param TicketCategory $ticketCategory
     * @return false
     */
    public function buyWhenSoldOut(User $user, TicketCategory $ticketCategory)
    {
        if (EventController::hasValidWaitingListToken($ticketCategory->event)) {
            return true;
        }

        return false;
    }

    /**
     * @param User $user
     * @param TicketCategory $ticketCategory
     * @return false
     */
    public function buyAfterEndDate(User $user, TicketCategory $ticketCategory)
    {
        return false;
    }

    /**
     * Check if the event is owned by a publisher owned by this user.
     * @param User $user
     * @param Event $event
     * @return bool
     */
    protected function isAdmin(User $user, Event $event)
    {
        /** @var Organisation $organisation */
        $organisation = $event->organisation;
        if (!$organisation) {
            return false;
        }

        return $organisation->isAdmin($user);
    }
}
