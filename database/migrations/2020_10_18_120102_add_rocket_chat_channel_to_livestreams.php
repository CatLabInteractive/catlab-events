<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRocketChatChannelToLivestreams extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('livestreams', function (Blueprint $table) {
            $table->string('rocketchat_channel')->nullable()->after('streaming');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->string('rocketchat_oauth_secret')->nullable()->after('livestream_css');
            $table->string('rocketchat_oauth_client')->nullable()->after('livestream_css');
            $table->string('rocketchat_url')->nullable()->after('livestream_css');
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
            $table->dropColumn('rocketchat_channel');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('rocketchat_url');
            $table->dropColumn('rocketchat_oauth_client');
            $table->dropColumn('rocketchat_oauth_secret');
        });
    }
}
