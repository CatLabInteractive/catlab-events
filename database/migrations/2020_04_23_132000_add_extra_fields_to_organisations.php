<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExtraFieldsToOrganisations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {

            $table->integer('logo_id')->unsigned()->nullable()->after('vat_footer');
            $table->foreign('logo_id')->references('id')->on('assets');

            $table->string('legal_name')->nullable()->after('logo_id');
            $table->string('support_email')->nullable()->after('legal_name');

            $table->string('blog_url')->nullable()->after('support_email');
            $table->string('blog_rss_url')->nullable()->after('blog_url');
            $table->string('website_url')->nullable()->after('blog_rss_url');
            $table->string('facebook_url')->nullable()->after('website_url');
            $table->string('youtube_url')->nullable()->after('facebook_url');
            $table->string('instagram_url')->nullable()->after('youtube_url');
            $table->string('googleplus_url')->nullable()->after('instagram_url');
            $table->string('linkedin_url')->nullable()->after('googleplus_url');
            $table->string('twitter_url')->nullable()->after('linkedin_url');
            $table->text('footer_html')->nullable()->after('twitter_url');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('organisations', function (Blueprint $table) {

            $table->dropForeign('organisations_logo_id_foreign');
            $table->dropColumn('logo_id');

            $table->dropColumn('legal_name');
            $table->dropColumn('support_email');

            $table->dropColumn('blog_url');
            $table->dropColumn('blog_rss_url');
            $table->dropColumn('website_url');
            $table->dropColumn('facebook_url');
            $table->dropColumn('youtube_url');
            $table->dropColumn('instagram_url');
            $table->dropColumn('googleplus_url');
            $table->dropColumn('linkedin_url');
            $table->dropColumn('twitter_url');
            $table->dropColumn('footer_html');

        });
    }
}
