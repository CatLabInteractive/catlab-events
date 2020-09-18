<?php

namespace App\UitDB\Exceptions;

use App\Exceptions\Exception;

/**
 * Class InvalidCard
 * @package App\UitPAS\Exceptions
 */
class InvalidEventException extends UitPASException
{
    /**
     * @return InvalidEventException
     */
    public static function make()
    {
        return new self('UitPAS is niet correct geconfigureerd voor dit evenement. Contacteer een administrator.');
    }
}
