<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from hp_erp's `s_level_responsibility` table (schema captured via
 * SHOW CREATE TABLE / migration source). This is a global Level-of-
 * Responsibility competency framework - no `sub_institute_id` column exists
 * in the G2G source either (confirmed: querying it by sub_institute_id
 * errors "Unknown column" in hp_erp's own database), so unlike the
 * jobrole/skill/task tables, this one is genuinely shared reference data,
 * not tenant-owned - safe to copy wholesale (see the paired data-migration
 * command).
 */
class CreateSLevelResponsibilityTable extends Migration
{
    public function up()
    {
        Schema::create('s_level_responsibility', function (Blueprint $table) {
            $table->id();
            $table->string('level', 10);
            $table->string('guiding_phrase', 50);
            $table->text('essence_level')->nullable();
            $table->text('guidance_notes')->nullable();
            $table->string('attribute_code', 10)->nullable();
            $table->string('attribute_name', 50)->nullable();
            $table->string('attribute_type', 50)->nullable();
            $table->text('attribute_overall_description')->nullable();
            $table->text('attribute_guidance_notes')->nullable();
            $table->text('attribute_description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('s_level_responsibility');
    }
}
