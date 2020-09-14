<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRedirectUrlToLivestreams extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('livestreams', function (Blueprint $table) {

            $table->string('youtube_video')->nullable()->after('mixer_key');
            $table->string('redirect_uri')->nullable()->after('youtube_video');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('livestreams', function (Blueprint $table) {
            $table->dropColumn('youtube_video');
            $table->dropColumn('redirect_uri');
        });
    }
}
