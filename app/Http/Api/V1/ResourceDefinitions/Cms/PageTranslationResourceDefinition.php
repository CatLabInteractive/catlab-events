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
use App\Models\PageTranslation;

/**
 * One language version of a CMS page. `blocks` is the stored JSON list
 * ([{ "id": "a1b2c3", "type": "rich_text", "data": { ... } }]); it is
 * validated per block type and its rich text sanitised by App\Cms\PageWriter,
 * like everything else written here.
 *
 * Class PageTranslationResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions\Cms
 */
class PageTranslationResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(PageTranslation::class);

        $this->identifier('id');

        $this->field('locale')
            ->enum(config('cms.locales'))
            ->describe('Language; set on create only.')
            ->filterable()
            ->visible(true, true)
            ->writeable(true, false);

        $this->field('slug')
            ->string()
            ->describe('Last segment of the path: lowercase letters, digits and dashes.')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('path')
            ->string()
            ->describe('Full path below the locale prefix (parent path + slug). Read only.')
            ->visible(true, true);

        $this->field('url')
            ->string()
            ->describe('Public URL. Read only.')
            ->visible(true, true);

        $this->field('title')
            ->string()
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('meta_title')
            ->string()
            ->visible()
            ->writeable(true, true);

        $this->field('meta_description')
            ->string()
            ->visible()
            ->writeable(true, true);

        $this->field('og_image_id')
            ->number()
            ->describe('Asset id of the share image; an asset of the same organisation.')
            ->visible()
            ->writeable(true, true);

        $this->field('is_published')
            ->bool()
            ->filterable()
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('published_at')
            ->datetime()
            ->visible(true, true);

        $this->field('blocks')
            ->object()
            ->array()
            ->describe('Section blocks: a list of { "id": 6 hex characters, "type": block type, "data": { ... } }.')
            ->visible()
            ->writeable(true, true);
    }
}
