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

namespace App\Http\Api\V1\Transformers;

use Carbon\Carbon;
use CatLab\Charon\Transformers\DateTransformer;

/**
 * Charon's date transformer (RFC 822 in and out, like the rest of the API),
 * but also accepting ISO 8601 input, and converting whatever offset the
 * client sends to the application timezone before Eloquent stores the wall
 * clock time. An unparseable value stays false, which the writers reject.
 *
 * Class AppTimezoneDateTransformer
 * @package App\Http\Api\V1\Transformers
 */
class AppTimezoneDateTransformer extends DateTransformer
{
    /**
     * @param mixed $value
     * @return Carbon|false|null
     */
    public function toParameterValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return false;
        }

        foreach ([ $this->formatIn ?? $this->format, DATE_ATOM, 'Y-m-d\TH:i:s.uP' ] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return Carbon::instance($date)->setTimezone(config('app.timezone'));
            }
        }

        return false;
    }
}
