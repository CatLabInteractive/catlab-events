<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusColumnToEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_published')->after('organisation_id')->default(false);
        });

        foreach (\App\Models\Event::all() as $event) {
            /** @var \App\Models\Event $event */
            $event->is_published = true;

            if ($event->isSoldOut()) {
                $event->registration = \App\Models\Event::REGISTRATION_FULL;
            } else {
                $event->registration = \App\Models\Event::REGISTRATION_OPEN;
            }

            $event->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }
}
