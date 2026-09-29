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
 * A grid of 2 to 4 columns of cards (title, text, icon or image, link).
 *
 * Class Cards
 * @package App\Cms\Blocks\Types
 */
class Cards extends BlockType
{
    public function type(): string
    {
        return 'cards';
    }

    public function label(): string
    {
        return 'Kaarten';
    }

    public function rules(): array
    {
        return [
            'title' => [ 'nullable', 'string', 'max:120' ],
            'intro' => [ 'nullable', 'string', 'max:500' ],
            'columns' => [ 'required', 'integer', 'between:2,4' ],
            'items' => [ 'nullable', 'array', 'max:12' ],
            'items.*.title' => [ 'required', 'string', 'max:120' ],
            'items.*.text' => [ 'nullable', 'string', 'max:500' ],
            // Font Awesome 4 icon name without the 'fa-' prefix, e.g. 'trophy'.
            'items.*.icon' => [ 'nullable', 'string', 'max:40', 'regex:/^[a-z0-9\-]+$/' ],
            'items.*.image_id' => [ 'nullable', 'integer' ],
            'items.*.url' => [ 'nullable', 'string', 'max:1024', new SafeUrl() ],
        ];
    }

    public function assetFields(): array
    {
        return [ 'items.*.image_id' ];
    }

    public function defaults(): array
    {
        return [ 'title' => '', 'intro' => '', 'columns' => 3, 'items' => [] ];
    }

    public function prepare(array $data, BlockContext $context): ?array
    {
        $columns = (int) ($data['columns'] ?? 3);
        $data['columns'] = max(2, min(4, $columns));

        $data['items'] = array_map(function (array $item) {
            $item['image'] = $this->resolveAsset($item['image_id'] ?? null);
            $item['url'] = SafeUrl::isSafe($item['url'] ?? null) ? $item['url'] : null;
            $item['icon'] = preg_match('/^[a-z0-9\-]+$/', (string) ($item['icon'] ?? '')) ? $item['icon'] : null;
            return $item;
        }, $this->listOfArrays($data['items'] ?? []));

        return $data;
    }
}
