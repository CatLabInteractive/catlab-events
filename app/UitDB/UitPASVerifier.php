<?php

namespace App\UitDB;

use App\Models\Event;
use App\UitDB\Exceptions\InvalidCardException;

/**
 * Class UitPASVerifier
 * @package App\Tools
 */
class UitPASVerifier
{
    /**
     * UitPASVerifier constructor.
     */
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
