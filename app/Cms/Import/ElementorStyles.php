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

namespace App\Cms\Import;

use App\Tools\ColourPalette;

/**
 * What an Elementor page's generated CSS (uploads/elementor/css/post-N.css)
 * says about its elements, by data-id: background colour, background photo
 * and round images. Only the desktop rules count (media queries are
 * dropped). Colours come back as a block colour: a palette index ('0'..),
 * 'peach' for the light band colour, or null for white / none.
 *
 * Class ElementorStyles
 * @package App\Cms\Import
 */
class ElementorStyles
{
    /**
     * The light band colour of the Kleurveld design.
     */
    const PEACH = '#f8e6c6';

    /**
     * @var array[] data-id => [ background => ?string, image => ?string, round => bool ]
     */
    private $elements = [];

    /**
     * @var ColourPalette
     */
    private $palette;

    /**
     * @param string $css
     * @param ColourPalette|null $palette
     */
    public function __construct(string $css = '', ?ColourPalette $palette = null)
    {
        $this->palette = $palette ?? new ColourPalette();
        $this->parse($css);
    }

    /**
     * @param string $dataId
     * @return string|null a block colour ('0'.., 'peach') or null
     */
    public function colour(string $dataId): ?string
    {
        $hex = $this->elements[$dataId]['background'] ?? null;
        return $hex === null ? null : $this->blockColour($hex);
    }

    /**
     * @param string $dataId
     * @return string|null the background photo's URL
     */
    public function image(string $dataId): ?string
    {
        return $this->elements[$dataId]['image'] ?? null;
    }

    /**
     * @param string $dataId
     * @return bool whether the (image) element is shown as a circle
     */
    public function isRound(string $dataId): bool
    {
        return $this->elements[$dataId]['round'] ?? false;
    }

    /**
     * The palette colour (or peach) nearest to $hex, or null for white,
     * black, greys and colours that are far from all of them.
     * @param string $hex
     * @return string|null
     */
    public function blockColour(string $hex): ?string
    {
        $rgb = $this->rgb($hex);
        if ($rgb === null) {
            return null;
        }

        $max = max($rgb);
        $min = min($rgb);
        if ($max - $min < 24) {
            // white, black, grey
            return null;
        }

        $candidates = [ 'peach' => self::PEACH ];
        foreach ($this->palette->getFields() as $i => $field) {
            $candidates[(string) $i] = $field['field'];
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($candidates as $key => $candidate) {
            $other = $this->rgb($candidate);
            $distance = ($rgb[0] - $other[0]) ** 2 + ($rgb[1] - $other[1]) ** 2 + ($rgb[2] - $other[2]) ** 2;
            if ($distance < $bestDistance) {
                $best = $key;
                $bestDistance = $distance;
            }
        }

        // Further than about 100 (of 441) away: not one of ours.
        return $bestDistance <= 100 ** 2 ? $best : null;
    }

    /**
     * @param string $css
     */
    private function parse(string $css)
    {
        $css = $this->withoutMediaQueries(preg_replace('#/\*.*?\*/#s', '', $css));

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        foreach ($rules as $rule) {
            $body = $rule[2];
            foreach (explode(',', $rule[1]) as $selector) {
                if (!preg_match_all('/\.elementor-element-([0-9a-f]+)(?![0-9a-f])/', $selector, $ids)) {
                    continue;
                }
                // The selector is about the last element it names.
                $id = end($ids[1]);
                $rest = substr($selector, strrpos($selector, 'elementor-element-' . $id) + strlen('elementor-element-' . $id));
                $this->apply($id, trim($rest), $body);
            }
        }
    }

    /**
     * @param string $id
     * @param string $rest the selector after the element, e.g. '> .elementor-background-overlay'
     * @param string $body
     */
    private function apply(string $id, string $rest, string $body)
    {
        $element = $this->elements[$id] ?? [ 'background' => null, 'image' => null, 'round' => false ];

        if (preg_match('/(^|\s)img\b/', $rest)) {
            if (preg_match('/border-radius:\s*50%/', $body) || strpos($body, 'mask-shapes/circle') !== false) {
                $element['round'] = true;
            }
            $this->elements[$id] = $element;
            return;
        }

        $isBackground = $rest === ''
            || strpos($rest, ':not(') === 0
            || strpos($rest, 'elementor-motion-effects-layer') !== false
            || strpos($rest, 'elementor-element-populated') !== false
            || strpos($rest, 'elementor-widget-wrap') !== false
            || strpos($rest, 'elementor-background-overlay') !== false;
        if (!$isBackground || strpos($rest, 'elementor-heading-title') !== false || strpos($rest, 'elementor-button') !== false) {
            return;
        }

        $isOverlay = strpos($rest, 'elementor-background-overlay') !== false;
        if ($isOverlay && preg_match('/(?:^|;)\s*opacity:\s*([0-9.]+)/', $body, $opacity) && (float) $opacity[1] < 0.5) {
            return;
        }

        if (preg_match('/(?:^|;)\s*background(?:-color)?:\s*(#[0-9a-fA-F]{3,8}|rgba?\([^)]*\))/', $body, $colour)) {
            $hex = $this->hex($colour[1]);
            // A see-through colour (an overlay on a photo) is not a field.
            if ($hex !== null) {
                // The overlay of a photo section is a veil, not a colour.
                if (!($isOverlay && $element['image'])) {
                    if ($element['background'] === null || $this->blockColour($element['background']) === null || $isOverlay) {
                        $element['background'] = $hex;
                    }
                }
            }
        }

        if (!$isOverlay && preg_match('/background(?:-image)?:[^;]*url\(\s*["\']?([^"\')]+)["\']?\s*\)/', $body, $image)) {
            $element['image'] = trim($image[1]);
        }

        $this->elements[$id] = $element;
    }

    /**
     * @param string $css
     * @return string
     */
    private function withoutMediaQueries(string $css): string
    {
        $out = '';
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            if ($css[$i] === '@' && preg_match('/\G@media[^{]*\{/', $css, $m, 0, $i)) {
                $depth = 0;
                for ($j = $i; $j < $length; $j++) {
                    if ($css[$j] === '{') {
                        $depth++;
                    } elseif ($css[$j] === '}') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }
                }
                $i = $j;
                continue;
            }
            $out .= $css[$i];
        }
        return $out;
    }

    /**
     * #rgb, #rrggbb, #rrggbbaa or rgb[a]() as #rrggbb; null when mostly
     * transparent.
     * @param string $value
     * @return string|null
     */
    private function hex(string $value): ?string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([0-9.]+))?\s*\)$/', $value, $m)) {
            if (isset($m[4]) && $m[4] !== '' && (float) $m[4] < 0.5) {
                return null;
            }
            return sprintf('#%02x%02x%02x', min(255, $m[1]), min(255, $m[2]), min(255, $m[3]));
        }

        $value = ltrim($value, '#');
        if (strlen($value) === 3 || strlen($value) === 4) {
            $value = implode('', array_map(function ($c) {
                return $c . $c;
            }, str_split($value)));
        }
        if (strlen($value) === 8) {
            if (hexdec(substr($value, 6, 2)) < 128) {
                return null;
            }
            $value = substr($value, 0, 6);
        }

        return strlen($value) === 6 && ctype_xdigit($value) ? '#' . $value : null;
    }

    /**
     * @param string $hex
     * @return int[]|null
     */
    private function rgb(string $hex): ?array
    {
        $hex = $this->hex($hex);
        if ($hex === null) {
            return null;
        }
        return [ hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)) ];
    }
}
