<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeVatPercentage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function(Blueprint $table) {
            $table->dropColumn('vat_percentage');
        });

        Schema::table('events', function(Blueprint $table) {
            $table->float('vat_percentage')->after('venue_id')->default(0);
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
            $table->dropColumn('vat_percentage');
        });

        Schema::table('events', function(Blueprint $table) {
            $table->tinyInteger('vat_percentage')->after('venue_id')->default(0);
        });
    }
}
