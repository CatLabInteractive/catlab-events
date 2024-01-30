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

namespace App\Http\Api\V1\Validators;

use App\Models\Group;
use CatLab\Charon\Validation\ResourceValidator;
use CatLab\Requirements\Exceptions\RequirementValidationException;
use CatLab\Requirements\Exceptions\ValidatorValidationException;
use CatLab\Requirements\Models\Message;

/**
 * Class GroupValidator
 * @package App\Http\Api\V1\Validators
 */
class GroupValidator extends ResourceValidator
{
    /**
     * @param $value
     * @return mixed
     * @throws RequirementValidationException
     */
    public function validate($value)
    {
        $this->checkUniqueGroupName($value);
    }

    /**
     * @param ValidatorValidationException $exception
     * @return Message
     */
    public function getErrorMessage(ValidatorValidationException $exception) : Message
    {
        return new Message('Woops, te laat. Je teamnaam is niet uniek, kies alsjeblieft een andere teamnaam.', null, null);
    }

    /**
     * @param $value
     * @throws RequirementValidationException
     */
    protected function checkUniqueGroupName($value)
    {
        // no name set? That will be caught by the 'required' validator
        if (!$value->getProperties()->getFromName('name')) {
            return;
        }

        $groupName = $value->getProperties()->getFromName('name')->getValue();

        // Look for a team with the same name (that has been active in the past 18 months)
        $existing = Group::similarName($groupName)
            ->leftJoin('orders', 'orders.group_id', '=', 'groups.id')
            ->where('orders.created_at', '>', date('Y-m-d', strtotime('-18 months')))
            ->first();

        if (isset($existing)) {
            // check if we are trying to edit ourselves
            if ($this->getOriginal() && $this->getOriginal()->id === $existing->id) {
                return;
            }

            // No? Error!
            throw ValidatorValidationException::make($this, $value);
        }
    }
}
