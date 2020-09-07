<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFeeToOrganisation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {

            $table->float('fee_vat_factor')->default(0.21)->after('footer_html');
            $table->float('fee_fixed')->default(1)->after('fee_vat_factor');
            $table->float('fee_factor')->default(0.03)->after('fee_fixed');
            $table->float('fee_minimum')->default(1)->after('fee_factor');

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

            $table->dropColumn('fee_vat_factor');
            $table->dropColumn('fee_fixed');
            $table->dropColumn('fee_factor');
            $table->dropColumn('fee_minimum');

        });
    }
}
