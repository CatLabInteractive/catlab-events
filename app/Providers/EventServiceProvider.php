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

namespace App\Providers;

use App\Events\GroupMemberJoined;
use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use App\Events\PreparingOrder;
use App\Listeners\EuklesEventSubscriber;
use App\Listeners\SendCancelConfirmation;
use App\Listeners\SendConfirmationEmail;
use App\Listeners\SendConfirmationEmailAfterGroupJoin;
use CatLab\Accounts\Client\SocialiteProvider\CatLabExtendSocialite;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Class EventServiceProvider
 * @package App\Providers
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        \SocialiteProviders\Manager\SocialiteWasCalled::class => [
            CatLabExtendSocialite::class
        ],

        OrderConfirmed::class => [
            SendConfirmationEmail::class
        ],

        OrderCancelled::class => [
            SendCancelConfirmation::class
        ],

        GroupMemberJoined::class => [
            SendConfirmationEmailAfterGroupJoin::class
        ],

        PreparingOrder::class => [

        ]
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        EuklesEventSubscriber::class
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
