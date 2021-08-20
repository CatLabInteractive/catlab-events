<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEventDates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_dates', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('event_id')->unsigned();
            $table->foreign('event_id')->references('id')->on('events');

            $table->dateTime('startDate');
            $table->dateTime('endDate');
            $table->dateTime('doorsDate')->nullable();

            $table->timestamps();
            $table->softDeletes();

        });

        DB::statement("
            INSERT INTO event_dates 
                (event_id, startDate, endDate, doorsDate, created_at, updated_at)
            SELECT
                id,
                startDate,
                endDate,
                doorsDate,
                now(),
                now()
            FROM
                events
            WHERE 
                startDate IS NOT NULL AND 
                endDate IS NOT NULL
        ");

        Schema::table('events', function (Blueprint $table) {

            $table->dropColumn('startDate');
            $table->dropColumn('endDate');
            $table->dropColumn('doorsDate');

        });

        Schema::table('event_ticket_categories', function (Blueprint $table) {

            $table->unsignedInteger('event_date_id')->nullable()->after('event_id');
            $table->foreign('event_date_id')->references('id')->on('event_dates');

        });

        DB::statement("
            UPDATE event_ticket_categories
            LEFT JOIN event_dates ON event_ticket_categories.event_id = event_dates.event_id
            SET 
                event_ticket_categories.event_date_id = event_dates.id
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_ticket_categories', function (Blueprint $table) {

            $table->dropForeign('event_ticket_categories_event_date_id_foreign');
            $table->dropColumn('event_date_id');

        });

        Schema::table('events', function (Blueprint $table) {

            $table->dateTime('startDate')->nullable();
            $table->dateTime('endDate')->nullable();
            $table->dateTime('doorsDate')->nullable();

        });

        DB::statement("
            UPDATE events
            LEFT JOIN event_dates ON events.id = event_dates.event_id
            SET 
                events.startDate = event_dates.startDate,
                events.endDate = event_dates.endDate,
                events.doorsDate = event_dates.doorsDate;
        ");

        Schema::dropIfExists("event_dates");
    }
}
