<?php

namespace App\Services;

/**
 * Cleans the HTML an admin writes for a mail (a standard mail, a library
 * template, an event's own text) before it is stored, and before stored
 * HTML is shown in the editor: scripts, event handlers and javascript: links
 * go, formatting stays. The {{ placeholders }} are kept as written, also
 * inside a link.
 *
 * The mail layout is a whole HTML document and is only ever shown in a
 * sandboxed frame; it is not cleaned here.
 *
 * @package App\Services
 */
class MailHtml
{
    /**
     * @var \HTMLPurifier|null
     */
    private static $purifier;

    /**
     * @param string|null $html
     * @return string|null
     */
    public static function clean($html)
    {
        if (!is_string($html) || trim($html) === '') {
            return $html;
        }

        // A placeholder is not a valid url, so it is swapped for a word the
        // purifier leaves alone and put back afterwards.
        $placeholders = [];
        $html = preg_replace_callback(Placeholders::PATTERN, function ($matches) use (&$placeholders) {
            $token = 'mailfield' . count($placeholders) . 'x';
            $placeholders[$token] = '{{ ' . $matches[1] . ' }}';
            return $token;
        }, $html);

        $clean = self::purifier()->purify($html);

        // Longest tokens first: mailfield1x must not eat the start of mailfield10x.
        return strtr($clean, $placeholders);
    }

    /**
     * @return \HTMLPurifier
     */
    private static function purifier()
    {
        if (self::$purifier === null) {
            $cachePath = storage_path('framework/cache/htmlpurifier');
            if (!is_dir($cachePath)) {
                @mkdir($cachePath, 0775, true);
            }

            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', $cachePath);
            $config->set('Attr.AllowedFrameTargets', [ '_blank' ]);
            $config->set('URI.AllowedSchemes', [ 'http' => true, 'https' => true, 'mailto' => true, 'tel' => true ]);

            self::$purifier = new \HTMLPurifier($config);
        }

        return self::$purifier;
    }
}
