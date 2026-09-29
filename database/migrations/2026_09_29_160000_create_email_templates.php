<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Transactional mails (registration, cancellation, waiting list, team
 * invitation) an organisation rewrote in the admin panel. Without a row the
 * default Blade template goes out, so nothing changes until an admin edits.
 */
class CreateEmailTemplates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_templates', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->string('type', 64);
            $table->string('subject');
            $table->longText('content');

            $table->timestamps();

            $table->unique([ 'organisation_id', 'type' ]);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_templates');
    }
}
