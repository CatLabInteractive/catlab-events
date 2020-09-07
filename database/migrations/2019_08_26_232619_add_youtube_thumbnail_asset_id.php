<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddYoutubeThumbnailAssetId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('series', function(Blueprint $table) {

            $table->integer('youtube_thumbnail_asset_id')
                ->unsigned()
                ->nullable()
                ->after('youtube_url');

            $table->foreign('youtube_thumbnail_asset_id')->references('id')->on('assets');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('series', function(Blueprint $table) {

            $table->dropForeign('series_youtube_thumbnail_asset_id_foreign');
            $table->dropColumn('youtube_thumbnail_asset_id');

        });
    }
}
