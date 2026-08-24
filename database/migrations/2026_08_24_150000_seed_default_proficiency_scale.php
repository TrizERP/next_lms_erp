<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds a real default 5-level tenant-global proficiency scale
 * (skill_id = null) for every provisioned institute, so the Competency
 * Framework Studio's Proficiency Scale tab shows real content instead of
 * the frontend's generic "Level N" / "No description." fallback (used only
 * when `s_proficiency_levels` has zero rows for a tenant).
 *
 * G2G's own `database/seeders/proficiencyLevel.php` only inserts placeholder
 * text ("Description for proficiency level") and uses a non-numeric
 * `proficiency_type` ('Autonomy') that this port's
 * `CompetencyStudioController::proficiencyScale()` can't order
 * (`CAST(proficiency_type AS UNSIGNED)`) - not a usable source to port
 * verbatim, so this is a genuinely new, real 5-level scale instead.
 *
 * Idempotent: skips any institute that already has at least one
 * tenant-global row (skill_id IS NULL), so this never duplicates or
 * overwrites a scale someone has already configured via "Edit Scale".
 */
return new class extends Migration
{
    private const LEVELS = [
        ['type' => 1, 'label' => 'Level 1', 'name' => 'Awareness', 'description' => 'Has basic awareness of the competency; understands what it involves but has little or no practical experience applying it.'],
        ['type' => 2, 'label' => 'Level 2', 'name' => 'Basic', 'description' => 'Can perform simple tasks with guidance; needs support from others for anything beyond routine situations.'],
        ['type' => 3, 'label' => 'Level 3', 'name' => 'Intermediate', 'description' => 'Applies the competency independently in familiar situations; handles most day-to-day work without supervision.'],
        ['type' => 4, 'label' => 'Level 4', 'name' => 'Advanced', 'description' => 'Applies the competency confidently in complex or unfamiliar situations; can guide and support others.'],
        ['type' => 5, 'label' => 'Level 5', 'name' => 'Expert', 'description' => 'Recognised as an expert; sets direction and standards for the competency and mentors others across the organisation.'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('s_proficiency_levels') || ! Schema::hasTable('tbluser')) {
            return;
        }

        // `school_detail`/`institute_detail` were tried first but don't cover
        // every real tenant - confirmed sub_institute_id=1 (an active tenant
        // with real competency/tbluser rows) is absent from both. `tbluser`
        // is populated for every tenant that has ever logged in, so distinct
        // sub_institute_id there is the reliable source of "real tenants."
        $instituteIds = DB::table('tbluser')
            ->whereNotNull('sub_institute_id')
            ->distinct()
            ->pluck('sub_institute_id')
            ->all();

        foreach ($instituteIds as $sid) {
            $hasScale = DB::table('s_proficiency_levels')
                ->where('sub_institute_id', $sid)
                ->whereNull('skill_id')
                ->exists();

            if ($hasScale) {
                continue;
            }

            foreach (self::LEVELS as $level) {
                DB::table('s_proficiency_levels')->insert([
                    'skill_id'          => null,
                    'sub_institute_id'  => $sid,
                    'proficiency_type'  => (string) $level['type'],
                    'proficiency_level' => $level['label'],
                    'type_description'  => $level['name'],
                    'description'       => $level['description'],
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }
    }

    /**
     * Deliberately does not delete rows - by the time this rolls back an
     * institute may have edited these levels into real, in-use data.
     */
    public function down(): void
    {
        // no-op
    }
};
