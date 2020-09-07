<?php

namespace App\UitPAS;

use App\Models\Event;
use App\UitPAS\Exceptions\InvalidCardException;

/**
 * Class UitPASVerifier
 * @package App\Tools
 */
class UitPASVerifier
{
    public function __construct()
    {

    }

    /**
     * @param Event $event
     * @param $cardNumber
     * @throws InvalidCardException
     */
    public function registerTicketSale(Event $event, $cardNumber)
    {
        throw new InvalidCardException('Je UitPAS kaartnummer kon niet worden herkend. Geef het nummer opnieuw in.');
    }


}
