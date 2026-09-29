<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * VIP registrations: orders an admin creates from the admin panel for guests
 * who never registered an account. They carry no user_id (already nullable),
 * so the guest's name and (optional) email live on the order itself.
 */
class AddVipToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->boolean('is_vip')->after('state')->default(false);
            $table->string('vip_name')->after('is_vip')->nullable();
            $table->string('vip_email')->after('vip_name')->nullable();
            $table->text('vip_notes')->after('vip_email')->nullable();

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
            $table->dropColumn([ 'is_vip', 'vip_name', 'vip_email', 'vip_notes' ]);
        });
    }
}
