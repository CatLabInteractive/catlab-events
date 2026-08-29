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

use App\Events\InvitedFromWaitingList;
use Carbon\Carbon;

/**
 * Class SendWaitingListInvitation
 * @package App\Listeners
 */
class SendWaitingListInvitation extends SendEmail
{
    /**
     * Handle the event.
     *
     * @param InvitedFromWaitingList $e
     * @return void
     */
    public function handle(InvitedFromWaitingList $e)
    {
        $sent = $this->sendWaitingListInvitationEmail($e->event, $e->user, $e->url);

        if (!$sent) {
            // invitation_sent_at stays null, which is what the admin panel
            // reads back to report which invitations did not make it out.
            return;
        }

        $e->event
            ->waitingList()
            ->updateExistingPivot($e->user->id, [ 'invitation_sent_at' => Carbon::now() ]);
    }
}
