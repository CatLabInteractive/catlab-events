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

namespace App\Cms;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validation and normalisation of a page translation's block list. Used by
 * every write path (admin form, API, import) so they behave identically.
 *
 * Class BlockValidator
 * @package App\Cms
 */
class BlockValidator
{
    /**
     * @var BlockRegistry
     */
    private $registry;

    /**
     * @var HtmlSanitizer
     */
    private $sanitizer;

    /**
     * @param BlockRegistry $registry
     * @param HtmlSanitizer $sanitizer
     */
    public function __construct(BlockRegistry $registry, HtmlSanitizer $sanitizer)
    {
        $this->registry = $registry;
        $this->sanitizer = $sanitizer;
    }

    /**
     * Rules for validating [ 'blocks' => $blocks ]: the list itself, every
     * block's type and id, then each known block's type rules prefixed with
     * blocks.{i}.data.
     * @param array $blocks
     * @return array
     */
    public function rules(array $blocks): array
    {
        $rules = [
            'blocks' => [ 'array', 'max:' . config('cms.max_blocks', 40) ],
            'blocks.*' => [ 'array' ],
            'blocks.*.type' => [ 'required', 'string', Rule::in(array_keys($this->registry->all())) ],
            'blocks.*.id' => [ 'required', 'string', 'regex:/^[0-9a-f]{6}$/' ],
            'blocks.*.data' => [ 'nullable', 'array' ],
        ];

        foreach (array_values($blocks) as $index => $block) {
            $type = is_array($block) && is_string($block['type'] ?? null)
                ? $this->registry->get($block['type'])
                : null;

            if (!$type) {
                continue;
            }

            $prefix = 'blocks.' . $index . '.data.';

            foreach ($type->rules() as $field => $fieldRules) {
                $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

                if (in_array($field, $type->assetFields(), true)) {
                    $fieldRules = array_merge($fieldRules, [ 'exists:assets,id' ]);
                }

                $rules[$prefix . $field] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Validate and normalise in one go.
     * @param array $blocks
     * @return array Normalised blocks.
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $blocks): array
    {
        Validator::make([ 'blocks' => $blocks ], $this->rules($blocks))->validate();
        return $this->normalise($blocks);
    }

    /**
     * Keep only registered blocks and, inside `data`, only the keys the
     * type's rules mention; sanitise the type's html fields. Run after
     * validation, before storing.
     * @param array $blocks
     * @return array
     */
    public function normalise(array $blocks): array
    {
        $out = [];

        foreach ($blocks as $block) {
            if (!is_array($block) || !is_string($block['type'] ?? null)) {
                continue;
            }

            $type = $this->registry->get($block['type']);
            if (!$type) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $data = $this->filterKeys($data, $this->keyTree($type));

            foreach ($type->htmlFields() as $field) {
                $data = $this->sanitizePath($data, explode('.', $field));
            }

            $out[] = [
                'id' => (string) ($block['id'] ?? ''),
                'type' => $type->type(),
                'data' => $data,
            ];
        }

        return $out;
    }

    /**
     * Turn rule keys ('buttons', 'buttons.*.label') into a nested tree.
     * @param BlockType $type
     * @return array
     */
    private function keyTree(BlockType $type): array
    {
        $tree = [];

        foreach (array_keys($type->rules()) as $key) {
            $node = &$tree;
            foreach (explode('.', $key) as $segment) {
                if (!isset($node[$segment])) {
                    $node[$segment] = [];
                }
                $node = &$node[$segment];
            }
            unset($node);
        }

        return $tree;
    }

    /**
     * @param array $data
     * @param array $tree
     * @return array
     */
    private function filterKeys(array $data, array $tree): array
    {
        $out = [];

        foreach ($tree as $key => $subtree) {
            if ($key === '*' || !array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if (count($subtree) > 0 && is_array($value)) {
                if (isset($subtree['*'])) {
                    $items = [];
                    foreach (array_values($value) as $item) {
                        $items[] = (count($subtree['*']) > 0 && is_array($item))
                            ? $this->filterKeys($item, $subtree['*'])
                            : $item;
                    }
                    $value = $items;
                } else {
                    $value = $this->filterKeys($value, $subtree);
                }
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param mixed $data
     * @param string[] $path
     * @return mixed
     */
    private function sanitizePath($data, array $path)
    {
        if (count($path) === 0) {
            return is_string($data) ? $this->sanitizer->sanitize($data) : $data;
        }

        if (!is_array($data)) {
            return $data;
        }

        $segment = array_shift($path);

        if ($segment === '*') {
            foreach ($data as $key => $item) {
                $data[$key] = $this->sanitizePath($item, $path);
            }
        } elseif (array_key_exists($segment, $data)) {
            $data[$segment] = $this->sanitizePath($data[$segment], $path);
        }

        return $data;
    }
}
