<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEventType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_type')->nullable()->after('name');
        });

        foreach (\App\Models\Event::withTrashed()->get() as $event) {
            $event->event_type = $event->hasEventDates() ? \App\Models\Event::TYPE_EVENT : \App\Models\Event::TYPE_PACKAGE;
            $event->save();
        }

        Schema::table('events', function (Blueprint $table) {
            $table->string('event_type')->nullable(false)->change();
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
            $table->dropColumn('event_type');
        });
    }
}
