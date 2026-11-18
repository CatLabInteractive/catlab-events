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

        $this->field('parent_id')
            ->number()
            ->describe('Id of the parent page (same organisation), or null for a top-level page.')
            ->filterable()
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('sort_order')
            ->number()
            ->sortable()
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('show_in_menu')
            ->bool()
            ->visible(true, true)
            ->writeable(true, true);

        // Always expanded, in the translation's index context (locale, slug,
        // path, url, title, published; not the blocks), like the admin index.
        $this->relationship('translations', PageTranslationResourceDefinition::class)
            ->many()
            ->visible(true, true)
            ->expanded();
    }
}
