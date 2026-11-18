<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link for the highlighted "Quizzen op maat" item in the site menu (empty = hidden).
 */
class AddCustomQuizUrlToOrganisations extends Migration
{
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('custom_quiz_url')->nullable();
        });
    }

    public function down()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('custom_quiz_url');
        });
    }
}
