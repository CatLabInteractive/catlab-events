<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

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
