<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * The CMS home page is chosen explicitly per organisation. When set (and the
 * page has a published translation in the requested locale) it is rendered
 * at / (or /en, /fr); when NULL the existing EventController@index homepage
 * stays. Pages are soft deleted, so Page also clears the column on delete;
 * the FK only covers hard deletes.
 */
class AddHomePageIdToOrganisations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {

            $table->integer('home_page_id')->unsigned()->nullable();
            $table->foreign('home_page_id')->references('id')->on('pages')->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropForeign([ 'home_page_id' ]);
            $table->dropColumn('home_page_id');
        });
    }
}
