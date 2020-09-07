<?php

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
        $groupName = $value->getProperties()->getFromName('name')->getValue();

        // Look for a team with the same name
        $existing = Group::similarName($groupName)->first();

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