<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2021 Thijs Van der Schaeghe
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

use App\Models\Organisation;
use CatLab\Charon\Models\Values\ChildValue;
use CatLab\Charon\Validation\ResourceValidator;
use CatLab\Requirements\Exceptions\RequirementValidationException;
use CatLab\Requirements\Exceptions\ValidatorValidationException;
use CatLab\Requirements\Models\Message;

/**
 * Class LiveStreamValidator
 * @package App\Http\Api\V1\Validators
 */
class EventValidator extends ResourceValidator
{
    /**
     * @var Message
     */
    private $message;

    /**
     * @param $value
     * @return mixed
     * @throws RequirementValidationException
     */
    public function validate($value)
    {
        $this->checkUiTDBID($value);
    }

    /**
     * @param ValidatorValidationException $exception
     * @return Message
     */
    public function getErrorMessage(ValidatorValidationException $exception) : Message
    {
        return $this->message;
    }

    /**
     * @param $value
     * @throws RequirementValidationException
     */
    protected function checkUiTDBID($value)
    {
        // only validate on publish.
        $published = $value->getProperties()->getFromName('is_published');
        if (!$published || !$published->getValue()) {
            return;
        }

        $organisation = Organisation::getRepresentedOrganisation();
        if (!$organisation->uitpas) {
            return;
        }

        // no venue? Then no problem.

        /** @var ChildValue $venue */
        $venue = $value->getProperties()->getFromName('venue');
        if ($venue->isEmpty()) {
            return;
        }

        $uitDbId = $value->getProperties()->getFromName('uitdb_event_id');
        if (!$uitDbId->getValue()) {
            $this->message = new Message('Kan niet publiceren: vul een UiTdatabank ID in om UiTPas kansentarief aan te kunnen bieden.');
            throw ValidatorValidationException::make($this, $value);
        }
    }

    /**
     * @param $value
     * @return bool
     */
    protected function hasStreamingPlatformSet($resource)
    {
        $toCheck = [
            'twitch_key',
            'youtube_video',
            'redirect_uri'
        ];

        foreach ($toCheck as $v) {
            $streamingParameter = $resource->getProperties()->getFromName($v);
            if ($streamingParameter) {
                $value = $streamingParameter->getValue();
                if (!empty($value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
