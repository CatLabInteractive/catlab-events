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

use App\Cms\Import\WordPressImporter;
use App\Models\Organisation;
use Illuminate\Console\Command;

/**
 * Class WordPressImport
 *
 * Copies the blog posts and pages of an organisation's WordPress site into
 * the CMS (see App\Cms\Import\WordPressImporter). Without --posts, --pages
 * or --redirects it does all three. Safe to run again: posts are updated,
 * pages that were imported before are kept as they are.
 *
 * @package App\Console\Commands
 */
class WordPressImport extends Command
{
    /**
     * @var string
     */
    protected $signature = 'wordpress:import
        {--site=https://www.quizfabriek.be : The WordPress site}
        {--organisation= : Organisation id to import into}
        {--posts : Import blog posts}
        {--pages : Import pages (unpublished, one text block each)}
        {--redirects : List sitemap URLs that do not map onto an import}
        {--skip=sko_milk : Comma-separated page slugs to leave out (slugs ending in -nieuw always are)}
        {--home= : Slug of the WordPress page to make the home page}
        {--dry-run : Show what would happen without writing anything}';

    /**
     * @var string
     */
    protected $description = 'Import posts, pages and media from a WordPress site through its REST API';

    /**
     * @return int
     */
    public function handle()
    {
        $organisation = Organisation::find((int) $this->option('organisation'));
        if (!$organisation) {
            $this->error('Organisation not found: pass --organisation=<id>.');
            return 1;
        }

        $site = (string) $this->option('site');
        $scheme = parse_url($site, PHP_URL_SCHEME);
        if (!in_array($scheme, [ 'http', 'https' ], true) || !parse_url($site, PHP_URL_HOST)) {
            $this->error('--site must be an http(s) URL.');
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $all = !$this->option('posts') && !$this->option('pages') && !$this->option('redirects');
        $skip = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('skip')))));
        $home = $this->option('home') !== null && $this->option('home') !== '' ? (string) $this->option('home') : null;

        $this->info(($dryRun ? '[dry run] ' : '') . 'Importing ' . $site . ' into "' . $organisation->name . '"');

        $importer = new WordPressImporter($site);

        try {
            if ($all || $this->option('posts')) {
                $importer->importPosts($organisation, $dryRun);
            }
            if ($all || $this->option('pages')) {
                $importer->importPages($organisation, $skip, $home, $dryRun);
            }
            if ($all || $this->option('redirects')) {
                $importer->redirects($organisation);
            }
        } catch (\RuntimeException $e) {
            $this->printReport($importer);
            $this->error($e->getMessage());
            return 1;
        }

        $this->printReport($importer);

        return 0;
    }

    /**
     * @param WordPressImporter $importer
     */
    private function printReport(WordPressImporter $importer): void
    {
        $report = $importer->report();

        $sections = [
            'Items' => $report->items,
            'Media' => $report->media,
            'Removed by the sanitiser' => $report->dropped,
            'Sitemap URLs without a page, post or redirect' => $report->unmapped,
        ];
        foreach ($sections as $title => $lines) {
            if (!$lines) {
                continue;
            }
            $this->line('');
            $this->line('<comment>' . $title . ' (' . count($lines) . ')</comment>');
            foreach ($lines as $line) {
                $this->line('  ' . $line);
            }
        }

        if ($report->warnings) {
            $this->line('');
            $this->warn('Warnings (' . count($report->warnings) . ')');
            foreach ($report->warnings as $line) {
                $this->warn('  ' . $line);
            }
        }
    }
}
