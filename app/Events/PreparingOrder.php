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

namespace App\Events;

use App\Models\Event;
use App\Models\Group;
use App\Models\Order;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

/**
 * Class OrderInitialized
 * @package App\Events
 */
class PreparingOrder
{
    use SerializesModels;

    /**
     * @var User
     */
    public $actor;

    /**
     * @var Group
     */
    public $group;

    /**
     * @var Event
     */
    public $event;

    /**
     * @var TicketCategory
     */
    public $ticketCategory;

    /**
     * @var array
     */
    public $session;

    public function __construct(User $actor, Event $event, $session, TicketCategory $ticketCategory, Group $group = null)
    {
        $this->actor = $actor;
        $this->group = $group;
        $this->event = $event;
        $this->session = $session;
        $this->ticketCategory = $ticketCategory;
    }
}
