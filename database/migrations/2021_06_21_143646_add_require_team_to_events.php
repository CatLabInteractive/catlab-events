<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRequireTeamToEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE events MODIFY COLUMN registration VARCHAR(8)');
        DB::statement('ALTER TABLE events MODIFY COLUMN visbility VARCHAR(8)');

        Schema::table('events', function (Blueprint $table) {

            $table->dateTime('startDate')->nullable()->change();
            $table->dateTime('endDate')->nullable()->change();
            $table->float('vat_percentage')->nullable()->change();

            $table->boolean('requires_team')->after('is_published')
                ->default(true);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {

            $table->dateTime('startDate')->nullable(false)->change();
            $table->dateTime('endDate')->nullable(false)->change();

            $table->dropColumn('requires_team');

        });
    }
}
