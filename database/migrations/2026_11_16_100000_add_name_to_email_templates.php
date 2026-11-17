<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Named templates form a library next to the organisation's own version of a
 * mail. The organisation's version keeps an empty name, so existing rows stay
 * what they were.
 */
class AddNameToEmailTemplates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('name', 100)->default('')->after('type');
        });

        // The new key starts with organisation_id, so the foreign key can use
        // it. Adding it first lets MySQL drop the old key afterwards.
        Schema::table('email_templates', function (Blueprint $table) {
            $table->unique([ 'organisation_id', 'type', 'name' ]);
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique([ 'organisation_id', 'type' ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Library templates cannot exist under the old key.
        \DB::table('email_templates')->where('name', '<>', '')->delete();

        Schema::table('email_templates', function (Blueprint $table) {
            $table->unique([ 'organisation_id', 'type' ]);
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique([ 'organisation_id', 'type', 'name' ]);
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
}
