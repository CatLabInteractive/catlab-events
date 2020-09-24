<?php

namespace App\UitDB\Exceptions;

use App\Models\TicketCategory;

/**
 * Class UitPASAlreadyUsed
 * @package App\UitDB\Exceptions
 */
class UitPASAlreadyUsed extends UitPASException
{
    /**
     * @param TicketCategory $ticketCategory
     * @return PriceClassNotFound
     */
    public static function make(TicketCategory $ticketCategory)
    {
        return new PriceClassNotFound('Deze UiTPas is reeds gebruikt voor een andere ticket. Contacteer een administrator.');
    }
}
