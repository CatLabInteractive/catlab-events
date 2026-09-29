<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Guest registrations: orders an admin creates from the admin panel for guests
 * who never registered an account. They carry no user_id (already nullable),
 * so the guest's name and (optional) email live on the order itself.
 */
class AddGuestsToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->boolean('is_guest')->after('state')->default(false);
            $table->string('guest_name')->after('is_guest')->nullable();
            $table->string('guest_email')->after('guest_name')->nullable();
            $table->text('guest_notes')->after('guest_email')->nullable();

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
            $table->dropColumn([ 'is_guest', 'guest_name', 'guest_email', 'guest_notes' ]);
        });
    }
}
