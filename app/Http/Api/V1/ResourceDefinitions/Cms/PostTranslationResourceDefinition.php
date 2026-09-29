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

        $this->field('locale')
            ->enum(config('cms.locales'))
            ->describe('Language; set on create only.')
            ->filterable()
            ->visible(true, true)
            ->writeable(true, false);

        $this->field('slug')
            ->string()
            ->describe('Last segment of the URL: lowercase letters, digits and dashes; unique per organisation and locale.')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('url')
            ->string()
            ->describe('Public URL (/{locale/}YYYY/MM/DD/slug). Read only.')
            ->visible(true, true);

        $this->field('title')
            ->string()
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('excerpt')
            ->string()
            ->describe('Plain text summary, shown in lists and used as meta description.')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('author')
            ->string()
            ->describe('Free text byline; empty shows the organisation name.')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('body')
            ->string()
            ->describe('HTML; sanitised on write (scripts, event handlers, styles and foreign images are removed).')
            ->visible()
            ->writeable(true, true);

        $this->field('meta_title')
            ->string()
            ->visible()
            ->writeable(true, true);

        $this->field('meta_description')
            ->string()
            ->visible()
            ->writeable(true, true);

        $this->field('is_published')
            ->bool()
            ->filterable()
            ->visible(true, true)
            ->writeable(true, true);
    }
}
