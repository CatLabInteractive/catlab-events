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

use App\Cms\Blocks\Types\Gallery;
use App\Models\Organisation;
use CatLab\CentralStorage\Client\Models\Asset;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Moves the photos out of rich text blocks into gallery blocks: a
 * WordPress gallery (figure.wp-block-gallery, the classic div.gallery), or
 * a run of at least MIN_RUN images with nothing but images in between. The
 * text before and after stays a rich_text block around the gallery; a
 * heading right before the photos becomes the gallery's title.
 *
 * Used by the WordPress importer and by `cms:extract-galleries` for pages
 * that were imported before the gallery block existed. Only images that are
 * the organisation's own assets move; a run with any other image stays text.
 *
 * Class GalleryExtractor
 * @package App\Cms
 */
class GalleryExtractor
{
    /**
     * Loose images that make a gallery.
     */
    const MIN_RUN = 3;

    /**
     * @var callable fn(string $src): ?Asset
     */
    private $asset;

    /**
     * @param callable $asset fn(string $src): ?Asset
     */
    public function __construct(callable $asset)
    {
        $this->asset = $asset;
    }

    /**
     * An extractor that knows the organisation's assets by their URL
     * (…/assets/{asset_key}…).
     * @param Organisation $organisation
     * @return self
     */
    public static function forOrganisation(Organisation $organisation): self
    {
        $cache = [];

        return new self(function (string $src) use ($organisation, &$cache) {
            if (!preg_match('#/assets/([^/?\#]+)#', $src, $match)) {
                return null;
            }
            if (!array_key_exists($match[1], $cache)) {
                $cache[$match[1]] = Asset::query()
                    ->where('organisation_id', '=', $organisation->id)
                    ->where('asset_key', '=', $match[1])
                    ->whereNull('deleted_at')
                    ->first();
            }
            return $cache[$match[1]];
        });
    }

