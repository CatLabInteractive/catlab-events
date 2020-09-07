<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMaxPointsAndDoorsTimeToEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function(Blueprint $table) {

            $table->dateTime('doorsDate')->after('endDate')->nullable();
            $table->integer('max_points')->nullable()->after('quizwitz_report_id');

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

            $table->dropColumn('doorsDate');
            $table->dropColumn('max_points');

        });
    }
}
