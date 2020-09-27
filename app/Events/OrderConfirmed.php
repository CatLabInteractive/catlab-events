<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Queue\SerializesModels;

class OrderConfirmed
{
    use SerializesModels;

    /**
     * @var Order
     */
    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }
}
