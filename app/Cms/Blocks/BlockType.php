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

use App\Rules\SafeUrl;
use CatLab\CentralStorage\Client\Models\Asset;

/**
 * A section block type. The block's words and images are stored as JSON in
 * page_translations.blocks ({id, type, data}); the type defines how `data`
 * is validated, which fields hold (sanitised) HTML or asset ids, what is
 * resolved before rendering, and which Blade partial renders it.
 *
 * Class BlockType
 * @package App\Cms\Blocks
 */
abstract class BlockType
{
    /**
     * Registry key and stored `type`, e.g. 'hero'.
     * @return string
     */
    abstract public function type(): string;

    /**
     * Admin label (Dutch).
     * @return string
     */
    abstract public function label(): string;

    /**
     * Laravel validation rules, keys relative to the block's `data`. Only
     * the keys mentioned here survive BlockValidator::normalise().
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Fields (dot paths, '*' for list items) that hold rich text. They are
     * run through HtmlSanitizer on save and are the only fields a view may
     * print unescaped.
     * @return string[]
     */
    public function htmlFields(): array
    {
        return [];
    }

    /**
     * Fields (dot paths) that hold an assets.id.
     * @return string[]
     */
    public function assetFields(): array
    {
        return [];
    }

    /**
     * Data for a freshly added block.
     * @return array
     */
    public function defaults(): array
    {
        return [];
    }

    /**
     * Resolve what the view needs (assets, queries). Never trust stored data
     * blindly here: it may predate a rule change.
     * @param array $data
     * @param BlockContext $context
     * @return array|null Null to skip rendering the block.
     */
    public function prepare(array $data, BlockContext $context): ?array
    {
        return $data;
    }

    /**
     * @return string
     */
    public function view(): string
    {
        return 'cms.blocks.' . $this->type();
    }

    /**
     * @return string
     */
    public function formView(): string
    {
        return 'admin.cms.blocks.' . $this->type();
    }

    /**
     * Rules for a list of up to $max buttons under $key.
     * @param string $key
     * @param int $max
     * @return array
     */
    protected function buttonRules(string $key, int $max): array
    {
        return [
            $key => [ 'nullable', 'array', 'max:' . $max ],
            $key . '.*.label' => [ 'required', 'string', 'max:40' ],
            $key . '.*.url' => [ 'required', 'string', 'max:1024', new SafeUrl() ],
            $key . '.*.style' => [ 'required', 'in:primary,secondary' ],
        ];
    }

    /**
     * The asset for a stored id, or null (unset, or deleted since).
     * @param mixed $id
     * @return Asset|null
     */
    protected function resolveAsset($id): ?Asset
    {
        if (!is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        return Asset::find((int) $id);
    }

    /**
     * Stored list field as a list of arrays (defensive against old data).
     * @param mixed $items
     * @return array[]
     */
    protected function listOfArrays($items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * Stored buttons, keeping only those whose URL is (still) safe.
     * @param mixed $buttons
     * @return array[]
     */
    protected function safeButtons($buttons): array
    {
        return array_values(array_filter($this->listOfArrays($buttons), function ($button) {
            return isset($button['label']) && SafeUrl::isSafe($button['url'] ?? null);
        }));
    }
}
