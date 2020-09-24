<?php

namespace App\UitDB\Exceptions;

use App\Models\TicketCategory;

/**
 * Class UitPASAlreadyUsed
 * @package App\UitDB\Exceptions
 */
class UiTPasGenericCardError extends UitPASException
{
    /**
     * @param TicketCategory $ticketCategory
     * @param $error
     * @return PriceClassNotFound
     */
    public static function make(TicketCategory $ticketCategory, $error)
    {
        return new PriceClassNotFound('UiTPas geeft geen recht tot kansentarief: ' . $error);
    }
}
