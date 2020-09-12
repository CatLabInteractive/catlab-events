<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddJwtToOrganisations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {

            $table->text('uitdb_refresh')->nullable()->after('uitpas');
            $table->text('uitdb_jwt')->nullable()->after('uitpas');

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

            $table->dropColumn('uitdb_refresh');
            $table->dropColumn('uitdb_jwt');

        });
    }
}
