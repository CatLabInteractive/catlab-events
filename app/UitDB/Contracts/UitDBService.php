<?php

namespace App\UitDB\Contracts;

use App\UitDB\UitPASVerifier;

/**
 * Interface UitDBService
 * @package App\UitDB\Contracts
 */
interface UitDBService
{
    public function getUitPasService(): UitPASVerifier;
}
