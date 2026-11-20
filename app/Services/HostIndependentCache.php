<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache::remember() for values that hold absolute URLs of this site. An
 * organisation can have several domains, and url() builds links on the host
 * of the current request. Without this, the domain that filled a
 * per-organisation cache would show up in the links of every other domain
 * until the cache expired.
 *
 * Strings that start with the site root are stored with a placeholder for the
 * root, and read back with the root of the current request. Other values
 * (external links, numbers, dates) are kept as they are.
 */
class HostIndependentCache
{
    const ROOT = "\0site-root\0";

    /**
     * @param string $key
     * @param \DateTimeInterface|\DateInterval|int|null $ttl
     * @param Closure $callback
     * @return mixed
     */
    public static function remember(string $key, $ttl, Closure $callback)
    {
        $stored = Cache::remember($key, $ttl, function () use ($callback) {
            return self::replace($callback(), url('/'), self::ROOT);
        });

        return self::replace($stored, self::ROOT, url('/'));
    }

    /**
     * Replace the prefix $from by $to in every string of $value (arrays
     * recursively). Only a whole root matches: the root of
     * http://www.example.test is not the start of http://www.example.test.be.
     * @param mixed $value
     * @param string $from
     * @param string $to
     * @return mixed
     */
    protected static function replace($value, string $from, string $to)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::replace($item, $from, $to);
            }
            return $value;
        }

        if (!is_string($value) || strncmp($value, $from, strlen($from)) !== 0) {
            return $value;
        }

        $rest = substr($value, strlen($from));
        if ($rest !== '' && !in_array($rest[0], [ '/', '?', '#' ], true)) {
            return $value;
        }

        return $to . $rest;
    }
}
