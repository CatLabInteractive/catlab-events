<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MoveScoresToEventDates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->unsignedInteger('event_date_id')
                ->nullable()
                ->after('event_id');

            $table->foreign('event_date_id')
                ->references('id')->on('event_dates');
        });

        \DB::statement("
            UPDATE
                scores 
            LEFT JOIN 
                event_dates ON event_dates.event_id = scores.event_id 
            SET 
                event_date_id = event_dates.id
        ");

        Schema::table('scores', function (Blueprint $table) {
            $table->unsignedInteger('event_date_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('event_dates', function(Blueprint $table) {

            $table->string('quizwitz_report_id')
                ->after('max_tickets')
                ->nullable();

        });

        \DB::statement("
            UPDATE
                event_dates 
            LEFT JOIN 
                events ON event_dates.event_id = events.id 
            SET 
                event_dates.quizwitz_report_id = events.quizwitz_report_id
        ");

        Schema::table('events', function (Blueprint $table) {

            $table->dropColumn('quizwitz_report_id');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->dropForeign('scores_event_date_id_foreign');
            $table->dropColumn('event_date_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('quizwitz_report_id')->after('registration')->nullable();
        });

        \DB::statement("
            UPDATE
                events 
            LEFT JOIN 
                event_dates ON event_dates.event_id = events.id 
            SET 
                events.quizwitz_report_id = event_dates.quizwitz_report_id
        ");

        Schema::table('event_dates', function(Blueprint $table) {
            $table->dropColumn('quizwitz_report_id');
        });
    }
}
