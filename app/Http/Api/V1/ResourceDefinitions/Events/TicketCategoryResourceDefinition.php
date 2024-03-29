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

use App\Http\Api\V1\ResourceDefinitions\BaseResourceDefinition;
use App\Http\Api\V1\Validators\TicketCategoryValidator;
use App\Models\TicketCategory;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class TicketCategoryResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class TicketCategoryResourceDefinition extends BaseResourceDefinition
{
    /**
     * TicketCategoryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(TicketCategory::class);

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

        $this->field('price')
            ->visible(true)
            ->writeable(true, true)
            ->required()
            ->number();

        $this->field('max_tickets')
            ->visible(true)
            ->writeable(true, true)
            ->number();

        $this->field('max_players')
            ->visible()
            ->writeable(true, true)
            ->number();

        $this->field('start_date')
            ->visible(true)
            ->writeable(true, true)
            ->datetime();

        $this->field('end_date')
            ->visible(true)
            ->writeable(true, true)
            ->datetime();

        $this->relationship('eventDates', EventDateResourceDefinition::class)
            ->many()
            ->linkable(true, true)
            ->visible()
            ->expanded()
            ->required();

        $this->validator(new TicketCategoryValidator());
    }
}
