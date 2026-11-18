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

use App\Models\EmailTemplate;
use App\Services\EmailTemplates;

/**
 * A named mail template of the library ("Sjablonen"): a subject and a
 * content for one of the event mails, that events can choose instead of the
 * standard mail. The organisation's own version of a standard mail (a
 * template without a name) is not part of this resource.
 *
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class EmailTemplateResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(EmailTemplate::class);

        $this->identifier('id');

        $this->field('name')
            ->string()
            ->label('Naam')
            ->describe('Uniek per mail binnen de organisatie.')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('type')
            ->enum(EmailTemplates::EVENT_TYPES)
            ->label('Mail')
            ->describe('De mail waarvoor dit een sjabloon is. Ligt vast na het aanmaken.')
            ->filterable()
            ->visible()
            ->writeable(true, false);

        $this->field('type_label')
            ->string()
            ->label('Mail')
            ->visible(true, false);

        $this->field('subject')
            ->string()
            ->label('Onderwerp')
            ->visible()
            ->writeable(true, true);

        $this->field('content')
            ->string()
            ->label('Inhoud')
            ->describe('HTML. De {{ velden }} worden ingevuld wanneer de mail verstuurd wordt.')
            ->visible()
            ->writeable(true, true);

        $this->field('updated_at')
            ->datetime()
            ->label('Aangepast op')
            ->visible(true, true);

        $this->field('used_by')
            ->string()
            ->label('Gebruikt door')
            ->describe('De evenementen die dit sjabloon gebruiken.')
            ->visible(true, true);
    }
}
