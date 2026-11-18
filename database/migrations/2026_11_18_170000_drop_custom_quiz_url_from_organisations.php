<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Quizzen op maat" menu link was dropped again (the sites are being merged);
 * remove its column where an earlier staging build had added it.
 */
class DropCustomQuizUrlFromOrganisations extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('organisations', 'custom_quiz_url')) {
            Schema::table('organisations', function (Blueprint $table) {
                $table->dropColumn('custom_quiz_url');
            });
        }
    }

    public function down()
    {
    }
}
