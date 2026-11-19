<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free text shown under the credits on the event page, e.g. when and where
 * the quiz was recorded. Column only: existing rows stay empty.
 */
class AddCreditsNoteToEvents extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('credits_note')->nullable();
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('credits_note');
        });
    }
}
