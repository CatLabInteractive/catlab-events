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

namespace App\Http\Api\V1\ResourceDefinitions\Events;

use App\Http\Api\V1\ResourceDefinitions\AssetResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\BaseResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\CompetitionResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\LiveStreamResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\PersonResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\SeriesResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\VenueResourceDefinition;
use App\Http\Api\V1\Validators\EventValidator;
use App\Models\Event;
use Illuminate\Support\Str;

/**
 * Class EventResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class EventResourceDefinition extends BaseResourceDefinition
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
            ->visible(false)
            ->datetime();

        $this->field('endDate')
            ->visible(false)
            ->datetime();

        $this->relationship('eventDates', EventDateResourceDefinition::class)
            ->visible()
            ->writeable(true, true)
            ->expanded();

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

        /*
        $this->field('max_tickets')
            ->visible()
            ->writeable(true, true)
            ->number();
        */

        $this->field('fb_event_id')
            ->visible()
            ->writeable(true, true)
            ->string();

        if (config('services.uitdb.oauth_consumer')) {
            $this->field('uitdb_event_id')
                ->visible()
                ->writeable(true, true)
                ->string();
        }

        $this->field('max_points')
            ->visible()
            ->writeable(true, true)
            ->number();

        if (config('services.quizwitz.apiClient')) {
            $this->field('quizwitz_report_id')
                ->visible()
                ->writeable(true, true)
                ->number();
        }

        $this->field('vat_percentage')
            ->visible()
            ->writeable(true, true)
            ->number();

        $this->field('team_size')
            ->visible()
            ->writeable(true, true)
            ->number();

        $this->field('campaign_id')
            ->display('quizwitz_campaign_id')
            ->visible()
            ->writeable(true, true)
            ->number();

        $this->relationship('livestream', LiveStreamResourceDefinition::class)
            ->one()
            ->visible()
            ->expanded();

        $this->relationship('producers', PersonResourceDefinition::class)
            ->many()
            ->visible()
            ->expanded()
            ->linkable();

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

        $this->relationship('technicians', PersonResourceDefinition::class)
            ->many()
            ->visible()
            ->expanded()
            ->linkable();

        $this->field('is_published')
            ->visible(true, true)
            ->writeable(false, true)
            ->bool();

        $this->field('requires_team')
            ->visible()
            ->writeable(false, true)
            ->required()
            ->bool();

        $this->field('include_ticket_fee')
            ->visible()
            ->writeable(false, true)
            ->required()
            ->bool();

        // Scan the emails folder for email templates
        $emailTemplates = [];
        $emailTemplates[] = '';
        $postfix = '.blade.php';
        foreach (\Storage::disk('views')->files('emails/tickets') as $file) {
            if (Str::endsWith($file, $postfix)) {
                $emailTemplates[] = Str::substr($file, 0, -Str::length($postfix));
            }
        }

        $this->field('confirmation_email')
            ->visible(false, true)
            ->writeable(true, true)
            ->enum($emailTemplates);

        $this->addLanguageField();

        $this->field('livestream_url')
            ->visible(false, true)
            ->writeable(true, true)
            ->string();

        $this->validator(new EventValidator());
    }
}
