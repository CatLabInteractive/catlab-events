<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest checkout (catlab-accounts spec 2026-11-17-guest-checkout-design.md):
 * the order was placed from the public register page without the buyer
 * being logged in. Not to be confused with `is_guest`, which marks the
 * admin's VIP guest registrations.
 */
class AddGuestCheckoutToOrders extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_guest_checkout')->after('is_guest')->default(false);
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_guest_checkout');
        });
    }
}
