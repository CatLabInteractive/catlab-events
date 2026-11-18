<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The series colours of the site design, e.g. "#61cc6f/#fcc765, #5e2fff".
 * Empty means the default palette. See App\Tools\ColourPalette.
 */
class AddThemeColoursToOrganisations extends Migration
{
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->text('theme_colours')->nullable();
        });
    }

    public function down()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('theme_colours');
        });
    }
}
