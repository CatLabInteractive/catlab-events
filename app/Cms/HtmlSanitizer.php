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

use DOMDocument;
use DOMElement;
use DOMXPath;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The one place rich text is cleaned before it is stored (and later printed
 * unescaped). Wraps symfony/html-sanitizer with the allow-list from
 * config('cms.sanitizer'), then runs a DOM pass for what Symfony cannot
 * express: per-element host lists (images: our central storage plus approved
 * hosts; iframes: video hosts), the class prefix filter, h1 -> h2 and
 * rel="noopener noreferrer" on target="_blank" links.
 *
 * Class HtmlSanitizer
 * @package App\Cms
 */
class HtmlSanitizer
{
    /**
     * @var SymfonyHtmlSanitizer
     */
    private $sanitizer;

    /**
     * @var string[]
     */
    private $imageHosts;

    /**
     * @var string[]
     */
    private $videoHosts;

    /**
     * @var string[]
     */
    private $classPrefixes;

    /**
     * Build from config/cms.php and config/centralStorage.php.
     * @return HtmlSanitizer
     */
    public static function fromConfig(): self
    {
        $imageHosts = config('cms.allowed_image_hosts', []);

        $storage = config('centralStorage.front') ?: config('centralStorage.server');
        $storageHost = $storage ? parse_url($storage, PHP_URL_HOST) : null;
        if ($storageHost) {
            $imageHosts[] = $storageHost;
        }

        return new self(config('cms.sanitizer'), $imageHosts, config('cms.video_hosts', []));
    }

    /**
     * @param array $config config('cms.sanitizer')
     * @param string[] $imageHosts hosts <img src> may point at
     * @param string[] $videoHosts hosts <iframe src> may point at
     */
    public function __construct(array $config, array $imageHosts, array $videoHosts)
    {
        $this->imageHosts = $this->normaliseHosts($imageHosts);
        $this->videoHosts = $this->normaliseHosts($videoHosts);
        $this->classPrefixes = $config['class_prefixes'] ?? [];

        $symfonyConfig = (new HtmlSanitizerConfig())
            ->allowLinkSchemes($config['link_schemes'] ?? [ 'http', 'https', 'mailto', 'tel' ])
            ->allowRelativeLinks(true)
            ->allowMediaSchemes([ 'https' ])
            ->allowMediaHosts(array_values(array_unique(array_merge($this->imageHosts, $this->videoHosts))))
            ->allowRelativeMedias(false)
            ->forceHttpsUrls(false)
            ->withMaxInputLength($config['max_input_length'] ?? 2 * 1024 * 1024);

        // h1 is let through here and demoted to h2 in the DOM pass.
        $elements = $config['elements'] ?? [];
        $elements['h1'] = [];

        foreach ($elements as $element => $attributes) {
            $symfonyConfig = $symfonyConfig->allowElement($element, array_merge($attributes, [ 'class' ]));
        }

        $this->sanitizer = new SymfonyHtmlSanitizer($symfonyConfig);
    }

    /**
     * @param string|null $html
     * @return string
     */
    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $html = $this->sanitizer->sanitize($html);
        if (trim($html) === '') {
            return '';
        }

        return $this->postProcess($html);
    }

    /**
     * Is this an https URL on our central storage or an approved image host?
     * @param string $url
     * @return bool
     */
    public function isAllowedImageUrl(string $url): bool
    {
        return $this->isOnHost($url, $this->imageHosts);
    }

    /**
     * Is this an https URL on one of the allowed video (iframe) hosts?
     * @param string $url
     * @return bool
     */
    public function isAllowedVideoUrl(string $url): bool
    {
        return $this->isOnHost($url, $this->videoHosts);
    }

    /**
     * @param string $html Output of the Symfony sanitizer (well formed).
     * @return string
     */
    private function postProcess(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div id="cms-sanitizer-root">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//div[@id="cms-sanitizer-root"]')->item(0);
        if (!$root) {
            return '';
        }

        // Copy to arrays first: removing nodes from a live list skips some.
        foreach (iterator_to_array($xpath->query('.//img', $root)) as $img) {
            if (!$this->isAllowedImageUrl($img->getAttribute('src'))) {
                $img->parentNode->removeChild($img);
            }
        }

        foreach (iterator_to_array($xpath->query('.//iframe', $root)) as $iframe) {
            if (!$this->isAllowedVideoUrl($iframe->getAttribute('src'))) {
                $iframe->parentNode->removeChild($iframe);
            }
        }

        foreach (iterator_to_array($xpath->query('.//*[@class]', $root)) as $element) {
            $this->filterClasses($element);
        }

        foreach (iterator_to_array($xpath->query('.//a[@target]', $root)) as $link) {
            if ($link->getAttribute('target') === '_blank') {
                $link->setAttribute('rel', 'noopener noreferrer');
            } else {
                $link->removeAttribute('target');
            }
        }

        foreach (iterator_to_array($xpath->query('.//h1', $root)) as $h1) {
            $this->renameElement($h1, 'h2');
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return $out;
    }

    /**
     * @param DOMElement $element
     */
    private function filterClasses(DOMElement $element)
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY);

        $kept = array_filter($classes, function ($class) {
            if (!preg_match('/^[A-Za-z0-9_\-]+$/', $class)) {
                return false;
            }
            foreach ($this->classPrefixes as $prefix) {
                if (strpos($class, $prefix) === 0) {
                    return true;
                }
            }
            return false;
        });

        if (count($kept) > 0) {
            $element->setAttribute('class', implode(' ', $kept));
        } else {
            $element->removeAttribute('class');
        }
    }

    /**
     * @param DOMElement $element
     * @param string $name
     */
    private function renameElement(DOMElement $element, string $name)
    {
        $replacement = $element->ownerDocument->createElement($name);

        foreach ($element->attributes as $attribute) {
            $replacement->setAttribute($attribute->nodeName, $attribute->nodeValue);
        }

        while ($element->firstChild) {
            $replacement->appendChild($element->firstChild);
        }

        $element->parentNode->replaceChild($replacement, $element);
    }

    /**
     * @param string $url
     * @param string[] $hosts
     * @return bool
     */
    private function isOnHost(string $url, array $hosts): bool
    {
        if (!preg_match('/^https:\/\//i', $url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        return in_array(strtolower($host), $hosts, true);
    }

    /**
     * @param string[] $hosts
     * @return string[]
     */
    private function normaliseHosts(array $hosts): array
    {
        $out = [];
        foreach ($hosts as $host) {
            $host = strtolower(trim((string) $host));
            if ($host !== '') {
                $out[] = $host;
            }
        }
        return array_values(array_unique($out));
    }
}
