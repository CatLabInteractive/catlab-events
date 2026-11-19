<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * When an organisation marks one of its domains canonical, requests on its
 * other domains are 301'd there (CanonicalDomain middleware, a later phase).
 * Replaces the global VALID_DOMAINS approach of ValidDomain, which would
 * send every organisation to the first valid domain.
 */
class AddIsCanonicalToOrganisationDomains extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisation_domains', function (Blueprint $table) {

            $table->boolean('is_canonical')->nullable()->after('domain');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('organisation_domains', function (Blueprint $table) {
            $table->dropColumn('is_canonical');
        });
    }
}
