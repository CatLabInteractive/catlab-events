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
 * Call to action strip: title, text and up to two buttons.
 *
 * Class Cta
 * @package App\Cms\Blocks\Types
 */
class Cta extends BlockType
{
    public function type(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return 'Oproep (call to action)';
    }

    public function rules(): array
    {
        return array_merge([
            'title' => [ 'required', 'string', 'max:120' ],
            'text' => [ 'nullable', 'string', 'max:500' ],
            'background' => [ 'required', 'in:light,dark,primary' ],
        ], $this->buttonRules('buttons', 2));
    }

    public function defaults(): array
    {
        return [ 'title' => '', 'text' => '', 'background' => 'light', 'buttons' => [] ];
    }

    public function prepare(array $data, BlockContext $context): ?array
    {
        $data['buttons'] = $this->safeButtons($data['buttons'] ?? []);
        $data['background'] = in_array($data['background'] ?? null, [ 'light', 'dark', 'primary' ], true)
            ? $data['background']
            : 'light';

        return $data;
    }
}
