<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeEverythingSoftdelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function(Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('event_ticket_categories', function(Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('groups', function(Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function(Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('event_ticket_categories', function(Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('groups', function(Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
