<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAddressToOrganisation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisations', function(Blueprint $table) {

            $table->text('address')->nullable()->after('name');
            $table->string('national_id')->length(64)->nullable()->after('address');
            $table->string('bank_iban')->length(64)->nullable()->after('national_id');
            $table->string('bank_bic')->length(64)->nullable()->after('bank_iban');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('organisations', function(Blueprint $table) {

            $table->dropColumn('address');
            $table->dropColumn('national_id');
            $table->dropColumn('bank_iban');
            $table->dropColumn('bank_bic');

        });
    }
}
