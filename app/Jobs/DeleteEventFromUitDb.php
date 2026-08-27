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

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class DeleteEventFromUitDb
 *
 * Removes an event from UiTDatabank. Carries the remote id rather than the
 * Event model since by the time this job runs the local event may already
 * have been (soft or hard) deleted.
 *
 * @package App\Jobs
 */
class DeleteEventFromUitDb implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 60, 300];

    /**
     * @var string
     */
    public $uitdbEventId;

    /**
     * Create a new job instance.
     *
     * @param string $uitdbEventId
     */
    public function __construct(string $uitdbEventId)
    {
        $this->uitdbEventId = $uitdbEventId;
    }

    /**
     * The unique ID of the job, used to prevent multiple delete jobs for the
     * same UiTDatabank event from being queued at the same time.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->uitdbEventId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $eventService = \UitDb::getEventService();

        if (!$eventService) {
            // Client credentials are no longer configured, nothing to do.
            return;
        }

        try {
            $eventService->deleteEvent($this->uitdbEventId);
        } catch (\Throwable $exception) {
            \Log::error('UiTDatabank: failed to delete event ' . $this->uitdbEventId . ' - ' . $exception->getMessage(), [
                'uitdb_event_id' => $this->uitdbEventId,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
