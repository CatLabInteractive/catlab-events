<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSeriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('series', function(Blueprint $table) {

            $table->increments('id');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->string('slug', 128)->nullable();
            $table->unique('slug');

            $table->string('name', 128)->nullable();

            $table->text('teaser')->nullable();
            $table->text('description')->nullable();

            $table->boolean('active')->default(true);

            $table->integer('header_asset_id')->unsigned()->nullable();
            $table->foreign('header_asset_id')->references('id')->on('assets');

            $table->integer('logo_asset_id')->unsigned()->nullable();
            $table->foreign('logo_asset_id')->references('id')->on('assets');

            $table->timestamps();
            $table->softDeletes();

        });

        Schema::table('events', function(Blueprint $table) {

            $table->integer('series_id')->unsigned()->nullable()->after('logo_id');
            $table->foreign('series_id')->references('id')->on('series');

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

            $table->dropForeign('events_series_id_foreign');
            $table->dropColumn('series_id');

        });

        Schema::dropIfExists('series');

    }
}
