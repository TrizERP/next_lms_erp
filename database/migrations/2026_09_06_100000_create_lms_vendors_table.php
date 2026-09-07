<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Training vendors for G2G-LMS Administration & Governance.
 *
 * Ported from hp_erp's `2026_07_29_100001_create_lms_vendors_table.php`
 * (`App\Http\Controllers\Api\LmsPartnerController`). New model - no vendor
 * concept exists anywhere in this schema. `lms_trainers.vendor_id` (next
 * migration) points here. Created before `lms_trainers` so that FK-shaped
 * reference resolves in migration order (no hard FK constraint either way,
 * matching this codebase's no-FK-across-modules convention).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_vendors')) {
            return;
        }
Schema::create('lms_vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            $table->string('name', 191);
            $table->string('vendor_code', 100)->nullable();
            $table->string('contact_person', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website', 191)->nullable();
            $table->text('address')->nullable();

            /** What they supply: 'content', 'trainers', 'platform', 'mixed'. */
            $table->string('service_type', 50)->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->decimal('contract_value', 14, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->boolean('status')->default(true);
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_vendors');
    }
};
