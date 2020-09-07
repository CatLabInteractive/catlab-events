<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Console\Command;

class TestEukles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eukles:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test an eukles event';

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
        $group = Group::first();
        $user = User::first();

        \Eukles::trackEvent(
            \Eukles::createEvent(
                'group.member.join',
                [
                    'group' => $group,
                    'user' => $user
                ]
            )
                ->link($user, 'ismemberof', $group)
        );
    }
}
