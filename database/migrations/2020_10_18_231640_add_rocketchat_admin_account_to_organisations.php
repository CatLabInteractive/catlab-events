<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRocketchatAdminAccountToOrganisations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('rocketchat_admin_password')->nullable()->after('rocketchat_url');
            $table->string('rocketchat_admin_username')->nullable()->after('rocketchat_url');

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
            $table->dropColumn('rocketchat_admin_password');
            $table->dropColumn('rocketchat_admin_username');
        });
    }
}
