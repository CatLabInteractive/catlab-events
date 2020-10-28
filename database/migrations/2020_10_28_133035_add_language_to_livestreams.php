<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLanguageToLivestreams extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('livestreams', function (Blueprint $table) {
            $table->string('language', 5)->after('rocketchat_channel')->nullable();
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->string('language', 5)->after('rocketchat_oauth_secret')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('language', 5)->after('series_id')->nullable();
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
            $table->dropColumn('language');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('language');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
}
