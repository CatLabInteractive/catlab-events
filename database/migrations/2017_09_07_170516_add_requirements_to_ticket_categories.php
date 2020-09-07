<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRequirementsToTicketCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_ticket_categories', function(Blueprint $table) {

            $table->dateTime('end_date')->nullable()->after('price');
            $table->dateTime('start_date')->nullable()->after('price');
            $table->integer('max_tickets')->nullable()->unsigned()->after('price');

        });

        Schema::table('events', function(Blueprint $table) {

            $table->integer('max_tickets')->nullable()->unsigned()->after('venue_id');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_ticket_categories', function(Blueprint $table) {

            $table->dropColumn('max_tickets');
            $table->dropColumn('start_date');
            $table->dropColumn('end_date');

        });

        Schema::table('events', function(Blueprint $table) {

            $table->dropColumn('max_tickets');

        });
    }
}
