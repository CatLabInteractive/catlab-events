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

namespace App\Tools;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Class StringHelper
 * @package App\Tools
 */
class StringHelper
{
    public static function htmlToText($input)
    {
        $input = str_replace('</p>', "\n\n", $input);
        $input = str_replace('<br>', "\n", $input);
        $input = str_replace('<br />', "\n", $input);
        $input = str_replace('<br/>', "\n", $input);

        $input = str_replace("\t", " ", $input);

        $input = strip_tags($input);
        $input = trim($input);

        // remove double spaces
        $input = preg_replace('/[\t\n\r\0\x0B]/', '', $input);
        $input = preg_replace('/([\s])\1+/', ' ', $input);
        $input = trim($input);

        return $input;
    }

    /**
     * @param \DateTime[] $dates
     */
    public static function datesToDescription(Collection $dates)
    {
        $dates = $dates->sort()->values();

        $parts = [];
        $hours = [];

        foreach ($dates as $index => $date) {

            // is the next date on the same day?
            $next = $dates[$index + 1] ?? null;
            if (!$next) {
                $format = '%A %-d %B %Y';
            } elseif ($next->format('m-Y') === $date->format('m-Y')) {
                $format = '%A %-d';
            } elseif ($next->format('m') === $date->format('m')) {
                $format = '%A %-d %B';
            } else {
                $format = '%A %-d %B';
            }

            $parts[] = $date->formatLocalized($format);
        }

        if (count($parts) === 1) {
            return Str::ucfirst($parts[0]);
        }

        $lastDate = array_pop($parts);
        return Str::ucfirst(implode(', ', $parts) . ' & ' . $lastDate);
    }
}
