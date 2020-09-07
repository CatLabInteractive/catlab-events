<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHelpdeskUrlToOrganisations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {

            $table->integer('favicon_id')->unsigned()->nullable()->after('logo_id');
            $table->foreign('favicon_id')->references('id')->on('assets');

            $table->string('helpdesk_url')->nullable()->after('twitter_url');

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

            $table->dropForeign('organisations_favicon_id_foreign');
            $table->dropColumn('favicon_id');
            $table->dropColumn('helpdesk_url');

        });
    }
}
