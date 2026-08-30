<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInvitationSentAtToWaitinglist extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_waitinglist', function (Blueprint $table) {

            $table->timestamp('invitation_sent_at')->after('access_token')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_waitinglist', function (Blueprint $table) {
            $table->dropColumn('invitation_sent_at');
        });
    }
}
