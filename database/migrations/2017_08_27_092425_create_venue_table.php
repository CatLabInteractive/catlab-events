<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVenueTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('venues', function(Blueprint $table) {

            $table->increments('id');

            $table->string('name');
            $table->string('address');
            $table->string('postalCode');
            $table->string('city');
            $table->string('country');

            $table->decimal('long', 10, 7)->nullable();
            $table->decimal('lat', 10, 7)->nullable();

            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');

            $table->timestamps();
            $table->softDeletes();

        });

        Schema::table('events', function(Blueprint $table) {

            $table->integer('venue_id')->unsigned()->after('endDate')->nullable();

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

            $table->dropColumn('venue_id');

        });

        Schema::dropIfExists('venues');
    }
}
