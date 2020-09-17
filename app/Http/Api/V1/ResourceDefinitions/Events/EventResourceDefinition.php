<?php

namespace App\Http\Api\V1\ResourceDefinitions\Events;

use App\Http\Api\V1\ResourceDefinitions\AssetResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\CompetitionResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\PersonResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\SeriesResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\VenueResourceDefinition;
use App\Models\Event;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class EventResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class EventResourceDefinition extends ResourceDefinition
{
    /**
     * StoryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(Event::class);

        // Identifier
        $this->identifier('id');

        // Name
        $this->field('name')
            ->visible(true)
            ->filterable()
            ->writeable(true, true)
            ->required()
            ->string()
            ->min(3);

        // Name
        $this->field('work_title')
            ->visible()
            ->writeable(true, true)
            ->string();

        // url
        $this->field('event_url')
            ->visible()
            ->filterable()
            ->writeable(true, true)
            ->string();

        // Name
        $this->field('description')
            ->visible()
            ->filterable()
            ->writeable(true, true)
            ->required()
            ->string()
            ->min(3)
        ;

        $this->field('registration')
            ->visible(true, true)
            ->writeable(true, true)
            ->enum([
                Event::REGISTRATION_CLOSED,
                Event::REGISTRATION_OPEN,
                Event::REGISTRATION_FULL
            ]);

        // Start date
        $this->field('startDate')
            ->visible(true)
            ->writeable(true, true)
            ->required()
            ->datetime();

        $this->field('endDate')
            ->visible(true)
            ->writeable(true, true)
            ->required()
            ->datetime();

        $this->field('doorsDate')
            ->visible(true)
            ->writeable(true, true)
            ->datetime();

        $this->relationship('ticketCategories', TicketCategoryResourceDefinition::class)
            ->visible()
            ->writeable(true, true)
            ->expanded();

        $this->relationship('venue', VenueResourceDefinition::class)
            ->one()
            ->visible()
            ->linkable(true, true)
            ->expanded();

        $this->relationship('competition', CompetitionResourceDefinition::class)
            ->one()
            ->visible()
            ->linkable(true, true)
            ->expanded();

        $this->relationship('series', SeriesResourceDefinition::class)
            ->one()
            ->visible()
            ->linkable(true, true)
            ->expanded();

        $this->relationship('logo', AssetResourceDefinition::class)
            ->one()
            ->visible()
            ->expanded()
            ->linkable();

        $this->relationship('poster', AssetResourceDefinition::class)
            ->one()
            ->visible()
            ->expanded()
            ->linkable();

        $this->field('max_tickets')
            ->visible()
            ->writeable(true, true)
            ->number();

        $this->field('fb_event_id')
            ->visible()
            ->writeable(true, true)
            ->string();

        $this->field('uitdb_event_id')
            ->visible()
            ->writeable(true, true)
            ->string();

        $this->field('max_points')
            ->visible()
            ->writeable(true, true)
            ->number();

        $this->field('quizwitz_report_id')
            ->visible()
            ->writeable(true, true)
            ->number();

        $this->field('include_ticket_fee')
            ->visible()
            ->writeable(true, true)
            ->required()
            ->bool();

        $this->field('vat_percentage')
            ->visible()
            ->writeable(true, true)
            ->required()
            ->number();

        $this->field('team_size')
            ->visible()
            ->writeable(true, true)
            ->number();

        $this->field('livestream_url')
            ->visible()
            ->writeable(true, true)
            ->string();

        $this->relationship('authors', PersonResourceDefinition::class)
            ->many()
            ->visible()
            ->expanded()
            ->linkable();

        $this->relationship('presenters', PersonResourceDefinition::class)
            ->many()
            ->visible()
            ->expanded()
            ->linkable();

        $this->relationship('musicians', PersonResourceDefinition::class)
            ->many()
            ->visible()
            ->expanded()
            ->linkable();

        $this->field('is_published')
            ->visible(true, true)
            ->writeable(true, true)
            ->bool();
    }
}
