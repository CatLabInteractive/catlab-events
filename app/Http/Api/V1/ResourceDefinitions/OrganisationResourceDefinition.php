<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\Organisation;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class OrganisationResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class OrganisationResourceDefinition extends ResourceDefinition
{
    /**
     * StoryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(Organisation::class);

        // Identifier
        $this->identifier('id');

        // Name
        $this->field('name')
            ->visible(true)
            ->filterable()
            ->writeable(true, true)
            ->required()
            ->string()
            ->min(3)
        ;


    }
}