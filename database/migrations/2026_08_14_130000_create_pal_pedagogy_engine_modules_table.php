<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module-level metadata for the Pedagogy Engine (title, description, version,
 * provenance label shown in the PAL header).
 *
 * Previously these strings lived in PedagogyEngineService. Moved into the
 * database so that every character the PAL UI renders -- header included, not
 * just the rule rows -- is editable data rather than deployed code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pal_pedagogy_engine_modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('module_name');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('version')->nullable();
            $table->string('source_label')->nullable(); // provenance shown in the UI header
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_pedagogy_engine_modules');
    }
};
