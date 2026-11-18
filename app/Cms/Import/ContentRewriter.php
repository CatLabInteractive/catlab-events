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

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * DOM pass over WordPress content before it is sanitised:
 *
 * - `img[src]` and `a[href]` pointing at the site's `wp-content/uploads/`
 *   are handed to a resolver and replaced by the URL it returns (left alone
 *   when it returns null; the sanitiser then drops images on foreign hosts);
 * - lazy-loaded images and iframes (`data-src`, `data-lazy-src` with a
 *   placeholder `src`) get their real `src` back;
 * - `srcset` and `sizes` are removed (they point at WordPress resizes);
 * - with $unwrapLayout, Elementor's layout wrappers (`section` and any
 *   element with an `elementor*` class) are replaced by their children, so
 *   a page flattens to its headings, paragraphs and images.
 *
 * Class ContentRewriter
 * @package App\Cms\Import
 */
class ContentRewriter
{
    /**
     * Elements that are only layout in Elementor output.
     */
    const LAYOUT_ELEMENTS = [ 'section', 'div', 'span', 'article', 'header', 'footer', 'main', 'aside' ];

    /**
     * @var MediaUrl
     */
    private $mediaUrl;

    /**
     * @param MediaUrl $mediaUrl
     */
    public function __construct(MediaUrl $mediaUrl)
    {
        $this->mediaUrl = $mediaUrl;
    }

    /**
     * Every upload URL in $html (img src and a href), absolute, in document order.
     * @param string $html
     * @return string[]
     */
    public function uploadUrls(string $html): array
    {
        $urls = [];
        [ $document, $xpath, $root ] = $this->load($html);
        if (!$root) {
            return [];
        }

        foreach (iterator_to_array($xpath->query('.//img | .//iframe', $root)) as $element) {
            $this->restoreLazySource($element);
        }

        foreach ($xpath->query('.//img[@src] | .//a[@href]', $root) as $element) {
            $value = $element->getAttribute($element->nodeName === 'img' ? 'src' : 'href');
            $url = $this->mediaUrl->normalise($value);
            if ($url !== null) {
                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    /**
     * @param string $html
     * @param callable $resolve fn(string $absoluteUploadUrl): ?string
     * @param bool $unwrapLayout
     * @return string
     */
    public function rewrite(string $html, callable $resolve, bool $unwrapLayout = false): string
    {
        [ $document, $xpath, $root ] = $this->load($html);
        if (!$root) {
            return '';
        }

        foreach (iterator_to_array($xpath->query('.//img | .//iframe', $root)) as $element) {
            $this->restoreLazySource($element);
        }

        foreach (iterator_to_array($xpath->query('.//img', $root)) as $img) {
            /** @var DOMElement $img */
            $img->removeAttribute('srcset');
            $img->removeAttribute('sizes');
            $this->rewriteAttribute($img, 'src', $resolve);
        }

        foreach (iterator_to_array($xpath->query('.//a[@href]', $root)) as $link) {
            $this->rewriteAttribute($link, 'href', $resolve);
        }

        if ($unwrapLayout) {
            // Deepest first, so a wrapper's children are final when it goes.
            $elements = array_reverse(iterator_to_array($xpath->query('.//*', $root)));
            foreach ($elements as $element) {
                if ($this->isLayout($element)) {
                    $this->unwrap($element);
                }
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * @param DOMElement $element
     * @param string $attribute
     * @param callable $resolve
     */
    private function rewriteAttribute(DOMElement $element, string $attribute, callable $resolve): void
    {
        $url = $this->mediaUrl->normalise($element->getAttribute($attribute));
        if ($url === null) {
            return;
        }

        $replacement = $resolve($url);
        if ($replacement !== null) {
            $element->setAttribute($attribute, $replacement);
        }
    }

    /**
     * Lazy-load plugins keep the real URL in a data attribute and put a
     * placeholder (a data: URI) in src.
     * @param DOMElement $element
     */
    private function restoreLazySource(DOMElement $element): void
    {
        foreach ([ 'data-src', 'data-lazy-src' ] as $attribute) {
            $lazy = trim($element->getAttribute($attribute));
            if ($lazy === '') {
                continue;
            }
            $src = trim($element->getAttribute('src'));
            if ($src === '' || stripos($src, 'data:') === 0) {
                $element->setAttribute('src', $lazy);
            }
            break;
        }
    }

    /**
     * @param DOMElement $element
     * @return bool
     */
    private function isLayout(DOMElement $element): bool
    {
        $name = strtolower($element->nodeName);
        if ($name === 'section') {
            return true;
        }
        if (!in_array($name, self::LAYOUT_ELEMENTS, true)) {
            return false;
        }

        foreach (preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) as $class) {
            if (strpos($class, 'elementor') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replace $element by its children.
     * @param DOMNode $element
     */
    private function unwrap(DOMNode $element): void
    {
        $parent = $element->parentNode;
        if (!$parent) {
            return;
        }
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    /**
     * @param string $html
     * @return array [ DOMDocument, DOMXPath, DOMElement|null ]
     */
    private function load(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div id="wp-import-root">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//div[@id="wp-import-root"]')->item(0);

        return [ $document, $xpath, $root ];
    }
}
