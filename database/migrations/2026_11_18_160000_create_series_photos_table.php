<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The photos shown, as a slow slideshow, in the hero of a series and its events.
 */
class CreateSeriesPhotosTable extends Migration
{
    public function up()
    {
        Schema::create('series_photos', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('series_id')->unsigned();
            $table->foreign('series_id')->references('id')->on('series')->onDelete('cascade');

            $table->integer('photo_asset_id')->unsigned()->nullable();
            $table->foreign('photo_asset_id')->references('id')->on('assets');

            $table->integer('sort')->default(0);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('series_photos');
    }
}
