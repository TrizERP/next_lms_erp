<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widgets a user asked the dashboard assistant to add. One row per widget, private to that user.
 *
 * `spec` holds the choice that was made from the fixed catalog (metric, breakdown, chart, title), never a query:
 * the figures are worked out each time the dashboard opens, from the catalog's own read-only code.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashboard_custom_widgets')) {
            return;
        }

        Schema::create('dashboard_custom_widgets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('sub_institute_id');
            $table->unsignedInteger('user_id');
            $table->string('role', 40);
            $table->text('spec');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sub_institute_id', 'user_id'], 'dashboard_custom_widgets_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_custom_widgets');
    }
};
