<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hpbrain_tenants')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_tenants (
  id              VARCHAR(36) PRIMARY KEY,
  name            VARCHAR(255) NOT NULL,
  region          VARCHAR(100) NOT NULL DEFAULT \'default\',
  status          VARCHAR(50) NOT NULL DEFAULT \'provisioning\',
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_tenants');
    }
};
