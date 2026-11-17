<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Services;

use Illuminate\Support\HtmlString;

/**
 * Class Placeholders
 *
 * Fills {{ placeholders }} in text that admins write (mails, the mail
 * layout). Admins never write Blade, so this is the only templating they get.
 *
 * Values are plain strings (text, escaped in HTML) or HtmlString (trusted
 * HTML that the application rendered itself, e.g. a button block).
 *
 * @package App\Services
 */
class Placeholders
{
    /**
     * Unknown placeholders are left alone, so a typo shows up in the preview.
     */
    const PATTERN = '/\{\{\s*([a-z_.]+)\s*\}\}/i';

    /**
     * For HTML: strings are escaped, HtmlString values go in as they are.
     *
     * @param string $template
     * @param array $values placeholder => string|HtmlString
     * @return string
     */
    public static function fillHtml(string $template, array $values): string
    {
        return self::fill($template, $values, function ($value) {
            return $value instanceof HtmlString ? $value->toHtml() : e((string) $value);
        });
    }

    /**
     * For plain text such as a subject: strings go in as they are, the tags
     * of HtmlString values are stripped.
     *
     * @param string $template
     * @param array $values placeholder => string|HtmlString
     * @return string
     */
    public static function fillText(string $template, array $values): string
    {
        return self::fill($template, $values, function ($value) {
            return $value instanceof HtmlString ? trim(strip_tags($value->toHtml())) : (string) $value;
        });
    }

    /**
     * @param string $template
     * @param array $values
     * @param callable $format value => replacement
     * @return string
     */
    protected static function fill(string $template, array $values, callable $format): string
    {
        return preg_replace_callback(self::PATTERN, function ($matches) use ($values, $format) {
            if (!array_key_exists($matches[1], $values)) {
                return $matches[0];
            }
            return $format($values[$matches[1]]);
        }, $template);
    }
}
