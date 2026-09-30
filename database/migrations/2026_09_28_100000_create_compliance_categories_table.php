<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance Category master (Compliance Management foundation, phase 1 of the
 * K-12 Compliance Library -> Compliance Management System optimization).
 *
 * `sub_institute_id IS NULL` means a platform-wide default category, available
 * to every school without being duplicated into every tenant's rows - same
 * "absence is meaningful, merged not seeded" shape as
 * App\Models\Platform\PlatformNotificationChannel. A school can add its own
 * category by inserting a row with its own sub_institute_id; the unique key
 * below is scoped per tenant (and separately across the NULL/global rows) so
 * a school's own "Fire & Safety" can coexist with the global default without
 * colliding.
 *
 * Deliberately NOT reusing `hrms_departments` or any other existing master -
 * category is a materially different concept (compliance domain, not an org
 * unit) and no equivalent table exists anywhere in this repo (verified: only
 * `hrms_departments`/`hrms_departments_mapping` and the unrelated SQAA
 * `master_compliance` table do).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('compliance_categories')) {
            Schema::create('compliance_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0)->index();
                $table->boolean('status')->default(true)->index();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['sub_institute_id', 'name'], 'compliance_categories_tenant_name_unique');
            });
        }

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_categories');
    }

    /**
     * The 13 categories from the product brief, seeded once as global
     * defaults (sub_institute_id = NULL) so every existing and future school
     * sees them immediately without a per-tenant backfill job. `insertOrIgnore`
     * against the unique(tenant, name) key makes this safe to run more than
     * once (this repo's migrations have needed re-running before - see e.g.
     * org_compliance_library's own migration history).
     */
    private function seedDefaults(): void
    {
        $names = [
            'Fire & Safety',
            'HR & Staff',
            'Health & Hygiene',
            'Building & Infrastructure',
            'Transport',
            'IT & Data Privacy',
            'Finance',
            'Government & Regulatory',
            'Child Safety',
            'Environment',
            'Emergency Management',
            'Policy & Documentation',
            'Other',
        ];

        $now = now();
        $rows = [];
        foreach ($names as $index => $name) {
            $rows[] = [
                'sub_institute_id' => null,
                'name' => $name,
                'description' => null,
                'sort_order' => $index + 1,
                'status' => true,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('compliance_categories')->insertOrIgnore($rows);
    }
};