    /**
     * @param array[] $blocks [ { id, type, data } ]
     * @return array[] the same blocks, rich text with photos split up
     */
    public function extract(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || ($block['type'] ?? null) !== 'rich_text' || !is_string($block['data']['html'] ?? null)) {
                $out[] = $block;
                continue;
            }
            foreach ($this->split($block) as $part) {
                $out[] = $part;
            }
        }

        return $out;
    }

    /**
     * Whether extract() would change these blocks.
     * @param array[] $blocks
     * @return bool
     */
    public function wouldChange(array $blocks): bool
    {
        return $this->extract($blocks) !== $blocks;
    }

    /**
     * @param array $block a rich_text block
     * @return array[]
     */
    private function split(array $block): array
    {
        $html = $block['data']['html'];
        if (stripos($html, '<img') === false) {
            return [ $block ];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body) {
            return [ $block ];
        }

        // Top-level nodes as [ node, images|null, isGallery ].
        $nodes = [];
        foreach ($body->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '') {
                continue;
            }
            $gallery = $node instanceof DOMElement && $this->isGallery($node);
            $nodes[] = [ $node, $node instanceof DOMElement ? $this->images($document, $node, $gallery) : null, $gallery ];
        }

        // Runs of photo-only nodes that make a gallery: [ start, end ].
        $runs = [];
        $i = 0;
        while ($i < count($nodes)) {
            if ($nodes[$i][1] === null) {
                $i++;
                continue;
            }
            $start = $i;
            $images = [];
            $hasGallery = false;
            while ($i < count($nodes) && $nodes[$i][1] !== null) {
                $images = array_merge($images, $nodes[$i][1]);
                $hasGallery = $hasGallery || $nodes[$i][2];
                $i++;
            }
            if (($hasGallery || count($images) >= self::MIN_RUN) && $this->resolve($images) !== null) {
                $runs[] = [ $start, $i - 1 ];
            }
        }

        if (!$runs) {
            return [ $block ];
        }

        $colour = $block['data']['colour'] ?? null;
        $parts = [];
        $text = [];
        $flush = function () use (&$text, &$parts, $document, $colour) {
            $html = trim(implode('', array_map(function (DOMNode $node) use ($document) {
                return $document->saveHTML($node);
            }, $text)));
            $text = [];
            if ($html !== '' && trim(strip_tags($html, '<img><iframe>')) !== '') {
                $parts[] = [ 'type' => 'rich_text', 'data' => array_filter([ 'html' => $html, 'colour' => $colour ], function ($v) {
                    return $v !== null;
                }) ];
            }
        };

        $next = 0;
        foreach ($runs as [ $start, $end ]) {
            for ($j = $next; $j < $start; $j++) {
                $text[] = $nodes[$j][0];
            }
            // A heading right before the photos is their title.
            $title = null;
            $last = end($text);
            if ($last instanceof DOMElement && in_array(strtolower($last->nodeName), [ 'h2', 'h3', 'h4' ], true)) {
                $title = $this->limit($this->clean($last->textContent), 120);
                array_pop($text);
            }
            $flush();

            $images = [];
            for ($j = $start; $j <= $end; $j++) {
                $images = array_merge($images, $nodes[$j][1]);
            }
            $items = array_slice($this->resolve($images), 0, Gallery::MAX_ITEMS);
            $parts[] = [ 'type' => 'gallery', 'data' => array_filter([
                'title' => $title,
                'intro' => null,
                'layout' => 'grid',
                'visible' => 12,
                'items' => $items,
                'colour' => $colour,
            ], function ($v) {
                return $v !== null;
            }) ];
            $next = $end + 1;
        }
        for ($j = $next; $j < count($nodes); $j++) {
            $text[] = $nodes[$j][0];
        }
        $flush();

        // The first part keeps the block's id (links to #b-{id} keep working).
        return array_map(function (array $part, int $index) use ($block) {
            return [
                'id' => $index === 0 && is_string($block['id'] ?? null) ? $block['id'] : PageWriter::newBlockId(),
                'type' => $part['type'],
                'data' => $part['data'],
            ];
        }, $parts, array_keys($parts));
    }

    /**
     * @param DOMElement $element
     * @return bool
     */
    private function isGallery(DOMElement $element): bool
    {
        $class = ' ' . $element->getAttribute('class') . ' ';
        return strpos($class, ' wp-block-gallery ') !== false
            || strpos($class, ' blocks-gallery-grid ') !== false
            || (strtolower($element->nodeName) === 'div' && strpos($class, ' gallery ') !== false);
    }

    /**
     * The images of a node that holds nothing but images (and their
     * captions or links), or null.
     * @param DOMDocument $document
     * @param DOMElement $element
     * @param bool $gallery
     * @return array[]|null [ [ src, caption ], ... ]
     */
    private function images(DOMDocument $document, DOMElement $element, bool $gallery): ?array
    {
        $tag = strtolower($element->nodeName);
        if (!$gallery && !in_array($tag, [ 'img', 'figure', 'p', 'div', 'a' ], true)) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $imgs = $tag === 'img' ? [ $element ] : iterator_to_array($xpath->query('.//img', $element));
        if (!$imgs) {
            return null;
        }

        // Anything said outside the captions keeps this node text.
        $text = $element->textContent;
        foreach ($xpath->query('.//figcaption', $element) as $caption) {
            $text = str_replace($caption->textContent, '', $text);
        }
        if (!$gallery && $this->clean($text) !== '') {
            return null;
        }

        $out = [];
        foreach ($imgs as $img) {
            $caption = null;
            for ($parent = $img->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
                if (strtolower($parent->nodeName) === 'figure') {
                    $figcaption = $xpath->query('./figcaption', $parent)->item(0);
                    $caption = $figcaption ? $this->clean($figcaption->textContent) : null;
                    break;
                }
                if ($parent === $element) {
                    break;
                }
            }
            $alt = $this->clean($img->getAttribute('alt'));
            $out[] = [ 'src' => $img->getAttribute('src'), 'caption' => $caption ?: ($alt !== '' ? $alt : null) ];
        }

        return $out;
    }

    /**
     * Gallery items for images, or null when one of them is not an asset.
     * @param array[] $images
     * @return array[]|null
     */
    private function resolve(array $images): ?array
    {
        $items = [];
        foreach ($images as $image) {
            $asset = ($this->asset)($image['src']);
            if (!$asset) {
                return null;
            }
            $items[] = [ 'image_id' => (int) $asset->id, 'caption' => $this->limit($image['caption'], 200) ];
        }

        return $items;
    }

    /**
     * @param string $text
     * @return string
     */
    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * @param string|null $text
     * @param int $max
     * @return string|null
     */
    private function limit(?string $text, int $max): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
    }
}
