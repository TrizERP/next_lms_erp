<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('dicipline', function (Blueprint $table) {
            $table->tinyInteger('flag')->default(0)->comment('1=Positive, 0=Neutral, -1=Negative')->after('message');
        });
    }

    public function down()
    {
        Schema::table('dicipline', function (Blueprint $table) {
            $table->dropColumn(['flag']);
        });
    }
};