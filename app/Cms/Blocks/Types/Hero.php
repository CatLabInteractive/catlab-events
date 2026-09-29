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
 * Page banner: title, subtitle, background image and up to three buttons.
 *
 * Class Hero
 * @package App\Cms\Blocks\Types
 */
class Hero extends BlockType
{
    public function type(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'Banner';
    }

    public function rules(): array
    {
        return array_merge([
            'title' => [ 'required', 'string', 'max:120' ],
            'subtitle' => [ 'nullable', 'string', 'max:300' ],
            'image_id' => [ 'nullable', 'integer' ],
            'align' => [ 'required', 'in:left,center' ],
        ], $this->buttonRules('buttons', 3));
    }

    public function assetFields(): array
    {
        return [ 'image_id' ];
    }

    public function defaults(): array
    {
        return [ 'title' => '', 'subtitle' => '', 'image_id' => null, 'align' => 'center', 'buttons' => [] ];
    }

    public function prepare(array $data, BlockContext $context): ?array
    {
        $data['image'] = $this->resolveAsset($data['image_id'] ?? null);
        $data['buttons'] = $this->safeButtons($data['buttons'] ?? []);
        $data['align'] = ($data['align'] ?? null) === 'left' ? 'left' : 'center';

        return $data;
    }
}
