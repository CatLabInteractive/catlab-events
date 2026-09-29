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

namespace App\Rules;

use CatLab\CentralStorage\Client\Models\Asset;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An assets.id that belongs to the given organisation. Assets are scoped per
 * organisation (assets.organisation_id), so a block or an og image can only
 * reference the organisation's own uploads; legacy assets without an
 * organisation are not selectable either.
 *
 * Class OrganisationAsset
 * @package App\Rules
 */
class OrganisationAsset implements ValidationRule
{
    /**
     * @var int
     */
    private $organisationId;

    /**
     * @param int $organisationId
     */
    public function __construct(int $organisationId)
    {
        $this->organisationId = $organisationId;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $exists = is_numeric($value) && Asset::query()
            ->where('id', '=', (int) $value)
            ->where('organisation_id', '=', $this->organisationId)
            ->exists();

        if (!$exists) {
            $fail('De gekozen afbeelding (:attribute) bestaat niet of hoort niet bij deze organisatie.');
        }
    }
}
