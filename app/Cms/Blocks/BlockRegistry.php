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

namespace App\Cms\Blocks;

use InvalidArgumentException;

/**
 * Maps a block `type` to its BlockType, built from config('cms.blocks').
 * Bound as a container singleton by CmsServiceProvider.
 *
 * Class BlockRegistry
 * @package App\Cms\Blocks
 */
class BlockRegistry
{
    /**
     * @var BlockType[]
     */
    private $types = [];

    /**
     * @param string[] $map type => BlockType class
     */
    public function __construct(array $map)
    {
        foreach ($map as $key => $class) {
            $type = new $class();
            if (!$type instanceof BlockType) {
                throw new InvalidArgumentException($class . ' must extend ' . BlockType::class);
            }
            $this->types[$key] = $type;
        }
    }

    /**
     * @param string $type
     * @return BlockType|null
     */
    public function get(string $type): ?BlockType
    {
        return $this->types[$type] ?? null;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * @return BlockType[] type => BlockType, in registration order
     */
    public function all(): array
    {
        return $this->types;
    }
}
