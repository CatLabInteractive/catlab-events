<?php

namespace App\Providers;

use App\Models\Competition;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\LiveStream;
use App\Models\Organisation;
use App\Models\Person;
use App\Models\Series;
use App\Models\TicketCategory;
use App\Models\Venue;
use App\Policies\CompetitionPolicy;
use App\Policies\EventPolicy;
use App\Policies\GroupMemberPolicy;
use App\Policies\GroupPolicy;
use App\Policies\LiveStreamPolicy;
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
        LiveStream::class       => LiveStreamPolicy::class

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
