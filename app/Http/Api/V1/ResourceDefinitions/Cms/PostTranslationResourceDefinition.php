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

namespace App\Http\Api\V1\ResourceDefinitions\Cms;

use App\Http\Api\V1\ResourceDefinitions\BaseResourceDefinition;
use App\Models\PostTranslation;

/**
 * One language version of a blog post. `body` is HTML; it is sanitised by
 * App\Cms\PostWriter, like everything else written here.
 *
 * Class PostTranslationResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions\Cms
 */
class PostTranslationResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(PostTranslation::class);

        $this->identifier('id');

        $this->field('post_id')
            ->number()
            ->describe('Id of the post. Read only.')
            ->visible(true, true);

        $this->field('locale')
            ->enum(config('cms.locales'))
            ->label('Taal')
            ->describe('De taal; ligt vast na het aanmaken.')
            ->filterable()
            ->visible(true, true)
            ->writeable(true, false);

        $this->field('title')
            ->string()
            ->label('Titel')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('slug')
            ->string()
            ->label('Adres')
            ->describe('Het laatste deel van het adres (/jjjj/mm/dd/…): kleine letters, cijfers en koppeltekens, bv. quiz-van-het-jaar. Uniek per taal.')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('url')
            ->string()
            ->describe('Public URL (/{locale/}YYYY/MM/DD/slug). Read only.')
            ->visible(true, true);

        $this->field('excerpt')
            ->string()
            ->label('Samenvatting')
            ->describe('Gewone tekst, getoond in de lijst van berichten en gebruikt als SEO-beschrijving.')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('author')
            ->string()
            ->label('Auteur')
            ->describe('Leeg: de naam van de organisatie.')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('body')
            ->string()
            ->label('Tekst')
            ->describe('HTML; wordt bij het opslaan opgeschoond (scripts, event handlers, stijlen en vreemde afbeeldingen verdwijnen).')
            ->visible()
            ->writeable(true, true);

        $this->field('meta_title')
            ->string()
            ->label('SEO-titel')
            ->visible()
            ->writeable(true, true);

        $this->field('meta_description')
            ->string()
            ->label('SEO-beschrijving')
            ->visible()
            ->writeable(true, true);

        $this->field('is_published')
            ->bool()
            ->label('Gepubliceerd')
            ->filterable()
            ->visible(true, true)
            ->writeable(true, true);
    }
}
