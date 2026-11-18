<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Blog posts, split like pages: `posts` holds identity, the publication date
 * (which drives the /YYYY/MM/DD/slug URL for every locale) and the WordPress
 * id for idempotent re-imports; `post_translations` holds the per-locale
 * slug, title, free-text author byline and the sanitised HTML body.
 */
class CreatePostsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->integer('featured_image_id')->unsigned()->nullable();
            $table->foreign('featured_image_id')->references('id')->on('assets');

            $table->dateTime('published_at')->nullable();

            $table->integer('wp_post_id')->unsigned()->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index([ 'organisation_id', 'published_at' ]);
            $table->unique([ 'organisation_id', 'wp_post_id' ]);
        });

        Schema::create('post_translations', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('post_id')->unsigned();
            $table->foreign('post_id')->references('id')->on('posts');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->string('locale', 5);
            $table->string('slug', 191);

            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->string('author', 191)->nullable();
            $table->mediumText('body');

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();

            $table->boolean('is_published')->default(false);

            $table->timestamps();

            $table->unique([ 'post_id', 'locale' ]);
            $table->unique([ 'organisation_id', 'locale', 'slug' ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('posts');
    }
}
