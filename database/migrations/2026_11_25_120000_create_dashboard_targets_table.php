<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What "on track" means, set by the school itself.
 *
 * The trustee dashboard judges a figure against a target the school chose (a collection rate to reach, a number of
 * admissions to make). There is no default: a metric with no row is shown as "no target set" rather than judged
 * against a number the system invented. One row per school, academic year and metric.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashboard_targets')) {
            return;
        }

        Schema::create('dashboard_targets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('sub_institute_id');
            $table->unsignedInteger('syear');
            $table->string('metric', 40);
            $table->decimal('value', 12, 2);
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['sub_institute_id', 'syear', 'metric'], 'dashboard_targets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_targets');
    }
};
