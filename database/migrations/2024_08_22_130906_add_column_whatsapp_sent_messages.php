<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('whatsapp_sent_messages', function (Blueprint $table) {
            // added status,error,uri
            $table->string('message_status',50)->after('sent_date')->nullable();
            $table->string('message_error',255)->after('message_status')->nullable();
            $table->text('uri')->after('message_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('whatsapp_sent_messages', function (Blueprint $table) {
            //
            $table->dropColumn('message_status');
            $table->dropColumn('message_error');
            $table->dropColumn('uri');
        });
    }
};
