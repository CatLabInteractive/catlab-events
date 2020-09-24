<?php

namespace App\UitDB\Exceptions;

use App\Models\TicketCategory;

/**
 * Class PriceClassNotFound
 * @package App\UitDB\Exceptions
 */
class PriceClassNotFound extends UitPASException
{
    /**
     * @param TicketCategory $ticketCategory
     * @return PriceClassNotFound
     */
    public static function make(TicketCategory $ticketCategory)
    {
        return new PriceClassNotFound('Fout! UitPAS ticket voor ' . $ticketCategory->name . ' is niet gevonden. Contacteer een administrator.');
    }
}
