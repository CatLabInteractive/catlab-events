<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A short explanation shown under the ticket on the register form, e.g.
 * "Je speelt de quiz alleen. Je hebt twee toestellen nodig."
 */
class AddDescriptionToTicketCategories extends Migration
{
    public function up()
    {
        Schema::table('event_ticket_categories', function (Blueprint $table) {
            $table->text('description')->after('name')->nullable();
        });
    }

    public function down()
    {
        Schema::table('event_ticket_categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
}
