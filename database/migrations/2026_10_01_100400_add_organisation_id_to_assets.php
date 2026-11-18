<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Assets become scoped to an organisation so the CMS image picker and the
 * API only list (and blocks only accept) the active organisation's images.
 * Existing rows stay NULL: they are only visible where already referenced.
 */
class AddOrganisationIdToAssets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {

            $table->integer('organisation_id')->unsigned()->nullable()->after('user_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign([ 'organisation_id' ]);
            $table->dropColumn('organisation_id');
        });
    }
}
