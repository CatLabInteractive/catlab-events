<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTrackStatusToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function(Blueprint $table) {

            $table->tinyInteger('tracker_sent')->after('pay_url')->default(0);
            $table->tinyInteger('reminder_sent')->after('pay_url')->default(0);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function(Blueprint $table) {

            $table->dropColumn('tracker_sent');
            $table->dropColumn('reminder_sent');

        });
    }
}
