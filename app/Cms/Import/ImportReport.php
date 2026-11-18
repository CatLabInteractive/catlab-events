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
 * What an import run did (or, on a dry run, would do), printed by the
 * wordpress:import command.
 *
 * Class ImportReport
 * @package App\Cms\Import
 */
class ImportReport
{
    /**
     * "created post 12 → /2019/03/14/slug" lines.
     * @var string[]
     */
    public $items = [];

    /**
     * @var string[]
     */
    public $media = [];

    /**
     * Elements the sanitiser removed, per item: "post 12: iframe ×1".
     * @var string[]
     */
    public $dropped = [];

    /**
     * Sitemap URLs with no imported page or post behind them.
     * @var string[]
     */
    public $unmapped = [];

    /**
     * @var string[]
     */
    public $warnings = [];
}
