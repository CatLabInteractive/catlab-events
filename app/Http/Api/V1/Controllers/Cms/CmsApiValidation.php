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

namespace App\Http\Api\V1\Controllers\Cms;

use CatLab\Requirements\Collections\MessageCollection;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Models\Message;
use Illuminate\Validation\ValidationException;

/**
 * For the CMS API controllers, whose writes go through App\Cms\PageWriter:
 * turn the writer's Laravel ValidationException into a Charon
 * ResourceValidationException (keyed by field, e.g. `blocks.2.data.title`)
 * and answer validation errors with 422.
 *
 * Trait CmsApiValidation
 * @package App\Http\Api\V1\Controllers\Cms
 */
trait CmsApiValidation
{
    /**
     * Run $callback and rethrow a writer validation error as a
     * ResourceValidationException.
     * @param callable $callback
     * @return mixed
     * @throws ResourceValidationException
     */
    protected function throughWriter(callable $callback)
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            throw $this->toResourceValidationException($e);
        }
    }

    /**
     * @param ValidationException $e
     * @return ResourceValidationException
     */
    protected function toResourceValidationException(ValidationException $e): ResourceValidationException
    {
        $messages = new MessageCollection();
        foreach ($e->errors() as $property => $propertyMessages) {
            foreach ($propertyMessages as $message) {
                $messages->add(new Message($message, null, (string) $property));
            }
        }

        return ResourceValidationException::make($messages);
    }

    /**
     * @param ResourceValidationException $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getValidationErrorResponse(ResourceValidationException $e)
    {
        return $this->toResponse([
            'error' => [
                'message' => 'Could not decode resource.',
                'issues' => $e->getMessages()->toMap(),
            ],
        ])->setStatusCode(422);
    }

    /**
     * Charon throws a bare Requirements ValidationException for a malformed
     * field (e.g. `blocks` that is not a list); answer it as a 422 too.
     * @param callable $callback
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function answerMalformedInput(callable $callback)
    {
        try {
            return $callback();
        } catch (ResourceValidationException $e) {
            return $this->getValidationErrorResponse($e);
        } catch (\CatLab\Requirements\Exceptions\ValidationException $e) {
            $messages = new MessageCollection();
            $messages->add(new Message($e->getMessage(), null, null));

            return $this->getValidationErrorResponse(ResourceValidationException::make($messages));
        }
    }

    /**
     * A refused delete (home page, child pages) as a 422.
     * @param ValidationException $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getRefusedResponse(ValidationException $e)
    {
        return $this->getValidationErrorResponse($this->toResourceValidationException($e));
    }
}
