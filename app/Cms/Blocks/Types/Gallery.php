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

/**
 * A set of photos. Every photo opens in the lightbox, which steps through
 * the whole gallery. Layouts:
 * - grid: even 3:2 tiles (cropped);
 * - mosaic: each photo in its own shape, in columns;
 * - featured: even tiles, the first one big.
 * Long galleries show their first `visible` photos and a button for the rest.
 *
 * Class Gallery
 * @package App\Cms\Blocks\Types
 */
class Gallery extends BlockType
{
    const LAYOUTS = [ 'grid', 'mosaic', 'featured' ];

    const MAX_ITEMS = 100;

    /**
     * Photos shown before "show all" (0 = all of them).
     */
    const VISIBLE = [ 0, 6, 9, 12, 18, 24 ];

    public function type(): string
    {
        return 'gallery';
    }

    public function label(): string
    {
        return 'Fotogalerij';
    }

    public function rules(): array
    {
        return [
            'title' => [ 'nullable', 'string', 'max:120' ],
            'intro' => [ 'nullable', 'string', 'max:500' ],
            'layout' => [ 'nullable', 'in:' . implode(',', self::LAYOUTS) ],
            'visible' => [ 'nullable', 'integer', 'in:' . implode(',', self::VISIBLE) ],
            'items' => [ 'nullable', 'array', 'max:' . self::MAX_ITEMS ],
            'items.*.image_id' => [ 'required', 'integer' ],
            'items.*.caption' => [ 'nullable', 'string', 'max:200' ],
        ];
    }

    public function assetFields(): array
    {
        return [ 'items.*.image_id' ];
    }

    public function defaults(): array
    {
        return [ 'title' => '', 'intro' => '', 'layout' => 'grid', 'visible' => 12, 'items' => [] ];
    }

    public function prepare(array $data, BlockContext $context): ?array
    {
        $items = [];
        foreach ($this->listOfArrays($data['items'] ?? []) as $item) {
            $image = $this->resolveAsset($item['image_id'] ?? null);
            if (!$image) {
                continue;
            }
            $caption = is_string($item['caption'] ?? null) ? trim($item['caption']) : '';
            $items[] = [
                'image' => $image,
                'caption' => $caption !== '' ? $caption : null,
            ];
        }

        // Nothing to show: skip the block (also when every photo was deleted).
        if (!$items) {
            return null;
        }

        $data['items'] = $items;
        $data['layout'] = in_array($data['layout'] ?? null, self::LAYOUTS, true) ? $data['layout'] : 'grid';
        $visible = (int) ($data['visible'] ?? 0);
        $visible = in_array($visible, self::VISIBLE, true) ? $visible : 12;
        // The big first photo plus an even number fills the two columns on phones.
        if ($data['layout'] === 'featured' && $visible > 0 && $visible % 2 === 0) {
            $visible++;
        }
        // Only fold when it hides a meaningful number of photos.
        $data['visible'] = $visible > 0 && count($items) > $visible + 2 ? $visible : 0;

        return $data;
    }
}
