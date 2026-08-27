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

namespace App\Observers;

use App\Jobs\DeleteEventFromUitDb;
use App\Jobs\SyncEventToUitDb;
use App\Models\Event;

/**
 * Class EventObserver
 *
 * Keeps events synchronized with UiTDatabank whenever they are saved or deleted.
 *
 * @package App\Observers
 */
class EventObserver
{
    /**
     * Handle the Event "saved" event (fired after both create and update).
     *
     * @param Event $event
     * @return void
     */
    public function saved(Event $event)
    {
        if (!\UitDb::getEventService()) {
            // Client credentials are not configured, nothing to do.
            return;
        }

        if (!$event->is_published && !$event->uitdb_event_id) {
            // Never published and not yet known to UiTDatabank: nothing to sync.
            return;
        }

        // UitDBEvents::upload() writes the uitdb_event_id back onto the event after
        // creating it remotely, which triggers this observer again. Guard against
        // that infinite loop by skipping dispatch when uitdb_event_id (ignoring the
        // timestamp touched by the save itself) is the only attribute that changed.
        // Note: upload() also saves via saveQuietly(), so no "saved" event is fired
        // at all for that write-back in normal operation. This check remains as a
        // defence in depth for any other code path that only touches uitdb_event_id.
        $changedAttributes = array_values(array_diff(array_keys($event->getChanges()), ['updated_at']));
        if ($changedAttributes === ['uitdb_event_id']) {
            return;
        }

        SyncEventToUitDb::dispatch($event);
    }

    /**
     * Handle the Event "deleted" event.
     *
     * @param Event $event
     * @return void
     */
    public function deleted(Event $event)
    {
        $uitdbEventId = $event->uitdb_event_id;

        if (!$uitdbEventId) {
            return;
        }

        if (!\UitDb::getEventService()) {
            // Client credentials are not configured, nothing to do.
            return;
        }

        // Pass the id rather than the model: by the time the job runs the event
        // row may be gone (hard delete) or soft-deleted, either way we only need
        // the remote identifier to issue the delete.
        DeleteEventFromUitDb::dispatch($uitdbEventId);
    }
}
