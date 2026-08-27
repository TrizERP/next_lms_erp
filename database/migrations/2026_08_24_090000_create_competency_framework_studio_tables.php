<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_07_28_100100_create_competency_module_tables.php`
 * (frameworks / framework items) and `2026_07_28_130000_create_competency_studio_tables.php`
 * (framework weights / mapping reviews).
 *
 * These four tables back the Capability Intelligence "Competency Framework"
 * screen (Framework Studio: framework CRUD, framework->competency mapping,
 * category weighting, and the role-mapping change approval queue). They were
 * explicitly left out of `2026_08_21_090000_create_competency_module_tables.php`
 * (the earlier Competency Management port) as out of scope at the time.
 *
 * Conventions match the source and the sibling competency migrations:
 * bigIncrements PK, indexed unsignedBigInteger sub_institute_id, nullable
 * indexed audit columns, timestamps + softDeletes, string-based status enums,
 * loose joins (jobrole string / department_id int) with NO foreign-key
 * constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('s_competency_frameworks')) {
            Schema::create('s_competency_frameworks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->string('version', 30)->default('v1.0');
                // draft | active | archived
                $table->string('status', 30)->default('draft')->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('jobrole', 191)->nullable();
                $table->unsignedBigInteger('created_by')->index()->nullable();
                $table->unsignedBigInteger('updated_by')->index()->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('s_competency_framework_items')) {
            Schema::create('s_competency_framework_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('framework_id')->index();
                $table->unsignedBigInteger('competency_id')->index();
                $table->string('required_proficiency', 50)->nullable();
                $table->unsignedBigInteger('created_by')->index()->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('s_competency_framework_weights')) {
            Schema::create('s_competency_framework_weights', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                // Null framework_id = the tenant's default weighting profile.
                $table->unsignedBigInteger('framework_id')->nullable()->index();
                $table->string('category', 191);
                $table->decimal('weight', 5, 2)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('s_competency_mapping_reviews')) {
            Schema::create('s_competency_mapping_reviews', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->string('jobrole', 191);
                $table->string('department', 191)->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('framework_id')->nullable()->index();
                $table->unsignedBigInteger('submitted_by')->nullable();   // tbluser.id
                $table->string('submitted_by_name', 191)->nullable();
                // pending | approved | rejected
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedInteger('changes_count')->default(0);
                $table->text('changes')->nullable();                      // human summary / JSON of edits
                $table->text('note')->nullable();                         // reviewer note
                $table->unsignedBigInteger('reviewer_id')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('s_competency_mapping_reviews');
        Schema::dropIfExists('s_competency_framework_weights');
        Schema::dropIfExists('s_competency_framework_items');
        Schema::dropIfExists('s_competency_frameworks');
    }
};
