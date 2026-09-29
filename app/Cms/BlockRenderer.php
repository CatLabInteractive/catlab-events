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

use App\Cms\Blocks\BlockContext;
use App\Cms\Blocks\BlockRegistry;
use App\Models\PageTranslation;
use Illuminate\Support\Facades\Log;

/**
 * Turns a translation's stored blocks into [view, data, block] triples for
 * cms/page.blade.php. Unknown types (a block removed from the registry) are
 * skipped, and logged once per type, so an old page never breaks.
 *
 * Class BlockRenderer
 * @package App\Cms
 */
class BlockRenderer
{
    /**
     * @var BlockRegistry
     */
    private $registry;

    /**
     * @var bool[]
     */
    private static $loggedUnknownTypes = [];

    /**
     * @param BlockRegistry $registry
     */
    public function __construct(BlockRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param PageTranslation $translation
     * @param BlockContext $context
     * @return array[] each [ 'view' => string, 'data' => array, 'block' => [ 'id', 'type' ] ]
     */
    public function render(PageTranslation $translation, BlockContext $context): array
    {
        return $this->renderBlocks(is_array($translation->blocks) ? $translation->blocks : [], $context);
    }

    /**
     * @param array $blocks
     * @param BlockContext $context
     * @return array[]
     */
    public function renderBlocks(array $blocks, BlockContext $context): array
    {
        $out = [];

        foreach ($blocks as $block) {
            if (!is_array($block) || !is_string($block['type'] ?? null)) {
                continue;
            }

            $type = $this->registry->get($block['type']);
            if (!$type) {
                $this->logUnknownType($block['type']);
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $data = $type->prepare(array_merge($type->defaults(), $data), $context);
            if ($data === null) {
                continue;
            }

            $id = is_string($block['id'] ?? null) && preg_match('/^[0-9a-f]{6}$/', $block['id'])
                ? $block['id']
                : substr(md5(json_encode($block)), 0, 6);

            $out[] = [
                'view' => $type->view(),
                'data' => $data,
                'block' => [ 'id' => $id, 'type' => $type->type() ],
            ];
        }

        return $out;
    }

    /**
     * @param string $type
     */
    private function logUnknownType(string $type)
    {
        if (isset(self::$loggedUnknownTypes[$type])) {
            return;
        }

        self::$loggedUnknownTypes[$type] = true;
        Log::warning('CMS: skipping block of unknown type "' . $type . '".');
    }
}
