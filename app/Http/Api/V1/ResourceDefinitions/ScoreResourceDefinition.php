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

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Http\Api\V1\ResourceDefinitions\Events\EventResourceDefinition;
use App\Models\Score;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class ScoreResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class ScoreResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Score::class);

        $this->identifier('id');

        $this->field('event.name')
            ->visible(true)
            ->display('event')
        ;

        $this->field('score')
            ->visible(true);

        $this->field('position')
            ->visible(true);

        $this->relationship('event', EventResourceDefinition::class)
            ->one()
            ->visible(false, false);
    }
}
