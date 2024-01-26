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

use App\Models\Competition;
use App\Models\Event;
use App\Models\EventDate;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\LiveStream;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Person;
use App\Models\Series;
use App\Models\TicketCategory;
use App\Models\Venue;
use App\Policies\CompetitionPolicy;
use App\Policies\EventDatePolicy;
use App\Policies\EventPolicy;
use App\Policies\GroupMemberPolicy;
use App\Policies\GroupPolicy;
use App\Policies\LiveStreamPolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrganisationPolicy;
use App\Policies\PersonPolicy;
use App\Policies\SeriesPolicy;
use App\Policies\TicketCategoriesPolicy;
use App\Policies\VenuePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Class AuthServiceProvider
 * @package App\Providers
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [

        Organisation::class     => OrganisationPolicy::class,
        Event::class            => EventPolicy::class,
        Group::class            => GroupPolicy::class,
        GroupMember::class      => GroupMemberPolicy::class,
        TicketCategory::class   => TicketCategoriesPolicy::class,
        Venue::class            => VenuePolicy::class,
        Competition::class      => CompetitionPolicy::class,
        Series::class           => SeriesPolicy::class,
        Person::class           => PersonPolicy::class,
        LiveStream::class       => LiveStreamPolicy::class,
        EventDate::class        => EventDatePolicy::class,
        Order::class            => OrderPolicy::class,

    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
