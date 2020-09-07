<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVatFieldsToEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function(Blueprint $table) {
            $table->tinyInteger('include_ticket_fee')->after('venue_id')->default(1);
            $table->tinyInteger('vat_percentage')->after('venue_id')->default(0);
        });

        Schema::table('organisations', function(Blueprint $table) {
            $table->text('vat_footer')->after('bank_bic')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function(Blueprint $table) {
            $table->dropColumn('include_ticket_fee');
            $table->dropColumn('vat_percentage');
        });

        Schema::table('organisations', function(Blueprint $table) {
            $table->dropColumn('vat_footer');
        });
    }
}
