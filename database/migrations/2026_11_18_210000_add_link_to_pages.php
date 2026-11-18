<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A page can be a menu link instead of content: to the quiz series
 * (@series), the calendar (@calendar), the blog (@blog) or any URL. Adds a
 * column only; existing pages stay content pages.
 */
class AddLinkToPages extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('pages', 'link')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->string('link', 1024)->nullable()->after('show_in_footer');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('pages', 'link')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropColumn('link');
            });
        }
    }
}
