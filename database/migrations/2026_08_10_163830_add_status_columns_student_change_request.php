<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('student_change_request', function (Blueprint $table) {
            $table->enum('STATUS', ['Pending', 'Approved', 'Rejected'])->default('Pending')->after('SECTION_ID');
            $table->integer('DECIDED_BY')->nullable()->after('STATUS');
            $table->timestamp('DECIDED_ON')->nullable()->after('DECIDED_BY');
        });
    }

    public function down()
    {
        Schema::table('student_change_request', function (Blueprint $table) {
            $table->dropColumn(['STATUS', 'DECIDED_BY', 'DECIDED_ON']);
        });
    }
};
