<?php

use App\Models\Order;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SendReminderEmails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*
        $orders = Order::leftJoin('events', 'events.id', '=', 'orders.event_id')
            ->where('orders.state', '=', Order::STATE_ACCEPTED)
            ->where('events.endDate', '>', new \DateTime())
            ->get();

        foreach ($orders as $order) {
            $order->onConfirmation();
        }*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
