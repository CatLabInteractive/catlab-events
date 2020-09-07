<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompetitionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('competitions', function(Blueprint $table) {

            $table->increments('id');

            $table->string('name');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->timestamps();
            $table->softDeletes();

        });

        Schema::table('events', function(Blueprint $table) {

            $table->integer('competition_id')->unsigned()->after('venue_id')->nullable();

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

            $table->dropColumn('competition_id');

        });

        Schema::dropIfExists('competitions');
    }
}
