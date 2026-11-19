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
use App\Models\Page;

/**
 * A CMS page: its place in the page tree. The content lives in its
 * translations (pages/{page}/translations).
 *
 * Class PageResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions\Cms
 */
class PageResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Page::class);

        $this->identifier('id');

        // The title in the default language (else the first translation's). Read only.
        $this->field('title')
            ->string()
            ->label('Titel')
            ->visible(true, true);

        $this->field('parent_id')
            ->number()
            ->label('Bovenliggende pagina')
            ->describe('De pagina (id, zelfde organisatie) waaronder deze pagina hangt; leeg voor het hoofdniveau.')
            ->filterable()
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('sort_order')
            ->number()
            ->label('Volgorde')
            ->describe('Lager staat eerst, in het menu en in de lijst.')
            ->sortable()
            ->visible(true, true)
            ->writeable(true, true);

        // A menu link instead of content: @series, @calendar, @blog or a URL.
        $this->field('link')
            ->string()
            ->label('Soort')
            ->describe('Een menulink heeft geen eigen inhoud: in het menu en de footer leidt hij naar zijn doel (@series, @calendar, @blog of een adres).')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('show_in_menu')
            ->bool()
            ->label('Toon in het menu')
            ->describe('Een subpagina verschijnt in het uitklapmenu van haar hoofdpagina.')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('show_in_footer')
            ->bool()
            ->label('Toon in de footer')
            ->visible(true, true)
            ->writeable(true, true);

        // The organisation's home page (organisations.home_page_id). Writing
        // true makes this page the home page, false stops using it as such.
        $this->field('is_home')
            ->bool()
            ->label('Startpagina')
            ->describe('De pagina die op / getoond wordt. Zonder startpagina toont / de kalender.')
            ->visible(true, true)
            ->writeable(true, true);

        // Always expanded, in the translation's index context (locale, slug,
        // path, url, title, published; not the blocks), like the admin index.
        $this->relationship('translations', PageTranslationResourceDefinition::class)
            ->label('Talen')
            ->many()
            ->visible(true, true)
            ->expanded();
    }
}
