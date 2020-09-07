<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangePeopleTableDescriptionField extends Migration
{
    /**
     * Run the migrations.F
     *
     * @return void
     */
    public function up()
    {
        Schema::table('people', function (Blueprint $table) {

            $table->text('description')->nullable()->change();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('people', function (Blueprint $table) {

            $table->string('description')->nullable(true)->change();

        });
    }
}
