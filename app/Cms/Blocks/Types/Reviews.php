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

use App\Cms\Blocks\BlockContext;
use App\Cms\Blocks\BlockType;
use App\Rules\SafeUrl;

/**
 * Quotes from reviews (quote, author, source, link).
 *
 * Class Reviews
 * @package App\Cms\Blocks\Types
 */
class Reviews extends BlockType
{
    public function type(): string
    {
        return 'reviews';
    }

    public function label(): string
    {
        return 'Recensies';
    }

    public function rules(): array
    {
        return [
            'title' => [ 'nullable', 'string', 'max:120' ],
            'items' => [ 'nullable', 'array', 'max:30' ],
            'items.*.quote' => [ 'required', 'string', 'max:1000' ],
            'items.*.author' => [ 'nullable', 'string', 'max:120' ],
            'items.*.source' => [ 'nullable', 'string', 'max:120' ],
            'items.*.url' => [ 'nullable', 'string', 'max:1024', new SafeUrl() ],
        ];
    }

    public function defaults(): array
    {
        return [ 'title' => '', 'items' => [] ];
    }

    public function prepare(array $data, BlockContext $context): ?array
    {
        $data['items'] = array_map(function (array $item) {
            $item['url'] = SafeUrl::isSafe($item['url'] ?? null) ? $item['url'] : null;
            return $item;
        }, $this->listOfArrays($data['items'] ?? []));

        return $data;
    }
}
