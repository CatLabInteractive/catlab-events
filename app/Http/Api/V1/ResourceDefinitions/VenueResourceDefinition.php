<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\Venue;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class VenueResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class VenueResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Venue::class);

        $this->identifier('id');

        $this->field('name')
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('url')
            ->visible()
            ->writeable(true, true);

        $this->field('address')
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('postalCode')
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('city')
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('country')
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('lat')
            ->number()
            ->visible(true)
            ->writeable(true, true)
            ->min(-90)
            ->max(90)
        ;

        $this->field('long')
            ->number()
            ->visible(true)
            ->writeable(true, true)
            ->min(-180)
            ->max(180)
        ;

        $this->field('description')
            ->string()
            ->visible()
            ->writeable(true, true)
        ;
    }
}