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
        Schema::table('whatapp_user_details', function (Blueprint $table) {
            $table->string('api_type')->default('twilio')->after('user_whatsapp_token');
            $table->text('cloud_api_access_token')->nullable()->after('api_type');
            $table->string('cloud_api_phone_number_id')->nullable()->after('cloud_api_access_token');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('whatapp_user_details', function (Blueprint $table) {
            $table->dropColumn(['cloud_api_phone_number_id', 'cloud_api_access_token', 'api_type']);
        });
    }
};
