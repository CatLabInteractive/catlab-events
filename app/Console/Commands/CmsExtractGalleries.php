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

namespace App\Console\Commands;

use App\Cms\GalleryExtractor;
use App\Cms\PageWriter;
use App\Models\Organisation;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Turns the photos in the rich text blocks of existing pages (WordPress
 * galleries, runs of images) into gallery blocks. For pages imported before
 * the gallery block existed. Saves through the PageWriter, so the result is
 * validated like an editor's save; published pages stay published.
 *
 * Class CmsExtractGalleries
 * @package App\Console\Commands
 */
class CmsExtractGalleries extends Command
{
    protected $signature = 'cms:extract-galleries
        {--organisation= : Organisation id}
        {--page=* : Only these pages (id or path, e.g. quizzen-op-maat)}
        {--dry-run : Show what would change without saving}';

    protected $description = 'Move the photos in the text blocks of CMS pages into gallery blocks';

    public function handle(PageWriter $writer)
    {
        $organisation = Organisation::find((int) $this->option('organisation'));
        if (!$organisation) {
            $this->error('Organisation not found: pass --organisation=<id>.');
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $only = array_values(array_filter(array_map(function ($page) {
            return trim((string) $page, " /");
        }, (array) $this->option('page'))));

        $extractor = GalleryExtractor::forOrganisation($organisation);
        $changed = 0;

        $pages = Page::query()->where('organisation_id', '=', $organisation->id)->with('translations')->get();
        foreach ($pages as $page) {
            foreach ($page->translations as $translation) {
                if ($only && !in_array((string) $page->id, $only, true) && !in_array((string) $translation->path, $only, true)) {
                    continue;
                }

                $blocks = is_array($translation->blocks) ? $translation->blocks : [];
                $new = $extractor->extract($blocks);
                if ($new === $blocks) {
                    continue;
                }

                $label = '/' . $translation->path . ' (' . $translation->locale . ')';
                $summary = implode(', ', array_map(function ($block) {
                    return $block['type'] . ($block['type'] === 'gallery' ? ' (' . count($block['data']['items']) . ' photos)' : '');
                }, $new));

                if ($dryRun) {
                    $this->line('would change ' . $label . ': ' . $summary);
                    $changed++;
                    continue;
                }

                try {
                    $writer->saveTranslation($page, $translation->locale, [ 'blocks' => $new ]);
                } catch (ValidationException $e) {
                    $this->warn('skipped ' . $label . ': ' . implode(' ', array_merge(...array_values($e->errors()))));
                    continue;
                }
                $this->info('changed ' . $label . ': ' . $summary);
                $changed++;
            }
        }

        $this->line(($dryRun ? '[dry run] ' : '') . $changed . ' page translation(s) ' . ($dryRun ? 'would change' : 'changed') . '.');

        return 0;
    }
}
