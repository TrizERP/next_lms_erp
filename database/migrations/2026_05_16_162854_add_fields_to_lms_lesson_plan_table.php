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
        Schema::table('lms_lesson_plan', function (Blueprint $table) {

            $table->unsignedBigInteger('teacher_id')->nullable()->after('id');

            $table->date('lesson_date')->nullable()->after('teacher_id');

            $table->string('lesson_title')->nullable()->after('lesson_date');

            $table->integer('period_number')->nullable()->after('lesson_title');

            $table->string('status')->nullable()->after('period_number');

            $table->integer('duration')->nullable()->after('status');

            $table->float('engagement_rating')->nullable()->after('duration');

            $table->timestamp('completed_at')->nullable()->after('engagement_rating');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_lesson_plan', function (Blueprint $table) {

            $table->dropColumn([
                'teacher_id',
                'lesson_date',
                'lesson_title',
                'period_number',
                'status',
                'duration',
                'engagement_rating',
                'completed_at'
            ]);

        });
    }
};