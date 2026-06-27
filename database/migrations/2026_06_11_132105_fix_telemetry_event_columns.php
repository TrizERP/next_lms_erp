<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pal_telemetry_events', function (Blueprint $table) {
            // context_id was wrongly typed as json
            $table->string('context_id')->nullable()->change();
            
            // actor_id should accept learner identifiers (string for now)
            $table->string('actor_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('pal_telemetry_events', function (Blueprint $table) {
            $table->json('context_id')->nullable()->change();
            $table->unsignedBigInteger('actor_id')->change();
        });
    }
};