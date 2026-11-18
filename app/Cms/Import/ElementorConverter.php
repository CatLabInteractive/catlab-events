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

use App\Cms\PageWriter;
use CatLab\CentralStorage\Client\Models\Asset;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Turns an Elementor page into CMS blocks, one or more per top-level
 * section, so an imported page starts out in the site's own design
 * instead of as one long text block:
 *
 * - the first section → hero (first heading, first text, its buttons);
 * - a section with a posts widget → latest_posts; a video widget → video;
 *   a hand-made list of events (buttons to /e/…) → upcoming_events;
 * - one image column next to a text column → text_image, or a reviews item
 *   when the text is a quote (“…”);
 * - columns (or repeated image + heading groups) → cards;
 * - a gallery → a rich_text gallery; a buttons-only section → the buttons
 *   join the previous block, or become a cta;
 * - anything else → rich_text (headings, text, lists, images, links).
 *
 * Consecutive reviews and cards blocks are merged. Icons, spacers,
 * dividers, social icons, anchors and custom HTML are dropped. The result
 * still goes through the PageWriter's validation; the importer falls back
 * to a single rich_text block when it does not pass.
 *
 * Class ElementorConverter
 * @package App\Cms\Import
 */
class ElementorConverter
{
    const SKIPPED_WIDGETS = [ 'spacer', 'divider', 'menu-anchor', 'social-icons', 'html', 'icon', 'shortcode', 'google_maps' ];

    /**
     * @var MediaUrl
     */
    private $site;

    /**
     * @var callable fn(string $uploadUrl): ?Asset
     */
    private $asset;

    /**
     * @var callable fn(string $html): string (upload URLs rewritten)
     */
    private $rewrite;

    /**
     * @var ElementorStyles
     */
    private $styles;

    /**
     * @param MediaUrl $site
     * @param callable $asset fn(string $uploadUrl): ?Asset
     * @param callable $rewrite fn(string $html): string
     * @param ElementorStyles|null $styles the page's Elementor CSS (colours, photos)
     */
    public function __construct(MediaUrl $site, callable $asset, callable $rewrite, ?ElementorStyles $styles = null)
    {
        $this->site = $site;
        $this->asset = $asset;
        $this->rewrite = $rewrite;
        $this->styles = $styles ?? new ElementorStyles();
    }

    /**
     * The blocks for an Elementor page, or null when $html has no Elementor
     * sections (or nothing usable in them).
     * @param string $html
     * @return array|null
     */
    public function convert(string $html): ?array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $sections = $xpath->query('//*[@data-element_type="section" and contains(concat(" ", @class, " "), " elementor-top-section ")]');
        if ($sections->length === 0) {
            return null;
        }

        $blocks = [];
        $index = 0;
        foreach ($sections as $section) {
            $widgets = $this->widgets($xpath, $section);
            if (!$widgets) {
                continue;
            }
            $sectionId = (string) $section->getAttribute('data-id');
            $style = [
                'colour' => $this->styles->colour($sectionId),
                'image' => $this->styles->image($sectionId),
            ];
            foreach ($this->sectionBlocks($widgets, $index === 0, $blocks, $style) as $block) {
                // The section's colour becomes the block's band colour.
                if ($style['colour'] !== null && !isset($block['data']['colour']) && $block['type'] !== 'cta') {
                    $block['data']['colour'] = $style['colour'];
                }
                $blocks[] = $block;
            }
            $index++;
        }

        $blocks = $this->merge($blocks);

