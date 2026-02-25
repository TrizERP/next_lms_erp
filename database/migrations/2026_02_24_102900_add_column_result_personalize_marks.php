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
         Schema::table('result_personalize_marks', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->default(0)->nullable()->after('obtain');
            $table->string('student_level',50)->nullable()->after('percentage');
            $table->text('exam_remark')->nullable()->after('student_level');
            $table->bigInteger('created_by')->nullable()->after('exam_remark');
            $table->bigInteger('updated_by')->nullable()->after('created_by');
            $table->timestamp('updated_at')->nullable()->after('created_at');
         });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('result_personalize_marks', function (Blueprint $table) {
            $table->dropColumn(['percentage', 'student_level','exam_remark','created_by','updated_by','updated_at']);
         });
    }
};
