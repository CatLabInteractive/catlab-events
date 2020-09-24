<?php

namespace App\UitDB\Exceptions;

use App\Models\TicketCategory;

/**
 * Class UitPASAlreadyUsed
 * @package App\UitDB\Exceptions
 */
class UiTPASInvalidCardStatus extends UitPASException
{
    /**
     * @param TicketCategory $ticketCategory
     * @return PriceClassNotFound
     */
    public static function make(TicketCategory $ticketCategory)
    {
        return new PriceClassNotFound('Deze UiTPas geeft geen recht meer tot het kortingstarief. Contacteer een baliehouder.');
    }
}