        return $blocks ? array_map(function (array $block) {
            return [ 'id' => PageWriter::newBlockId(), 'type' => $block['type'], 'data' => $block['data'] ];
        }, $blocks) : null;
    }

    /**
     * The usable widgets of a section in document order, each tagged with
     * the index of its top-level column.
     * @param DOMXPath $xpath
     * @param DOMElement $section
     * @return array[]
     */
    private function widgets(DOMXPath $xpath, DOMElement $section): array
    {
        $columns = [];
        foreach ($xpath->query('.//*[@data-element_type="column"]', $section) as $column) {
            if ($this->parentElement($column, [ 'column', 'section' ]) === $section) {
                $columns[] = $column;
            }
        }

        $widgets = [];
        $texts = [];
        foreach ($xpath->query('.//*[@data-element_type="widget"]', $section) as $element) {
            $type = explode('.', (string) $element->getAttribute('data-widget_type'))[0];
            if ($type === '' || in_array($type, self::SKIPPED_WIDGETS, true) || $this->hiddenOnDesktop($element, $section)) {
                continue;
            }

            $column = 0;
            foreach ($columns as $i => $candidate) {
                if ($this->contains($candidate, $element)) {
                    $column = $i;
                    break;
                }
            }

            $widget = $this->widget($xpath, $element, $type);

            // The same text twice in a section (a per-device copy) once.
            if ($widget && $widget['type'] === 'text-editor' && in_array($widget['text'], $texts, true)) {
                continue;
            }
            if ($widget && $widget['type'] === 'text-editor') {
                $texts[] = $widget['text'];
            }

            if ($widget) {
                $widget['column'] = $column;
                $widget['columns'] = count($columns);
                $widget['round'] = $this->styles->isRound((string) $element->getAttribute('data-id'));
                [ $widget['panel'], $widget['panel_colour'], $widget['inner'] ] = $this->panel($element, $section, $columns[$column] ?? null);
                $widgets[] = $widget;
            }
        }

        return $widgets;
    }

    /**
     * The nearest coloured column or inner section around a widget (below
     * the top section), and whether the widget sits in an inner section.
     * @param DOMElement $element
     * @param DOMElement $section
     * @param DOMElement|null $topColumn
     * @return array [ ?data-id, ?colour, bool inner ]
     */
    private function panel(DOMElement $element, DOMElement $section, ?DOMElement $topColumn): array
    {
        $panel = null;
        $colour = null;
        $inner = false;
        for ($node = $element->parentNode; $node instanceof DOMElement && $node !== $section; $node = $node->parentNode) {
            $type = $node->getAttribute('data-element_type');
            if ($type === 'section') {
                $inner = true;
            }
            if ($panel === null && in_array($type, [ 'column', 'section' ], true)) {
                $nodeColour = $this->styles->colour((string) $node->getAttribute('data-id'));
                if ($nodeColour !== null) {
                    $panel = $node === $topColumn ? 'column:' . $node->getAttribute('data-id') : (string) $node->getAttribute('data-id');
                    $colour = $nodeColour;
                }
            }
        }
        return [ $panel, $colour, $inner ];
    }

    /**
     * @param DOMXPath $xpath
     * @param DOMElement $element
     * @param string $type
     * @return array|null [ type, text, html, tag, images[], url, youtube ]
     */
    private function widget(DOMXPath $xpath, DOMElement $element, string $type): ?array
    {
        $widget = [ 'type' => $type, 'text' => '', 'html' => '', 'tag' => null, 'images' => [], 'url' => null, 'youtube' => null ];

        switch ($type) {
            case 'heading':
                $heading = $xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::p or self::div or self::span][contains(@class, "elementor-heading-title")]', $element)->item(0);
                $widget['text'] = $this->clean($heading ? $heading->textContent : $element->textContent);
                $widget['tag'] = $heading ? strtolower($heading->nodeName) : 'h2';
                return $widget['text'] === '' ? null : $widget;

            case 'text-editor':
                $container = $xpath->query('.//*[contains(@class, "elementor-widget-container")]', $element)->item(0) ?: $element;
                $widget['html'] = trim($this->innerHtml($container));
                $widget['text'] = $this->clean($container->textContent);
                return $widget['text'] === '' && strpos($widget['html'], '<img') === false ? null : $widget;

            case 'icon-list':
                $items = [];
                foreach ($xpath->query('.//*[contains(@class, "elementor-icon-list-text")]', $element) as $item) {
                    $text = $this->clean($item->textContent);
                    if ($text !== '') {
                        $items[] = '<li>' . e($text) . '</li>';
                    }
                }
                if (!$items) {
                    return null;
                }
                $widget['html'] = '<ul>' . implode('', $items) . '</ul>';
                $widget['text'] = $this->clean($element->textContent);
                return $widget;

            case 'button':
                $link = $xpath->query('.//a[@href]', $element)->item(0);
                $widget['text'] = $this->label($this->clean($element->textContent));
                $widget['url'] = $link ? $this->link($link->getAttribute('href')) : null;
                return $widget['text'] !== '' && $widget['url'] ? $widget : null;

            case 'image':
            case 'gallery':
            case 'image-gallery':
            case 'image-carousel':
                foreach ($xpath->query('.//img | .//a[contains(@class, "e-gallery-item")]', $element) as $node) {
                    $image = $this->image($xpath, $node);
                    if ($image) {
                        $widget['images'][] = $image;
                    }
                }
                if ($type !== 'image') {
                    $widget['type'] = 'gallery';
                }
                return $widget['images'] ? $widget : null;

            case 'video':
                $settings = json_decode((string) $element->getAttribute('data-settings'), true);
                $widget['youtube'] = is_array($settings) ? ($settings['youtube_url'] ?? null) : null;
                return $widget['youtube'] ? $widget : null;

            case 'posts':
            case 'archive-posts':
                $widget['type'] = 'posts';
                return $widget;

            default:
                // Unknown widget: keep its text as a paragraph.
                $widget['type'] = 'text-editor';
                $widget['text'] = $this->clean($element->textContent);
                $widget['html'] = $widget['text'] === '' ? '' : '<p>' . e($widget['text']) . '</p>';
                return $widget['text'] === '' ? null : $widget;
        }
    }

    /**
     * @param DOMXPath $xpath
     * @param DOMElement $node an img, or a gallery link
     * @return array|null [ asset, url, alt, href ]
     */
    private function image(DOMXPath $xpath, DOMElement $node): ?array
    {
        $href = null;
        if ($node->nodeName === 'a') {
            $src = $node->getAttribute('href');
        } else {
            $src = $node->getAttribute('src');
            if ($src === '' || stripos($src, 'data:') === 0) {
                $src = $node->getAttribute('data-src') ?: $node->getAttribute('data-lazy-src');
            }
            for ($parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
                if ($parent->nodeName === 'a') {
                    $href = $this->link($parent->getAttribute('href'));
                    break;
                }
                if ($parent->hasAttribute('data-widget_type')) {
                    break;
                }
            }
        }

        $url = $this->site->normalise($src);
        if ($url === null) {
            return null;
        }

        return [
            'asset' => ($this->asset)($url),
            'url' => $url,
            'alt' => $this->clean($node->getAttribute('alt')),
            'href' => $href !== null && $this->site->normalise($href) === null ? $href : null,
        ];
    }

    /**
     * @param array[] $widgets
     * @param bool $first
     * @param array[] $blocks the blocks so far (buttons-only sections join the last one)
     * @param array $style the section's [ colour, image ]
     * @return array[] [ [ type, data ], ... ]
     */
    private function sectionBlocks(array $widgets, bool $first, array &$blocks, array $style = [ 'colour' => null, 'image' => null ]): array
    {
        $types = array_column($widgets, 'type');
        $count = array_count_values($types);
        $headings = $this->ofType($widgets, 'heading');
        $images = array_merge(...array_map(function ($w) {
            return $w['images'];
        }, $this->ofType($widgets, 'image')));

        if (isset($count['posts'])) {
            return [ $this->block('latest_posts', [ 'title' => $this->limit($headings[0]['text'] ?? null, 120), 'limit' => 6 ]) ];
        }

        // Only buttons: add them to the previous block where it has room.
        if (count($count) === 1 && isset($count['button'])) {
            $buttons = $this->buttons($widgets, 2);
            $last = count($blocks) - 1;
            if ($last >= 0 && $blocks[$last]['type'] === 'hero' && count($blocks[$last]['data']['buttons']) + count($buttons) <= 3) {
                $blocks[$last]['data']['buttons'] = array_merge($blocks[$last]['data']['buttons'], $buttons);
                return [];
            }
            if ($last >= 0 && $blocks[$last]['type'] === 'text_image' && !$blocks[$last]['data']['button'] && count($buttons) === 1) {
                $blocks[$last]['data']['button'] = [ 'label' => $buttons[0]['label'], 'url' => $buttons[0]['url'] ];
                return [];
            }
            // After text: the buttons as links under it (a cta would repeat
            // the button's label as its title).
            if ($last >= 0 && $blocks[$last]['type'] === 'rich_text') {
                $html = $blocks[$last]['data']['html'];
                $new = array_filter($this->ofType($widgets, 'button'), function ($w) use ($html) {
                    return strpos($html, 'href="' . e($w['url']) . '"') === false;
                });
                $blocks[$last]['data']['html'] .= $this->html(array_values($new));
                return [];
            }
            return [ $this->block('cta', [ 'title' => $buttons[0]['label'], 'text' => '', 'background' => 'primary', 'buttons' => $buttons ]) ];
        }

        if ($first && $headings) {
            return $this->hero($widgets, $style['image'] ?? null);
        }

        // A hand-made list of events (buttons to /e/…): the live list instead,
        // and nothing for a list of past events.
        $eventLinks = array_filter($this->ofType($widgets, 'button'), function ($w) {
            return strpos((string) $w['url'], '/e/') === 0;
        });
        if (count($eventLinks) >= 2) {
            $title = $headings[0]['text'] ?? null;
            if ($title !== null && preg_match('/voorbij|vorige|past/i', $title)) {
                return [];
            }
            return [ $this->block('upcoming_events', [
                'title' => $this->limit($title !== null ? $this->label($title) : null, 120),
                'intro' => null,
                'limit' => 6,
                'event_type' => 'event',
                'show_sold_out' => true,
                'empty_text' => null,
            ]) ];
        }

        // A video in one column next to text: text with the video beside it.
        if (isset($count['video']) && $count['video'] === 1 && $widgets[0]['columns'] === 2) {
            $video = $this->ofType($widgets, 'video')[0];
            $textColumn = array_values(array_filter($widgets, function ($w) use ($video) {
                return $w['column'] !== $video['column'];
            }));
            $videoColumn = array_filter($widgets, function ($w) use ($video) {
                return $w['column'] === $video['column'];
            });
            if ($textColumn && count($videoColumn) === 1 && !$this->ofType($textColumn, 'image') && !$this->ofType($textColumn, 'gallery')) {
                $block = $this->textImage($textColumn, null, $video['column'] === 0 ? 'left' : 'right', $style['colour'] ?? null);
                $block['data']['youtube_url'] = $video['youtube'];
                return [ $block ];
            }
        }

        if (isset($count['video'])) {
            $out = [];
            $rest = array_values(array_filter($widgets, function ($w) {
                return $w['type'] !== 'video';
            }));
            if ($rest) {
                $out[] = $this->richText($rest);
            }
            foreach ($this->ofType($widgets, 'video') as $video) {
                $out[] = $this->block('video', [ 'title' => null, 'youtube_url' => $video['youtube'], 'caption' => null ]);
            }
            return $out;
        }

        $columns = $widgets[0]['columns'];

        // One column of images next to a column of text.
        if ($columns === 2 && count($images) >= 1) {
            $byColumn = [ 0 => [], 1 => [] ];
            foreach ($widgets as $widget) {
                $byColumn[$widget['column']][] = $widget;
            }
            foreach ([ 0 => 'left', 1 => 'right' ] as $imageColumn => $position) {
                $imageTypes = array_unique(array_column($byColumn[$imageColumn], 'type'));
                $textColumn = $byColumn[1 - $imageColumn];
                if ($imageTypes === [ 'image' ] && $textColumn && !in_array('image', array_column($textColumn, 'type'), true)) {
                    $image = $byColumn[$imageColumn][0]['images'][0];
                    return [ $this->textImage($textColumn, $image, $position, $style['colour'] ?? null) ];
                }
            }
        }

        // Coloured boxes (inner sections or columns) that each hold a
        // heading → cards as colour panels; the rest of the section follows.
        $panels = [];
        foreach ($widgets as $widget) {
            if ($widget['panel'] !== null && strpos($widget['panel'], 'column:') !== 0) {
                $panels[$widget['panel']][] = $widget;
            }
        }
        $panelItems = [];
        foreach ($panels as $group) {
            $item = $this->ofType($group, 'heading') ? $this->card($group) : null;
            if ($item) {
                $item['colour'] = $group[0]['panel_colour'];
                $panelItems[] = $item;
            }
        }
        if (count($panelItems) >= 2) {
            $inPanel = function ($w) use ($panels, $panelItems) {
                return $w['panel'] !== null && isset($panels[$w['panel']]) && $this->ofType($panels[$w['panel']], 'heading');
            };
            $before = [];
            $after = [];
            $seenPanel = false;
            foreach ($widgets as $widget) {
                if ($inPanel($widget)) {
                    $seenPanel = true;
                } elseif ($seenPanel) {
                    $after[] = $widget;
                } else {
                    $before[] = $widget;
                }
            }
            $beforeHeadings = $this->ofType($before, 'heading');
            $title = $beforeHeadings[0]['text'] ?? null;
            $intro = null;
            if ($beforeHeadings && count($before) <= 2) {
                $intro = isset($before[1]) ? $before[1]['text'] : null;
                $before = [];
            }
            $out = [];
            if ($before) {
                $out[] = $this->richText($before);
                $title = null;
            }
            $out[] = $this->block('cards', [
                'title' => $this->limit($title !== null ? $this->label($title) : null, 120),
                'intro' => $this->limit($intro, 500),
                'columns' => max(2, min(4, count($panelItems) > 4 ? 3 : count($panelItems))),
                'style' => 'panels',
                'items' => array_slice($panelItems, 0, 12),
            ]);
            if ($after) {
                $out[] = $this->richText($after);
            }
            return $out;
        }

        // Columns that each hold an image and a heading → cards.
        if ($columns >= 2 && count($images) >= 2) {
            $items = [];
            foreach (range(0, $columns - 1) as $column) {
                $group = array_values(array_filter($widgets, function ($w) use ($column) {
                    return $w['column'] === $column;
                }));
                $item = $this->card($group);
                if ($item) {
                    $items[] = $item;
                }
            }
            if (count($items) >= 2) {
                return [ $this->block('cards', [ 'title' => null, 'intro' => null, 'columns' => max(2, min(4, $columns)), 'items' => $items ]) ];
            }
        }

        // Repeated image + heading groups in one column (a team) → cards.
        $imageWidgets = $this->ofType($widgets, 'image');
        if (count($imageWidgets) >= 3) {
            $title = null;
            $groups = [];
            foreach ($widgets as $widget) {
                if ($widget['type'] === 'image') {
                    $groups[] = [ $widget ];
                } elseif ($groups) {
                    $groups[count($groups) - 1][] = $widget;
                } elseif ($widget['type'] === 'heading' && $title === null) {
                    $title = $widget['text'];
                }
            }
            $items = array_values(array_filter(array_map([ $this, 'card' ], $groups)));
            if (count($items) >= 3) {
                $round = count(array_filter($imageWidgets, function ($w) {
                    return $w['round'];
                })) * 2 >= count($imageWidgets);
                return [ $this->block('cards', [
                    'title' => $this->limit($title, 120),
                    'intro' => null,
                    'columns' => count($items) % 3 === 0 ? 3 : 4,
                    'style' => $round ? 'people' : 'tiles',
                    'items' => $items,
                ]) ];
            }
        }

        return [ $this->richText($widgets) ];
    }

    /**
     * @param array[] $widgets
     * @return array[]
     */
    private function hero(array $widgets, ?string $photo = null): array
    {
        $title = null;
        $subtitle = null;
        $used = [];
        foreach ($widgets as $i => $widget) {
            if ($title === null && $widget['type'] === 'heading') {
                $title = $widget['text'];
                $used[$i] = true;
            } elseif ($title !== null && $subtitle === null && $widget['type'] === 'text-editor' && mb_strlen($widget['text']) <= 300) {
                $subtitle = $widget['text'];
                $used[$i] = true;
            }
        }
        $buttons = [];
        foreach ($widgets as $i => $widget) {
            if ($widget['type'] === 'button' && count($buttons) < 3) {
                $buttons[] = [ 'label' => $this->limit($widget['text'], 40), 'url' => $widget['url'], 'style' => $buttons ? 'secondary' : 'primary' ];
                $used[$i] = true;
            }
        }

        $photoUrl = $photo !== null ? $this->site->normalise($photo) : null;
        $asset = $photoUrl !== null ? ($this->asset)($photoUrl) : null;

        $hero = $this->block('hero', [
            'title' => $this->limit($title, 120),
            'subtitle' => $subtitle,
            'image_id' => $asset ? $asset->id : null,
            'image_style' => $asset ? 'cover' : 'side',
            'align' => 'center',
            'buttons' => $buttons,
        ]);

        // What is left in an inner section of the hero is decoration (a
        // floating box, social icons) and goes.
        $rest = array_values(array_filter(array_diff_key($widgets, $used), function ($w) {
            return !$w['inner'];
        }));

        return $rest ? [ $hero, $this->richText($rest) ] : [ $hero ];
    }

    /**
     * @param array[] $widgets the text column
     * @param array|null $image
     * @param string $position
     * @param string|null $sectionColour
     * @return array
     */
    private function textImage(array $widgets, ?array $image, string $position, ?string $sectionColour = null): array
    {
        // The text column's own colour (when the section has another one).
        $panelColour = $widgets[0]['panel_colour'] ?? null;
        foreach ($widgets as $widget) {
            if ($widget['panel_colour'] !== $panelColour) {
                $panelColour = null;
            }
        }
        if ($panelColour === $sectionColour) {
            $panelColour = null;
        }

        $headings = $this->ofType($widgets, 'heading');
        $texts = $this->ofType($widgets, 'text-editor');

        // Quotes next to a photo: reviews (heading = who said it).
        $isQuote = function (array $w) {
            return (bool) preg_match('/^["“„\']/u', $w['text']);
        };
        if ($texts && count($headings) === count($texts) && count(array_filter($texts, $isQuote)) === count($texts)) {
            $items = [];
            foreach ($texts as $i => $text) {
                $items[] = [
                    'quote' => $this->limit(trim($text['text'], " \"“”„'"), 1000),
                    'author' => $this->limit($headings[$i]['text'], 120),
                    'source' => null,
                    'url' => null,
                    'image_id' => $i === 0 && $image && $image['asset'] ? $image['asset']->id : null,
                    'colour' => $panelColour,
                ];
            }
            return $this->block('reviews', [ 'title' => null, 'items' => $items ]);
        }

        $title = $headings ? $headings[0]['text'] : null;
        $rest = $headings ? array_values(array_filter($widgets, function ($w) use ($headings) {
            return $w !== $headings[0];
        })) : $widgets;
        $buttons = $this->buttons($rest, 1);
        $rest = array_values(array_filter($rest, function ($w) {
            return $w['type'] !== 'button';
        }));

        return $this->block('text_image', [
            'title' => $this->limit($title !== null ? $this->label($title) : null, 120),
            'html' => $this->html($rest),
            'image_id' => $image && $image['asset'] ? $image['asset']->id : null,
            'image_position' => $position,
            'panel_colour' => $panelColour,
            'button' => $buttons ? [ 'label' => $buttons[0]['label'], 'url' => $buttons[0]['url'] ] : null,
        ]);
    }

    /**
     * A card from an image + heading group, or null.
     * @param array[] $group
     * @return array|null
     */
    private function card(array $group): ?array
    {
        $headings = $this->ofType($group, 'heading');
        $image = null;
        foreach ($this->ofType($group, 'image') as $widget) {
            $image = $image ?? $widget['images'][0];
        }
        if (!$headings && !$image) {
            return null;
        }

        $title = $headings ? $headings[0]['text'] : ($image['alt'] ?: null);
        if ($title === null || $title === '') {
            return null;
        }

        $text = [];
        foreach ($group as $widget) {
            if ($widget['type'] === 'heading' && $widget !== $headings[0]) {
                $text[] = $this->label($widget['text']);
            } elseif ($widget['type'] === 'text-editor') {
                $text[] = $widget['text'];
            }
        }
        $buttons = $this->buttons($group, 1);

        return [
            'title' => $this->limit($this->label($title), 120),
            'text' => $text ? $this->limit(implode("\n", $text), 1000) : null,
            'icon' => null,
            'image_id' => $image && $image['asset'] ? $image['asset']->id : null,
            'url' => $buttons ? $buttons[0]['url'] : ($image['href'] ?? null),
        ];
    }

    /**
     * @param array[] $widgets
     * @return array
     */
    private function richText(array $widgets): array
    {
        return $this->block('rich_text', [ 'html' => $this->html($widgets) ]);
    }

    /**
     * The widgets as rich text: the first heading h2, later ones h3.
     * @param array[] $widgets
     * @return string
     */
    private function html(array $widgets): string
    {
        $html = '';
        $headingSeen = false;
        $links = [];
        $flushLinks = function () use (&$links, &$html) {
            if ($links) {
                $html .= '<p>' . implode(' · ', $links) . '</p>';
                $links = [];
            }
        };

        foreach ($widgets as $widget) {
            if ($widget['type'] !== 'button') {
                $flushLinks();
            }
            switch ($widget['type']) {
                case 'heading':
                    $tag = $headingSeen ? 'h3' : 'h2';
                    $headingSeen = true;
                    $html .= '<' . $tag . '>' . e($this->label($widget['text'])) . '</' . $tag . '>';
                    break;
                case 'text-editor':
                case 'icon-list':
                    $html .= ($this->rewrite)($widget['html']);
                    break;
                case 'button':
                    $links[] = '<a href="' . e($widget['url']) . '"><strong>' . e($widget['text']) . '</strong></a>';
                    break;
                case 'image':
                    foreach ($widget['images'] as $image) {
                        $html .= $this->img($image, 'figure');
                    }
                    break;
                case 'gallery':
                    $items = '';
                    foreach ($widget['images'] as $image) {
                        $items .= $this->img($image, 'li');
                    }
                    if ($items !== '') {
                        $html .= '<figure class="wp-block-gallery"><ul class="blocks-gallery-grid">' . $items . '</ul></figure>';
                    }
                    break;
            }
        }
        $flushLinks();

        return $html;
    }

    /**
     * @param array $image
     * @param string $wrapper figure or li
     * @return string
     */
    private function img(array $image, string $wrapper): string
    {
        if (!$image['asset']) {
            return '';
        }

        $img = '<img src="' . e($image['asset']->getUrl()) . '" alt="' . e($image['alt']) . '" loading="lazy">';

        return $wrapper === 'li'
            ? '<li class="blocks-gallery-item"><figure>' . $img . '</figure></li>'
            : '<figure>' . $img . '</figure>';
    }

    /**
     * @param array[] $widgets
     * @param int $max
     * @return array[] [ label, url, style ]
     */
    private function buttons(array $widgets, int $max): array
    {
        $buttons = [];
        foreach ($this->ofType($widgets, 'button') as $widget) {
            if (count($buttons) < $max) {
                $buttons[] = [ 'label' => $this->limit($widget['text'], 40), 'url' => $widget['url'], 'style' => $buttons ? 'secondary' : 'primary' ];
            }
        }
        return $buttons;
    }

    /**
     * Merge consecutive reviews blocks and consecutive cards blocks with the
     * same column count (within the block types' item limits).
     * @param array[] $blocks
     * @return array[]
     */
    private function merge(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            $last = count($out) - 1;
            if ($last >= 0 && $block['type'] === $out[$last]['type']) {
                if ($block['type'] === 'reviews' && count($out[$last]['data']['items']) + count($block['data']['items']) <= 30) {
                    $out[$last]['data']['items'] = array_merge($out[$last]['data']['items'], $block['data']['items']);
                    continue;
                }
                if ($block['type'] === 'cards'
                    && $block['data']['title'] === null
                    && $block['data']['columns'] === $out[$last]['data']['columns']
                    && count($out[$last]['data']['items']) + count($block['data']['items']) <= 12) {
                    $out[$last]['data']['items'] = array_merge($out[$last]['data']['items'], $block['data']['items']);
                    continue;
                }
            }
            $out[] = $block;
        }

        return $out;
    }

    /**
     * @param string $type
     * @param array $data
     * @return array
     */
    private function block(string $type, array $data): array
    {
        return [ 'type' => $type, 'data' => $data ];
    }

    /**
     * @param array[] $widgets
     * @param string $type
     * @return array[]
     */
    private function ofType(array $widgets, string $type): array
    {
        return array_values(array_filter($widgets, function ($w) use ($type) {
            return $w['type'] === $type;
        }));
    }

    /**
     * Site links become paths (the ticket site moves onto the main domain);
     * other links stay as they are.
     * @param string $href
     * @return string|null
     */
    private function link(string $href): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || $href === '#') {
            return null;
        }

        $host = strtolower((string) parse_url($href, PHP_URL_HOST));
        $domain = preg_replace('/^www\./', '', (string) parse_url($this->site->origin(), PHP_URL_HOST));
        if ($host !== '' && ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain)) {
            if ($this->site->normalise($href) !== null) {
                return $href;
            }
            $path = rtrim((string) parse_url($href, PHP_URL_PATH), '/');
            $query = parse_url($href, PHP_URL_QUERY);
            return ($path === '' ? '/' : $path) . ($query ? '?' . $query : '');
        }

        return $href;
    }

    /**
     * ALL CAPS (an Elementor style, not the text) becomes sentence case.
     * @param string $text
     * @return string
     */
    private function label(string $text): string
    {
        if (mb_strlen($text) > 2 && $text === mb_strtoupper($text) && $text !== mb_strtolower($text)) {
            $lower = mb_strtolower($text);
            return mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1);
        }

        // 'komende evenementen' (shown in capitals by CSS) starts with a capital.
        if (preg_match('/^\p{Ll}/u', $text)) {
            return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
        }

        return $text;
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

        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    /**
     * @param string $text
     * @return string
     */
    private function clean(string $text): string
    {
        return trim(preg_replace('/[\s\x{200B}\x{00A0}]+/u', ' ', $text));
    }

    /**
     * @param DOMElement $element
     * @return string
     */
    private function innerHtml(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    /**
     * Elementor duplicates content per device; the desktop version is kept.
     * @param DOMElement $element
     * @param DOMElement $section
     * @return bool
     */
    private function hiddenOnDesktop(DOMElement $element, DOMElement $section): bool
    {
        for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
            if (strpos(' ' . $node->getAttribute('class') . ' ', ' elementor-hidden-desktop ') !== false) {
                return true;
            }
            if ($node === $section) {
                break;
            }
        }
        return false;
    }

    /**
     * The nearest ancestor whose data-element_type is one of $types.
     * @param DOMElement $element
     * @param string[] $types
     * @return DOMElement|null
     */
    private function parentElement(DOMElement $element, array $types): ?DOMElement
    {
        for ($parent = $element->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if (in_array($parent->getAttribute('data-element_type'), $types, true)) {
                return $parent;
            }
        }
        return null;
    }

    /**
     * @param DOMElement $ancestor
     * @param DOMElement $element
     * @return bool
     */
    private function contains(DOMElement $ancestor, DOMElement $element): bool
    {
        for ($node = $element->parentNode; $node; $node = $node->parentNode) {
            if ($node === $ancestor) {
                return true;
            }
        }
        return false;
    }
}
