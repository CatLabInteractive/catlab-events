<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAssetToEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function(Blueprint $table) {

            $table->integer('logo_id')->unsigned()->nullable()->after('max_points');
            $table->foreign('logo_id')->references('id')->on('assets');

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

            $table->dropForeign('events_logo_id_foreign');
            $table->dropColumn('logo_id');

        });
    }
}
