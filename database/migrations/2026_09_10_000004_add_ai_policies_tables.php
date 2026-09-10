<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_policies')) {
            Schema::create('ai_policies', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->string('policy_type', 80);
                $table->tinyInteger('status')->default(1);
                $table->tinyInteger('require_disclosure')->default(0);
                $table->tinyInteger('require_acknowledgement')->default(0);
                $table->tinyInteger('ai_detection_required')->default(0);
                $table->tinyInteger('plagiarism_check_required')->default(0);
                $table->string('detection_provider', 120)->nullable();
                $table->decimal('detection_threshold', 5, 2)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['sub_institute_id', 'status'], 'ai_policies_scope_status_index');
            });
        }

        if (! Schema::hasTable('ai_policy_rules')) {
            Schema::create('ai_policy_rules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('policy_id')->index();
                $table->string('rule_key', 120);
                $table->longText('rule_value')->nullable();
                $table->timestamps();

                $table->unique(['policy_id', 'rule_key'], 'ai_policy_rules_policy_key_unique');
            });
        }

        if (! Schema::hasTable('ai_policy_assignments')) {
            Schema::create('ai_policy_assignments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('policy_id')->index();
                $table->string('scope_type', 40); // global, academic_year, grade, course, class, assignment, assessment, activity
                $table->unsignedBigInteger('scope_id')->nullable()->index();
                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
                $table->tinyInteger('status')->default(1);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['policy_id', 'scope_type', 'scope_id', 'sub_institute_id'],
                    'ai_policy_assignments_unique'
                );
            });
        }

        if (! Schema::hasTable('ai_policy_acknowledgements')) {
            Schema::create('ai_policy_acknowledgements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('policy_id')->index();
                $table->unsignedBigInteger('student_id')->nullable()->index();
                $table->unsignedBigInteger('assignment_id')->nullable()->index();
                $table->unsignedBigInteger('activity_id')->nullable()->index();
                $table->string('scope_type', 40)->nullable();
                $table->unsignedBigInteger('scope_id')->nullable()->index();
                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
                $table->tinyInteger('acknowledged')->default(0);
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_policy_acknowledgements');
        Schema::dropIfExists('ai_policy_assignments');
        Schema::dropIfExists('ai_policy_rules');
        Schema::dropIfExists('ai_policies');
    }
};
