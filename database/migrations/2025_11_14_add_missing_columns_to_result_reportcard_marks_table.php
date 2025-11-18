<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('result_reportcard_marks', function (Blueprint $table) {
            // Only add columns that are likely missing based on error logs
            $table->integer('syear')->nullable()->after('student_id');
            $table->integer('standard_id')->nullable()->after('syear');
            $table->integer('grade_id')->nullable()->after('standard_id');
            $table->integer('division_id')->nullable()->after('grade_id');
            $table->string('term_id')->nullable()->after('division_id');
            $table->integer('template_id')->nullable()->after('term_id');
            $table->string('result_type')->nullable()->after('template_id');
            $table->string('format')->nullable()->after('result_type');
            
            // Attendance related columns (the ones causing the main errors)
            $table->integer('total_working_day')->nullable()->after('format');
            $table->integer('present_working_day')->nullable()->after('total_working_day');
            $table->integer('student_percentage')->nullable()->after('present_working_day');
            
            // Marks related columns
            $table->decimal('total_marks_obtained', 10, 2)->nullable()->after('student_percentage');
            $table->decimal('percentage', 5, 2)->nullable()->after('total_marks_obtained');
            
            // Add indices for better performance
            $table->index(['student_id', 'syear']);
            $table->index(['student_id', 'standard_id', 'grade_id', 'division_id', 'term_id', 'syear']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('result_reportcard_marks', function (Blueprint $table) {
            // Drop the columns in reverse order
            $table->dropIndex(['student_id', 'syear']);
            $table->dropIndex(['student_id', 'standard_id', 'grade_id', 'division_id', 'term_id', 'syear']);
            
            $table->dropColumn([
                'syear',
                'sub_institute_id', 
                'standard_id',
                'grade_id',
                'division_id',
                'term_id',
                'template_id',
                'result_type',
                'format',
                'total_working_day',
                'present_working_day',
                'student_percentage',
                'total_working_days',
                'present_working_days',
                'total_marks_obtained',
                'percentage'
            ]);
        });
    }
};