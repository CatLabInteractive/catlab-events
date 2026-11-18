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

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A URL that is safe to put in an href: http(s)://host..., mailto:, tel:,
 * or a local path starting with '/' or '#'. Everything else (javascript:,
 * data:, protocol-relative //host, ...) is rejected.
 *
 * Class SafeUrl
 * @package App\Rules
 */
class SafeUrl implements ValidationRule
{
    /**
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!self::isSafe($value)) {
            $fail('Het veld :attribute moet een http(s)-, mailto:- of tel:-link zijn, of een pad dat met / of # begint.');
        }
    }

    /**
     * @param mixed $url
     * @return bool
     */
    public static function isSafe($url): bool
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        // No whitespace or control characters anywhere: browsers strip them
        // and that is how 'java\nscript:' tricks work.
        if (preg_match('/[\x00-\x20\x7F]/', $url)) {
            return false;
        }

        // Fragment.
        if ($url[0] === '#') {
            return true;
        }

        // Local path, but not protocol-relative ('//host', '/\host').
        if ($url[0] === '/') {
            return !isset($url[1]) || ($url[1] !== '/' && $url[1] !== '\\');
        }

        if (preg_match('/^(mailto|tel):.+$/i', $url)) {
            return true;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            $host = parse_url($url, PHP_URL_HOST);
            return is_string($host) && $host !== '';
        }

        return false;
    }
}
