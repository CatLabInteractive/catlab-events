<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * CMS pages. Identity and hierarchy live on `pages`; everything that depends
 * on the language (slug, materialised path, title, blocks, SEO meta, the
 * published flag) lives on one `page_translations` row per locale. The
 * organisation is copied onto the translation so the public path can be
 * unique per organisation and locale.
 */
class CreatePagesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->integer('parent_id')->unsigned()->nullable();
            $table->foreign('parent_id')->references('id')->on('pages');

            $table->integer('sort_order')->default(0);
            $table->boolean('show_in_menu')->default(false);

            $table->integer('wp_post_id')->unsigned()->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index([ 'organisation_id', 'parent_id', 'sort_order' ]);
        });

        Schema::create('page_translations', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('page_id')->unsigned();
            $table->foreign('page_id')->references('id')->on('pages');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->string('locale', 5);
            $table->string('slug', 191);
            $table->string('path', 191);

            $table->string('title');
            $table->json('blocks');

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();

            $table->integer('og_image_id')->unsigned()->nullable();
            $table->foreign('og_image_id')->references('id')->on('assets');

            $table->boolean('is_published')->default(false);
            $table->dateTime('published_at')->nullable();

            $table->timestamps();

            $table->unique([ 'page_id', 'locale' ]);
            $table->unique([ 'organisation_id', 'locale', 'path' ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
    }
}
