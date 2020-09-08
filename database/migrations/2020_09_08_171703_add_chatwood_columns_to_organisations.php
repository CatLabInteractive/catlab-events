<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddChatwoodColumnsToOrganisations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {

            $table->string('chatwoot_token')->nullable()->after('uitpas');
            $table->string('chatwoot_url')->nullable()->after('uitpas');

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

            $table->dropColumn('chatwoot_url');
            $table->dropColumn('chatwoot_token');

        });
    }
}
