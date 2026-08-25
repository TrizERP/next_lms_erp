<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Locks a published exam's marks against further edits. No lock row for a
 * given (sub_institute_id, syear, exam_id) means the exam is unlocked
 * (matches pre-existing behaviour). Checked by App\Models\ResultLock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('syear', 20)->index();
            $table->unsignedBigInteger('exam_id')->index();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['sub_institute_id', 'syear', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_locks');
    }
};
