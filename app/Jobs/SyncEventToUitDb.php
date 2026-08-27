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

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class SyncEventToUitDb
 *
 * Uploads (creates or updates) an event in UiTDatabank.
 *
 * @package App\Jobs
 */
class SyncEventToUitDb implements ShouldQueue, ShouldBeUnique
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
     * @var Event
     */
    public $event;

    /**
     * Create a new job instance.
     *
     * @param Event $event
     */
    public function __construct(Event $event)
    {
        $this->event = $event;

        // Only dispatch once the surrounding database transaction (if any) has
        // been committed, so the worker never loads a stale or missing row.
        $this->afterCommit();
    }

    /**
     * The unique ID of the job, used to prevent multiple sync jobs for the
     * same event from being queued at the same time.
     *
     * @return string
     */
    public function uniqueId()
    {
        return (string) $this->event->getKey();
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
            $eventService->upload($this->event);
        } catch (\Throwable $exception) {
            \Log::error('UiTDatabank: failed to sync event ' . $this->event->getKey() . ' - ' . $exception->getMessage(), [
                'event_id' => $this->event->getKey(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
