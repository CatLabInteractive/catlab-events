<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Http\Api\V1\ResourceDefinitions\Events\EventResourceDefinition;
use App\Models\Score;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class ScoreResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class ScoreResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Score::class);

        $this->identifier('id');

        $this->field('event.name')
            ->visible(true)
            ->display('event')
        ;

        $this->field('score')
            ->visible(true);

        $this->field('position')
            ->visible(true);

        $this->relationship('event', EventResourceDefinition::class)
            ->one()
            ->visible(false, false);
    }
}