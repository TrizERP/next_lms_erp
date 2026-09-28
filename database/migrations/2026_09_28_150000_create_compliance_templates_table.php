<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance Management, frontend-completion pass: backs the "Create from
 * Template" action the frontend spec requires. No equivalent table existed
 * anywhere in this repo (verified). Follows the same tenant-or-global shape
 * as `compliance_categories` (2026_09_28_100000): `sub_institute_id IS NULL`
 * rows are platform-wide defaults (e.g. "Fire Safety Inspection", "First Aid
 * Kit Inspection" from the product brief), a school can add its own scoped
 * to its own tenant.
 *
 * FK `category_id` -> `compliance_categories.id` is nullable (a template
 * need not pin a category) and `NO ACTION` on delete, matching every other
 * FK added in this module's migrations - deleting a category a template
 * references is a business decision the app should surface, not a cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compliance_templates')) {
            return;
        }

        Schema::create('compliance_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();

            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('default_frequency', 30)->nullable();
            $table->string('default_custom_frequency_details', 191)->nullable();
            $table->string('default_priority', 20)->default('Medium');

            $table->integer('sort_order')->default(0)->index();
            $table->boolean('status')->default(true)->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id', 'compliance_templates_category_id_foreign')
                ->references('id')->on('compliance_categories')
                ->onDelete('NO ACTION')->onUpdate('NO ACTION');
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_templates');
    }

    /** The 5 example templates from the product brief, seeded as global defaults. */
    private function seedDefaults(): void
    {
        $categoryId = fn (string $name) => \Illuminate\Support\Facades\DB::table('compliance_categories')
            ->whereNull('sub_institute_id')
            ->where('name', $name)
            ->value('id');

        $now = now();
        $templates = [
            ['name' => 'Fire Safety Inspection', 'category' => 'Fire & Safety', 'frequency' => 'Yearly', 'priority' => 'Critical', 'description' => 'Annual inspection and certification of fire safety equipment and evacuation readiness.'],
            ['name' => 'First Aid Kit Inspection', 'category' => 'Health & Hygiene', 'frequency' => 'Monthly', 'priority' => 'Medium', 'description' => 'Monthly check of first aid kit stock, expiry dates, and accessibility.'],
            ['name' => 'Staff Background Verification', 'category' => 'HR & Staff', 'frequency' => 'Yearly', 'priority' => 'High', 'description' => 'Annual background verification renewal for staff in child-facing roles.'],
            ['name' => 'CCTV Audit', 'category' => 'IT & Data Privacy', 'frequency' => 'Quarterly', 'priority' => 'Medium', 'description' => 'Quarterly audit of CCTV coverage, retention, and access logs.'],
            ['name' => 'Building Safety Inspection', 'category' => 'Building & Infrastructure', 'frequency' => 'Yearly', 'priority' => 'High', 'description' => 'Annual structural and building safety inspection.'],
        ];

        $rows = [];
        foreach ($templates as $index => $template) {
            $rows[] = [
                'sub_institute_id' => null,
                'name' => $template['name'],
                'description' => $template['description'],
                'category_id' => $categoryId($template['category']),
                'default_frequency' => $template['frequency'],
                'default_custom_frequency_details' => null,
                'default_priority' => $template['priority'],
                'sort_order' => $index + 1,
                'status' => true,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // No natural unique key to insertOrIgnore against here (unlike
        // categories' unique(tenant, name)) - guarded manually so a re-run
        // does not duplicate rows.
        if (\Illuminate\Support\Facades\DB::table('compliance_templates')->whereNull('sub_institute_id')->exists()) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('compliance_templates')->insert($rows);
    }
};
