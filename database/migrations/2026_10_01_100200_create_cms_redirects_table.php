<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Per-organisation redirects for old (WordPress) URLs: media uploads,
 * taxonomies, feeds and renamed slugs. `from_path` is stored without leading
 * or trailing slash and without query string.
 */
class CreateCmsRedirectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_redirects', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->string('from_path', 191);
            $table->string('to_url', 1024);
            $table->smallInteger('status_code')->unsigned()->default(301);

            $table->timestamps();

            $table->unique([ 'organisation_id', 'from_path' ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_redirects');
    }
}
