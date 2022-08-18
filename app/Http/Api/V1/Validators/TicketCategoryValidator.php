<?php


namespace App\Http\Api\V1\Validators;

use App\Models\Event;
use CatLab\Charon\Models\Values\ChildrenValue;
use CatLab\Charon\Validation\ResourceValidator;
use CatLab\Requirements\Exceptions\RequirementValidationException;
use CatLab\Requirements\Exceptions\ValidatorValidationException;
use CatLab\Requirements\Models\Message;

/**
 *
 */
class TicketCategoryValidator extends ResourceValidator
{
    private $message;

    /**
     * @param $value
     * @return mixed
     * @throws RequirementValidationException
     */
    public function validate($value)
    {
        $this->checkTicketCategoryDates($value);
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
    protected function checkTicketCategoryDates($value)
    {
        $eventId = \Request::route('event');
        $event = Event::findOrFail($eventId);

        if ($event->event_type === Event::TYPE_PACKAGE) {
            // packages don't need dates.
            return;
        }

        /** @var ChildrenValue $dates */
        $dates = $value->getProperties()->getFromName('eventDates');

        if (count($dates->getChildren()) === 0) {
            $this->message = new Message('Selecteer minstens 1 datum.', null, null);
            throw ValidatorValidationException::make($this, $value);
        }
    }
}
