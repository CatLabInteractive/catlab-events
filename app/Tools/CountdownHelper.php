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

/**
 * Class CountdownHelper
 * @package App\Tools
 */
class CountdownHelper
{
    /**
     * @param \DateTime $dateTime
     * @return array
     */
    public static function getCountdown(\DateTime $dateTime)
    {
        $time = $dateTime->getTimestamp() - time();
        if ($time < 0) {
            return [
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'seconds' => 0
            ];
        }

        $out = [];
        $out['days'] = floor($time / (24 * 60 * 60));
        $time -= $out['days'] * 24 * 60 * 60;

        $out['hours'] = floor($time / (60 * 60));
        $time -= $out['hours'] * 60 * 60;

        $out['minutes'] = floor($time / (60));
        $time -= $out['minutes'] * 60;

        $out['seconds'] = $time;

        if ($out['days'] < 10) {
            $out['days'] = '0' . $out['days'];
        }

        if ($out['hours'] < 10) {
            $out['hours'] = '0' . $out['hours'];
        }

        if ($out['minutes'] < 10) {
            $out['minutes'] = '0' . $out['minutes'];
        }

        if ($out['seconds'] < 10) {
            $out['seconds'] = '0' . $out['seconds'];
        }

        return $out;
    }
}
