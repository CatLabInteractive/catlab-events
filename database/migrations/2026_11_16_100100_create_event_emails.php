<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * The mail of one type for one event: either a library template it points to,
 * or its own subject and content. Without a row the organisation's version
 * (or the default) goes out.
 */
class CreateEventEmails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_emails', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('event_id')->unsigned();
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');

            $table->string('type', 64);

            $table->integer('email_template_id')->unsigned()->nullable();
            $table->foreign('email_template_id')->references('id')->on('email_templates')->onDelete('restrict');

            $table->string('subject')->nullable();
            $table->longText('content')->nullable();

            $table->timestamps();

            $table->unique([ 'event_id', 'type' ]);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('event_emails');
    }
}
