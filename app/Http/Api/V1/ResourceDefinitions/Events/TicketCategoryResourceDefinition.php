<?php

namespace App\Http\Api\V1\ResourceDefinitions\Events;

use App\Models\TicketCategory;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class TicketCategoryResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class TicketCategoryResourceDefinition extends ResourceDefinition
{
    /**
     * TicketCategoryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(TicketCategory::class);

        // Identifier
        $this->identifier('id');

        // Name
        $this->field('name')
            ->visible(true)
            ->filterable()
            ->writeable(true, true)
            ->required()
            ->string()
            ->min(3);

        $this->field('price')
            ->visible(true)
            ->writeable(true, true)
            ->required()
            ->number();

        $this->field('max_tickets')
            ->visible(true)
            ->writeable(true, true)
            ->number();

        $this->field('start_date')
            ->visible(true)
            ->writeable(true, true)
            ->datetime();

        $this->field('end_date')
            ->visible(true)
            ->writeable(true, true)
            ->datetime();
    }
}