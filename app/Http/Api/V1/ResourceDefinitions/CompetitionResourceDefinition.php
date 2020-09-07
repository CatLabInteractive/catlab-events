<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\Competition;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class CompetitionResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class CompetitionResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Competition::class);

        $this->identifier('id');

        $this->field('name')
            ->required()
            ->visible(true)
            ->writeable(true, true);
    }
}