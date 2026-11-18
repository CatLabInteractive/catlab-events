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

/**
 * Recognises URLs of a WordPress site's uploads (`/wp-content/uploads/…`)
 * in absolute, protocol-relative or root-relative form, on the site's host
 * with or without `www.`. Anything else is not ours to fetch.
 *
 * Class MediaUrl
 * @package App\Cms\Import
 */
class MediaUrl
{
    const UPLOADS = '/wp-content/uploads/';

    /**
     * @var string scheme://host of the site, no trailing slash
     */
    private $origin;

    /**
     * @var string
     */
    private $host;

    /**
     * @param string $site e.g. https://www.quizfabriek.be
     */
    public function __construct(string $site)
    {
        $parts = parse_url(trim($site));
        if (!$parts || empty($parts['host'])) {
            throw new \InvalidArgumentException('Not a site URL: ' . $site);
        }

        $this->host = strtolower($parts['host']);
        $this->origin = ($parts['scheme'] ?? 'https') . '://' . $this->host;
    }

    /**
     * @return string scheme://host
     */
    public function origin(): string
    {
        return $this->origin;
    }

    /**
     * The absolute URL (on the site's own origin, no query or fragment) of an
     * upload, or null when $url is not an upload of this site.
     * @param string|null $url
     * @return string|null
     */
    public function normalise(?string $url): ?string
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return null;
        }

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } elseif (strpos($url, '/') === 0) {
            $url = $this->origin . $url;
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), [ 'http', 'https' ], true)) {
            return null;
        }

        if ($this->bareHost(strtolower($parts['host'])) !== $this->bareHost($this->host)) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if (strpos($path, self::UPLOADS) !== 0 || strpos($path, '..') !== false) {
            return null;
        }

        return $this->origin . $path;
    }

    /**
     * The redirect path (no leading slash) of a normalised upload URL.
     * @param string $url
     * @return string
     */
    public function path(string $url): string
    {
        return ltrim((string) parse_url($url, PHP_URL_PATH), '/');
    }

    /**
     * The original upload behind a WordPress resize (`foto-1024x768.jpg` →
     * `foto.jpg`), or null when $url is not a resize.
     * @param string $url
     * @return string|null
     */
    public function original(string $url): ?string
    {
        $original = preg_replace('/-\d+x\d+(\.[A-Za-z0-9]+)$/', '$1', $url);

        return $original !== $url ? $original : null;
    }

    /**
     * @param string $host
     * @return string
     */
    private function bareHost(string $host): string
    {
        return strpos($host, 'www.') === 0 ? substr($host, 4) : $host;
    }
}
