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
use App\Http\Api\V1\Transformers\AppTimezoneDateTransformer;
use App\Models\Post;

/**
 * A blog post: its publication date (which drives the /YYYY/MM/DD/ part of
 * every translation's URL) and featured image. The content lives in its
 * translations (posts/{post}/translations).
 *
 * Class PostResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions\Cms
 */
class PostResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Post::class);

        $this->identifier('id');

        $this->field('published_at')
            ->datetime(AppTimezoneDateTransformer::class)
            ->describe('Publication date (RFC 822 or ISO 8601). The post is visible from then on, in every locale whose translation is published; null keeps it hidden.')
            ->sortable()
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('featured_image_id')
            ->number()
            ->describe('Asset id of the featured image; an asset of the same organisation.')
            ->visible(true, true)
            ->writeable(true, true);

        // Always expanded, in the translation's index context (locale, slug,
        // url, title, published; not the body), like the admin index.
        $this->relationship('translations', PostTranslationResourceDefinition::class)
            ->many()
            ->visible(true, true)
            ->expanded();
    }
}
