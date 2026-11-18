<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assets belong to an organisation, so the admin's file picker only offers
 * the active organisation's files. Existing assets get the organisation of
 * whatever links to them; an asset nothing links to stays without one (and
 * out of every picker).
 */
class AddOrganisationToAssets extends Migration
{
    /**
     * Where an asset is linked, and how to find that link's organisation.
     * The first link found wins.
     */
    const LINKS = [
        [ 'organisations', 'logo_id', 'id' ],
        [ 'organisations', 'favicon_id', 'id' ],
        [ 'events', 'poster_id', 'organisation_id' ],
        [ 'events', 'logo_id', 'organisation_id' ],
        [ 'series', 'header_asset_id', 'organisation_id' ],
        [ 'series', 'logo_asset_id', 'organisation_id' ],
        [ 'series', 'youtube_thumbnail_asset_id', 'organisation_id' ],
        [ 'people', 'photo_asset_id', 'organisation_id' ],
    ];

    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->integer('organisation_id')->unsigned()->nullable()->after('user_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
        });

        foreach (self::LINKS as [ $table, $assetColumn, $organisationColumn ]) {
            DB::table('assets')
                ->whereNull('organisation_id')
                ->update([
                    'organisation_id' => DB::table($table)
                        ->select($table . '.' . $organisationColumn)
                        ->whereColumn($table . '.' . $assetColumn, 'assets.id')
                        ->limit(1),
                ]);
        }
    }

    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign([ 'organisation_id' ]);
            $table->dropColumn('organisation_id');
        });
    }
}
