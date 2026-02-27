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
          Schema::table('homework', function (Blueprint $table) {
            $table->bigInteger('updated_by')->after('created_ip')->nullable();
            $table->timestamp('updated_on')->after('created_on')->nullable();
            $table->string('ai_generated_file')->nullable();
         });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('homework', function (Blueprint $table) {
            $table->dropColumn(['updated_by', 'updated_on', 'ai_generated_file']);
         });
    }
};
