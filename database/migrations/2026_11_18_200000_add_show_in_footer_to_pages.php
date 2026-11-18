<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages can be listed in the site footer ("toon in footer"). Adds a column
 * only; existing pages stay out of the footer.
 */
class AddShowInFooterToPages extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('pages', 'show_in_footer')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->boolean('show_in_footer')->default(false)->after('show_in_menu');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('pages', 'show_in_footer')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropColumn('show_in_footer');
            });
        }
    }
}
