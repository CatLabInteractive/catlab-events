<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\Person;
use App\Models\Venue;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class PeopleResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class PersonResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Person::class);

        $this->identifier('id');

        $this->field('first_name')
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('last_name')
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('description')
            ->string()
            ->visible()
            ->writeable(true, true)
        ;
    }
}