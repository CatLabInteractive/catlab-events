<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMaxPlayersToTicketCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {

            $table->unsignedInteger('campaign_id')->after('quizwitz_report_id')
                ->nullable();

        });

        Schema::table('event_ticket_categories', function (Blueprint $table) {

            $table->unsignedInteger('max_players')->after('max_tickets')
                ->nullable();

        });

        Schema::table('orders', function (Blueprint $table) {

            $table->string('play_link')->after('pay_url')->nullable()
                ->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_ticket_categories', function (Blueprint $table) {

            $table->dropColumn('max_players');

        });

        Schema::table('orders', function (Blueprint $table) {

            $table->dropColumn('play_link');

        });

        Schema::table('events', function (Blueprint $table) {

            $table->dropColumn('campaign_id');

        });
    }
}
