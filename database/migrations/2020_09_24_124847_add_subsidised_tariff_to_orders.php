<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSubsidisedTariffToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->float('subsidised_tariff')->after('user_id')->nullable();
            $table->string('uitpas_sale_id')->after('catlab_order_id')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropColumn('subsidised_tariff');
            $table->dropColumn('uitpas_sale_id');

        });
    }
}
