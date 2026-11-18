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

namespace App\Cms\Blocks\Types;

use App\Cms\Blog;
use App\Cms\Blocks\BlockContext;
use App\Cms\Blocks\BlockType;

/**
 * The organisation's latest visible blog posts in the page's locale (cached
 * per organisation and locale by App\Cms\Blog). Renders nothing when there
 * are none.
 *
 * Class LatestPosts
 * @package App\Cms\Blocks\Types
 */
class LatestPosts extends BlockType
{
    public function type(): string
    {
        return 'latest_posts';
    }

    public function label(): string
    {
        return 'Laatste blogberichten';
    }

    public function rules(): array
    {
        return [
            'title' => [ 'nullable', 'string', 'max:120' ],
            'limit' => [ 'required', 'integer', 'between:1,6' ],
        ];
    }

    public function defaults(): array
    {
        return [ 'title' => '', 'limit' => 3 ];
    }

    public function prepare(array $data, BlockContext $context): ?array
    {
        $data['posts'] = app(Blog::class)->latest($context->organisation, $context->locale, (int) ($data['limit'] ?? 3));
        $data['locale'] = $context->locale;

        return $data;
    }
}
