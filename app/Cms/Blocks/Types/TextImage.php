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
 * Title and rich text next to an image, with an optional button.
 *
 * Class TextImage
 * @package App\Cms\Blocks\Types
 */
class TextImage extends BlockType
{
    public function type(): string
    {
        return 'text_image';
    }

    public function label(): string
    {
        return 'Tekst met afbeelding';
    }

    public function rules(): array
    {
        return [
            'title' => [ 'nullable', 'string', 'max:120' ],
            'html' => [ 'nullable', 'string', 'max:' . (512 * 1024) ],
            'image_id' => [ 'nullable', 'integer' ],
            'image_position' => [ 'required', 'in:left,right' ],
            'button' => [ 'nullable', 'array' ],
            'button.label' => [ 'nullable', 'string', 'max:40', 'required_with:button.url' ],
            'button.url' => [ 'nullable', 'string', 'max:1024', 'required_with:button.label', new SafeUrl() ],
        ];
    }

    public function htmlFields(): array
    {
        return [ 'html' ];
    }

    public function assetFields(): array
    {
        return [ 'image_id' ];
    }

    public function defaults(): array
    {
        return [ 'title' => '', 'html' => '', 'image_id' => null, 'image_position' => 'right', 'button' => null ];
    }

    public function prepare(array $data, BlockContext $context): ?array
    {
        $data['image'] = $this->resolveAsset($data['image_id'] ?? null);
        $data['image_position'] = ($data['image_position'] ?? null) === 'left' ? 'left' : 'right';

        $button = is_array($data['button'] ?? null) ? $data['button'] : null;
        $data['button'] = $button && !empty($button['label']) && SafeUrl::isSafe($button['url'] ?? null)
            ? $button
            : null;

        return $data;
    }
}
