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

namespace App\Cms\Blocks;

use App\Models\Organisation;
use App\Models\PageTranslation;
use Illuminate\Http\Request;

/**
 * What a block may need while preparing: the organisation (never query
 * content without it), the locale, the page translation and the request.
 *
 * Class BlockContext
 * @package App\Cms\Blocks
 */
class BlockContext
{
    /**
     * @var Organisation
     */
    public $organisation;

    /**
     * @var string
     */
    public $locale;

    /**
     * @var PageTranslation|null
     */
    public $translation;

    /**
     * @var Request|null
     */
    public $request;

    /**
     * @param Organisation $organisation
     * @param string $locale
     * @param PageTranslation|null $translation
     * @param Request|null $request
     */
    public function __construct(
        Organisation $organisation,
        string $locale,
        ?PageTranslation $translation = null,
        ?Request $request = null
    ) {
        $this->organisation = $organisation;
        $this->locale = $locale;
        $this->translation = $translation;
        $this->request = $request;
    }
}
