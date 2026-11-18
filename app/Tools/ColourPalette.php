<?php

namespace App\Tools;

/**
 * The series colours of an organisation (the "Kleurveld" design).
 *
 * An organisation lists its colours as text, separated by commas, spaces or
 * new lines. Each entry is a field colour, optionally followed by "/" and the
 * colour of the circles on that field:
 *
 *     #61cc6f/#fcc765, #5e2fff/#fcc765, #fcc765/#5e2fff, #bf8076
 *
 * Without a circle colour, a light tint of the field is used. The text colour
 * on each field (dark ink or white) is picked for contrast. Anything that is
 * not a hex colour is ignored, so the value can safely go into a style tag.
 */
class ColourPalette
{
    const INK = '#1f3326';
    const WHITE = '#ffffff';
    const PAPER = '#fbfaf5';

    const DEFAULT_COLOURS = '#61cc6f/#fcc765, #5e2fff/#fcc765, #fcc765/#5e2fff, #bf8076/#f8e6c6, #4a6a9a/#fcc765, #58998d/#f8e6c6';

    /**
     * @var array[] list of [ 'field' => hex, 'circle' => hex, 'text' => hex ]
     */
    private $fields;

    /**
     * @param string|null $definition
     */
    public function __construct(?string $definition = null)
    {
        $this->fields = self::parse($definition ?? '');
        if (count($this->fields) === 0) {
            $this->fields = self::parse(self::DEFAULT_COLOURS);
        }
    }

    /**
     * @return array[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->fields);
    }

    /**
     * The field class for a series (or anything else with a numeric id).
     * @param int|null $id
     * @return string
     */
    public function getClassFor(?int $id): string
    {
        return 'kv-field-' . ((int) $id % $this->count());
    }

    /**
     * CSS that defines .kv-field-N for every colour, plus --kv-c0.. on :root
     * for places that cycle through the palette (ticket tiles, footer dots).
     * @return string
     */
    public function toCss(): string
    {
        $root = [];
        $classes = [];
        foreach ($this->fields as $i => $field) {
            $root[] = "--kv-c{$i}:{$field['field']};--kv-c{$i}-text:{$field['text']}";
            $classes[] = ".kv-field-{$i}{--kv-field:{$field['field']};--kv-field-2:{$field['circle']};--kv-on-field:{$field['text']}}";
        }

        // Cycle the first colours so --kv-c0..--kv-c3 always exist.
        for ($i = count($this->fields); $i < 4; $i ++) {
            $source = $this->fields[$i % count($this->fields)];
            $root[] = "--kv-c{$i}:{$source['field']};--kv-c{$i}-text:{$source['text']}";
        }

        $first = $this->fields[0];
        $root[] = "--kv-field:{$first['field']};--kv-field-2:{$first['circle']};--kv-on-field:{$first['text']}";

        return ':root{' . implode(';', $root) . '}' . implode('', $classes);
    }

    /**
     * @param string $definition
     * @return array[]
     */
    public static function parse(string $definition): array
    {
        $fields = [];
        foreach (preg_split('/[\s,;]+/', $definition, -1, PREG_SPLIT_NO_EMPTY) as $entry) {
            $parts = explode('/', $entry, 2);
            $field = self::normaliseHex($parts[0]);
            if ($field === null) {
                continue;
            }

            $circle = isset($parts[1]) ? self::normaliseHex($parts[1]) : null;
            if ($circle === null) {
                $circle = self::mix($field, self::PAPER, 0.55);
            }

            $fields[] = [
                'field' => $field,
                'circle' => $circle,
                'text' => self::textColourFor($field),
            ];
        }
        return $fields;
    }

    /**
     * Dark ink or white, whichever reads better on the colour.
     * @param string $hex
     * @return string
     */
    public static function textColourFor(string $hex): string
    {
        $background = self::luminance($hex);
        $onInk = (max($background, self::luminance(self::INK)) + 0.05) / (min($background, self::luminance(self::INK)) + 0.05);
        $onWhite = 1.05 / ($background + 0.05);
        return $onInk >= $onWhite ? self::INK : self::WHITE;
    }

    /**
     * @param string $value
     * @return string|null "#rrggbb" in lower case, or null when it is not a hex colour
     */
    public static function normaliseHex(string $value): ?string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/', $value, $matches)) {
            return null;
        }
        $hex = $matches[1];
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return '#' . $hex;
    }

    /**
     * @param string $hex
     * @return float WCAG relative luminance
     */
    private static function luminance(string $hex): float
    {
        $channels = [];
        foreach (self::rgb($hex) as $value) {
            $value /= 255;
            $channels[] = $value <= 0.03928 ? $value / 12.92 : pow(($value + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * @param string $a
     * @param string $b
     * @param float $amountOfB
     * @return string
     */
    private static function mix(string $a, string $b, float $amountOfB): string
    {
        $from = self::rgb($a);
        $to = self::rgb($b);
        $out = '#';
        for ($i = 0; $i < 3; $i ++) {
            $out .= sprintf('%02x', (int) round($from[$i] + ($to[$i] - $from[$i]) * $amountOfB));
        }
        return $out;
    }

    /**
     * @param string $hex
     * @return int[]
     */
    private static function rgb(string $hex): array
    {
        return [ hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)) ];
    }
}
