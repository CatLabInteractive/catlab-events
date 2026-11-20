<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The organisation's public phone number, shown in the site footer and the
 * Organization JSON-LD. Column only: existing rows stay empty.
 */
class AddPhoneToOrganisations extends Migration
{
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('phone', 50)->nullable();
        });
    }

    public function down()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
}
