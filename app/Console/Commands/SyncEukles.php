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

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Class SyncEukles
 * @package App\Console\Commands
 */
class SyncEukles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eukles:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize all eukles objects.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        $events = Event::all();

        foreach ($events as $event) {
            $this->syncEukles($event);
        }
    }

    /**
     * @param Event $event
     * @throws \Exception
     */
    protected function syncEukles(Event $event)
    {
        $this->output->writeln('Synchronizing ' . $event->name);
        \Eukles::syncRelationship($event, 'attends', $event->attendees()->get());
    }
}
