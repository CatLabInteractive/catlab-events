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

namespace App\Http\Api\V1\ResourceDefinitions\Groups;

use App\Http\Api\V1\ResourceDefinitions\ScoreResourceDefinition;
use App\Http\Api\V1\Validators\GroupValidator;
use App\Models\Group;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class GroupResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class GroupResourceDefinition extends ResourceDefinition
{
    /**
     * StoryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(Group::class);

        // Identifier
        $this->identifier('id');

        // Name
        $this->field('name')
            ->visible(true)
            ->filterable()
            ->writeable(true, true)
            ->required()
            ->string()
            ->min(3)
            ->max(50)
        ;

        $this->relationship('members', GroupMemberResourceDefinition::class)
            ->writeable(true, true)
            ->visible()
            ->expanded()
            ->url('api/v1/groups/{model.id}/members')
        ;

        $this->relationship('scores', ScoreResourceDefinition::class)
            ->visible()
            ->expanded();

        $this->validator(new GroupValidator());
    }
}
