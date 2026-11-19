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

namespace App\Cms\Widgets;

use Carbon\Carbon;
use CatLab\CharonFrontend\Widgets\DateTimeWidget;
use CatLab\CharonFrontend\Widgets\FormField;

/**
 * The publication date of a blog post: a new post starts at "now", so it
 * isn't left without a date (and invisible) by accident.
 *
 * @package App\Cms\Widgets
 */
class PublicationDateWidget extends DateTimeWidget
{
    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        $data = parent::getViewData($field);

        if (!$field->getResource() && !$field->hasOldInput()) {
            $now = Carbon::now(config('app.timezone'));
            $data['date'] = $now->format('Y-m-d');
            $data['time'] = $now->format('H:i');
        }

        return $data;
    }
}
