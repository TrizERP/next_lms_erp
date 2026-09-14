<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('lms_assignment', function (Blueprint $table) {
            $table->string('assignment_source_type', 30)->nullable()->default('exam_paper')->after('exam_pdf');
            $table->string('homework_file', 250)->nullable()->after('assignment_source_type');
        });
    }

    public function down()
    {
        Schema::table('lms_assignment', function (Blueprint $table) {
            $table->dropColumn(['assignment_source_type', 'homework_file']);
        });
    }
};
