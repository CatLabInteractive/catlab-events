<?php


namespace App\Http\Api\V1\ResourceDefinitions;


use App\Http\Api\V1\ResourceDefinitions\Events\TicketCategoryResourceDefinition;
use App\Models\Order;
use CatLab\Charon\Models\ResourceDefinition;
use CatLab\Charon\Transformers\DateTransformer;

class OrderResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Order::class);

        $this->identifier('id');

        $this->field('event.name')
            ->display('eventName')
            ->visible(true);

        $this->field('group.name')
            ->display('groupName')
            ->visible(true);

        $this->field('created_at')
            ->transformer(DateTransformer::class)
            ->display('date')
            ->visible(true);

        $this->relationship('ticketCategory', TicketCategoryResourceDefinition::class)
            ->one()
            ->visible()
            ->linkable()
            ->expanded();
    }
}
