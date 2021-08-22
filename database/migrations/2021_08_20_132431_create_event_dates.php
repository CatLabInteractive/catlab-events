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

            $table->integer('max_tickets')->nullable()->unsigned();

            $table->timestamps();
            $table->softDeletes();

        });

        DB::statement("
            INSERT INTO event_dates 
                (event_id, startDate, endDate, doorsDate, max_tickets, created_at, updated_at)
            SELECT
                id,
                startDate,
                endDate,
                doorsDate,
                max_tickets,
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

        Schema::create('event_ticket_categories_dates', function (Blueprint $table) {

            $table->increments('id');

            $table->unsignedInteger('event_ticket_category_id');
            $table->foreign('event_ticket_category_id')->references('id')->on('event_ticket_categories');

            $table->unsignedInteger('event_date_id');
            $table->foreign('event_date_id')->references('id')->on('event_dates');

            $table->timestamps();

        });

        DB::statement("
            INSERT INTO event_ticket_categories_dates 
                (event_ticket_category_id, event_date_id, created_at, updated_at)
            SELECT
                event_ticket_categories.id,
                event_dates.id,
                NOW(),
                NOW()
            FROM
                event_ticket_categories
            LEFT JOIN event_dates ON event_ticket_categories.event_id = event_dates.event_id
            WHERE event_dates.event_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
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

        Schema::dropIfExists('event_ticket_categories_dates');
        Schema::dropIfExists("event_dates");
    }
}
