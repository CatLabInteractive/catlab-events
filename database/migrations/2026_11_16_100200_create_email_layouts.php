<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * An organisation's changes to the layout every mail is wrapped in. Without a
 * row the default layout is used.
 */
class CreateEmailLayouts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_layouts', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unique('organisation_id');

            $table->string('accent_color', 7)->nullable();
            $table->text('footer_text')->nullable();
            $table->longText('custom_html')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_layouts');
    }
}
