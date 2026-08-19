<?php

namespace App\Services\result;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HpcActivityTransferService
{
    /**
     * All sub institutes available for selection.
     */
    public function getSubInstitutes(): array
    {
        return DB::table('school_setup')
            ->orderBy('SchoolName')
            ->get(['Id as id', 'SchoolName as name'])
            ->toArray();
    }

    /**
     * Standards belonging to one sub institute.
     */
    public function getStandards(int $subInstituteId): array
    {
        return DB::table('standard')
            ->where('sub_institute_id', $subInstituteId)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->toArray();
    }

    /* -----------------------------------------------------------------
     * Standard to Standard
     * ----------------------------------------------------------------- */

    public function previewStandardTransfer(int $subInstituteId, int $sourceStandardId, int $targetStandardId): array
    {
        return $this->runStandardTransfer($subInstituteId, $sourceStandardId, $targetStandardId, null, false);
    }

    public function transferStandardToStandard(int $subInstituteId, int $sourceStandardId, int $targetStandardId, $createdBy): array
    {
        return DB::transaction(function () use ($subInstituteId, $sourceStandardId, $targetStandardId, $createdBy) {
            return $this->runStandardTransfer($subInstituteId, $sourceStandardId, $targetStandardId, $createdBy, true);
        });
    }

    protected function runStandardTransfer(int $subInstituteId, int $sourceStandardId, int $targetStandardId, $createdBy, bool $commit): array
    {
        if ($sourceStandardId === $targetStandardId) {
            throw new InvalidArgumentException('Source and target standard must be different.');
        }

        $sourceStandard = DB::table('standard')->where('id', $sourceStandardId)->where('sub_institute_id', $subInstituteId)->first();
        $targetStandard = DB::table('standard')->where('id', $targetStandardId)->where('sub_institute_id', $subInstituteId)->first();

        if (! $sourceStandard) {
            throw new InvalidArgumentException('Source standard does not belong to the selected sub institute.');
        }
        if (! $targetStandard) {
            throw new InvalidArgumentException('Target standard does not belong to the selected sub institute.');
        }

        $skillsetResult = $this->processSkillsetsForStandardPair(
            $subInstituteId, $sourceStandardId, $sourceStandard->name,
            $subInstituteId, $targetStandardId, $targetStandard->name,
            $createdBy, $commit
        );

        $activityResult = $this->processActivityMastersForStandardPair(
            $subInstituteId, $sourceStandardId, $sourceStandard->name,
            $subInstituteId, $targetStandardId, $targetStandard->name,
            $createdBy, $commit, $skillsetResult['title_to_target_id']
        );

        return [
            'source_standard' => ['id' => $sourceStandard->id, 'name' => $sourceStandard->name],
            'target_standard' => ['id' => $targetStandard->id, 'name' => $targetStandard->name],
            // result_activity_group has no `standard` column - groups are shared at the
            // sub institute level, so nothing needs to move for a same-institute transfer.
            'activity_groups' => ['total' => 0, 'new' => 0, 'duplicate' => 0],
            'skill_sets' => $skillsetResult['stats'],
            'activity_masters' => $activityResult['stats'],
            'records' => array_merge($skillsetResult['records'], $activityResult['records']),
        ];
    }

    /* -----------------------------------------------------------------
     * Sub Institute to Sub Institute
     * ----------------------------------------------------------------- */

    public function previewSubInstituteTransfer(int $sourceSubInstituteId, int $targetSubInstituteId): array
    {
        return $this->runSubInstituteTransfer($sourceSubInstituteId, $targetSubInstituteId, null, false);
    }

    public function transferSubInstituteToSubInstitute(int $sourceSubInstituteId, int $targetSubInstituteId, $createdBy): array
    {
        return DB::transaction(function () use ($sourceSubInstituteId, $targetSubInstituteId, $createdBy) {
            return $this->runSubInstituteTransfer($sourceSubInstituteId, $targetSubInstituteId, $createdBy, true);
        });
    }

    protected function runSubInstituteTransfer(int $sourceSubInstituteId, int $targetSubInstituteId, $createdBy, bool $commit): array
    {
        if ($sourceSubInstituteId === $targetSubInstituteId) {
            throw new InvalidArgumentException('Source and target sub institute must be different.');
        }

        if (! DB::table('school_setup')->where('Id', $sourceSubInstituteId)->exists()) {
            throw new InvalidArgumentException('Source sub institute does not exist.');
        }
        if (! DB::table('school_setup')->where('Id', $targetSubInstituteId)->exists()) {
            throw new InvalidArgumentException('Target sub institute does not exist.');
        }

        $groupResult = $this->processActivityGroups($sourceSubInstituteId, $targetSubInstituteId, $createdBy, $commit);

        $standardMap = $this->matchStandardsByName($sourceSubInstituteId, $targetSubInstituteId);

        $skillsetStats = ['total' => 0, 'new' => 0, 'duplicate' => 0];
        $activityStats = ['total' => 0, 'new' => 0, 'duplicate' => 0, 'skillsets_auto_created' => 0];
        $records = $groupResult['records'];

        foreach ($standardMap['matched'] as $pair) {
            $skillsetResult = $this->processSkillsetsForStandardPair(
                $sourceSubInstituteId, $pair['source']->id, $pair['source']->name,
                $targetSubInstituteId, $pair['target']->id, $pair['target']->name,
                $createdBy, $commit
            );

            $activityResult = $this->processActivityMastersForStandardPair(
                $sourceSubInstituteId, $pair['source']->id, $pair['source']->name,
                $targetSubInstituteId, $pair['target']->id, $pair['target']->name,
                $createdBy, $commit, $skillsetResult['title_to_target_id']
            );

            $skillsetStats['total'] += $skillsetResult['stats']['total'];
            $skillsetStats['new'] += $skillsetResult['stats']['new'];
            $skillsetStats['duplicate'] += $skillsetResult['stats']['duplicate'];

            $activityStats['total'] += $activityResult['stats']['total'];
            $activityStats['new'] += $activityResult['stats']['new'];
            $activityStats['duplicate'] += $activityResult['stats']['duplicate'];
            $activityStats['skillsets_auto_created'] += $activityResult['stats']['skillsets_auto_created'];

            $records = array_merge($records, $skillsetResult['records'], $activityResult['records']);
        }

        return [
            'source_sub_institute_id' => $sourceSubInstituteId,
            'target_sub_institute_id' => $targetSubInstituteId,
            'activity_groups' => $groupResult['stats'],
            'skill_sets' => $skillsetStats,
            'activity_masters' => $activityStats,
            'unmatched_standards' => array_map(fn ($std) => $std->name, $standardMap['unmatched']),
            'records' => $records,
        ];
    }

    /* -----------------------------------------------------------------
     * Shared building blocks
     * ----------------------------------------------------------------- */

    /**
     * Match every source standard to a target standard with the same name.
     * Standards that have no same-named counterpart in the target sub
     * institute are reported separately and simply skipped.
     */
    protected function matchStandardsByName(int $sourceSubInstituteId, int $targetSubInstituteId): array
    {
        $sourceStandards = DB::table('standard')
            ->where('sub_institute_id', $sourceSubInstituteId)
            ->orderBy('sort_order')
            ->get();

        $targetStandards = DB::table('standard')
            ->where('sub_institute_id', $targetSubInstituteId)
            ->get()
            ->keyBy('name');

        $matched = [];
        $unmatched = [];

        foreach ($sourceStandards as $standard) {
            if (isset($targetStandards[$standard->name])) {
                $matched[] = ['source' => $standard, 'target' => $targetStandards[$standard->name]];
            } else {
                $unmatched[] = $standard;
            }
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    /**
     * Plan or execute the result_activity_group copy (sub institute wide,
     * not standard specific - table has no `standard` column).
     */
    protected function processActivityGroups(int $sourceSubInstituteId, int $targetSubInstituteId, $createdBy, bool $commit): array
    {
        $sourceGroups = DB::table('result_activity_group')
            ->where('sub_institute_id', $sourceSubInstituteId)
            ->orderBy('sort_order')
            ->get();

        $existingKeys = DB::table('result_activity_group')
            ->where('sub_institute_id', $targetSubInstituteId)
            ->get(['title', 'group', 'sort_order'])
            ->map(fn ($row) => $this->normalizeTitleForComparison($row->title).'||'.$row->group.'||'.$row->sort_order)
            ->flip()
            ->all();

        $stats = ['total' => $sourceGroups->count(), 'new' => 0, 'duplicate' => 0];
        $records = [];

        foreach ($sourceGroups as $group) {
            $key = $this->normalizeTitleForComparison($group->title).'||'.$group->group.'||'.$group->sort_order;
            $isDuplicate = isset($existingKeys[$key]);

            $records[] = [
                'type' => 'Activity Group',
                'title' => $group->title,
                'source_standard' => '-',
                'target_standard' => '-',
                'status' => $isDuplicate ? 'duplicate' : 'new',
            ];

            if ($isDuplicate) {
                $stats['duplicate']++;

                continue;
            }

            $stats['new']++;

            if ($commit) {
                DB::table('result_activity_group')->insert([
                    'title' => $group->title,
                    'sort_order' => $group->sort_order,
                    'group' => $group->group,
                    'sub_institute_id' => $targetSubInstituteId,
                    'created_by' => $createdBy,
                    'created_at' => now(),
                ]);
                $existingKeys[$key] = true;
            }
        }

        return ['stats' => $stats, 'records' => $records];
    }

    /**
     * Plan or execute result_skillset copy for one source/target standard pair.
     * Returns a title => target_skillset_id map (seeded with pre-existing target
     * rows) so the activity master step can resolve the new skill_id.
     */
    protected function processSkillsetsForStandardPair(
        int $sourceSubInstituteId, int $sourceStandardId, string $sourceStandardName,
        int $targetSubInstituteId, int $targetStandardId, string $targetStandardName,
        $createdBy, bool $commit
    ): array {
        $sourceSkillsets = DB::table('result_skillset')
            ->where('sub_institute_id', $sourceSubInstituteId)
            ->where('standard', $sourceStandardId)
            ->orderBy('sort_order')
            ->get();

        $existing = DB::table('result_skillset')
            ->where('sub_institute_id', $targetSubInstituteId)
            ->where('standard', $targetStandardId)
            ->get()
            ->keyBy(fn ($row) => $this->normalizeTitleForComparison($row->title));

        // Keyed by normalized title so activity-master resolution below (which
        // also normalizes the skillset title it looks up) lines up correctly.
        $titleToTargetId = $existing->map(fn ($row) => $row->id)->all();

        $stats = ['total' => $sourceSkillsets->count(), 'new' => 0, 'duplicate' => 0];
        $records = [];

        foreach ($sourceSkillsets as $skillset) {
            $normalizedTitle = $this->normalizeTitleForComparison($skillset->title);
            $isDuplicate = isset($titleToTargetId[$normalizedTitle]);

            $records[] = [
                'type' => 'Skill Set',
                'title' => $skillset->title,
                'source_standard' => $sourceStandardName,
                'target_standard' => $targetStandardName,
                'status' => $isDuplicate ? 'duplicate' : 'new',
            ];

            if ($isDuplicate) {
                $stats['duplicate']++;

                continue;
            }

            $stats['new']++;

            if ($commit) {
                $newId = DB::table('result_skillset')->insertGetId([
                    'main_title' => $skillset->main_title,
                    'title' => $skillset->title,
                    'standard' => $targetStandardId,
                    'sort_order' => $skillset->sort_order,
                    'main_sort_order' => $skillset->main_sort_order,
                    'group' => $skillset->group,
                    'sub_institute_id' => $targetSubInstituteId,
                    'created_by' => $createdBy,
                    'created_at' => now(),
                ]);
                $titleToTargetId[$normalizedTitle] = $newId;
            }
        }

        return ['stats' => $stats, 'records' => $records, 'title_to_target_id' => $titleToTargetId];
    }

    /**
     * Plan or execute result_activity_master copy for one source/target standard
     * pair. skill_id is never copied verbatim - it is re-resolved through the
     * matching skillset title in the target standard (from $titleToTargetSkillsetId,
     * which already reflects any skillsets just transferred above).
     *
     * If a source activity's skillset has no counterpart in the target standard
     * yet (e.g. the skillset itself is not standard-scoped consistently in the
     * source data), that skillset is auto-created in the target standard here so
     * the activity master is never silently dropped - only true duplicates are
     * skipped, everything else is inserted.
     */
    protected function processActivityMastersForStandardPair(
        int $sourceSubInstituteId, int $sourceStandardId, string $sourceStandardName,
        int $targetSubInstituteId, int $targetStandardId, string $targetStandardName,
        $createdBy, bool $commit, array $titleToTargetSkillsetId
    ): array {
        $sourceActivities = DB::table('result_activity_master as a')
            ->join('result_skillset as d', 'd.id', '=', 'a.skill_id')
            ->where('a.sub_institute_id', $sourceSubInstituteId)
            ->where('a.standard', $sourceStandardId)
            ->orderBy('a.sort_order')
            ->select(
                'a.*',
                'd.title as skill_title',
                'd.main_title as skill_main_title',
                'd.sort_order as skill_sort_order',
                'd.main_sort_order as skill_main_sort_order',
                'd.group as skill_group'
            )
            ->get();

        $existing = DB::table('result_activity_master')
            ->where('sub_institute_id', $targetSubInstituteId)
            ->where('standard', $targetStandardId)
            ->get(['title', 'skill_id', 'term_id'])
            ->map(fn ($row) => $this->normalizeTitleForComparison($row->title).'||'.$row->skill_id.'||'.$row->term_id)
            ->flip()
            ->all();

        $stats = ['total' => $sourceActivities->count(), 'new' => 0, 'duplicate' => 0, 'skillsets_auto_created' => 0];
        $records = [];
        $previewSkillsetPlaceholder = 0;

        foreach ($sourceActivities as $activity) {
            // $titleToTargetSkillsetId is keyed by normalized skillset title
            // (see processSkillsetsForStandardPair), so look it up the same way.
            $normalizedSkillTitle = $this->normalizeTitleForComparison($activity->skill_title);
            $targetSkillId = $titleToTargetSkillsetId[$normalizedSkillTitle] ?? null;
            $skillsetAutoCreated = false;

            if (! $targetSkillId) {
                // No matching skillset in the target standard - create it instead
                // of dropping the activity master that depends on it.
                $skillsetAutoCreated = true;
                $stats['skillsets_auto_created']++;

                if ($commit) {
                    $targetSkillId = DB::table('result_skillset')->insertGetId([
                        'main_title' => $activity->skill_main_title,
                        'title' => $activity->skill_title,
                        'standard' => $targetStandardId,
                        'sort_order' => $activity->skill_sort_order,
                        'main_sort_order' => $activity->skill_main_sort_order,
                        'group' => $activity->skill_group,
                        'sub_institute_id' => $targetSubInstituteId,
                        'created_by' => $createdBy,
                        'created_at' => now(),
                    ]);
                } else {
                    // Preview only - no DB write yet, use a placeholder id purely
                    // so duplicate activities referencing the same missing skillset
                    // resolve to the same (not-yet-real) id within this run.
                    $previewSkillsetPlaceholder--;
                    $targetSkillId = $previewSkillsetPlaceholder;
                }

                $titleToTargetSkillsetId[$normalizedSkillTitle] = $targetSkillId;
            }

            // term_id is part of the identity, not noise: sub institutes with
            // termwise HPC enabled legitimately store one row per term for the
            // same title/skill (see `general_data.termwise_hpc`), so it must be
            // included here or genuinely distinct per-term rows collapse into
            // "duplicates" of each other. The title itself is normalized so
            // trivial formatting differences (trailing period, doubled spaces,
            // spacing around a slash) aren't treated as distinct activities.
            $dupKey = $this->normalizeTitleForComparison($activity->title).'||'.$targetSkillId.'||'.$activity->term_id;
            $isDuplicate = ! $skillsetAutoCreated && isset($existing[$dupKey]);

            $records[] = [
                'type' => 'Activity',
                'title' => $activity->title,
                'source_standard' => $sourceStandardName,
                'target_standard' => $targetStandardName,
                'status' => $isDuplicate ? 'duplicate' : 'new',
                'reason' => $skillsetAutoCreated
                    ? 'Skill set "'.$activity->skill_title.'" was missing in the target standard and was created automatically'
                    : null,
            ];

            if ($isDuplicate) {
                $stats['duplicate']++;

                continue;
            }

            $stats['new']++;

            if ($commit) {
                DB::table('result_activity_master')->insert([
                    'title' => $activity->title,
                    'skill_id' => $targetSkillId,
                    'sort_order' => $activity->sort_order,
                    'standard' => $targetStandardId,
                    'term_id' => $activity->term_id,
                    'sub_institute_id' => $targetSubInstituteId,
                    'created_by' => $createdBy,
                    'created_at' => now(),
                ]);
                $existing[$dupKey] = true;
            }
        }

        return ['stats' => $stats, 'records' => $records];
    }

    /**
     * Normalize a title for duplicate comparison only - the raw source title
     * is always what actually gets inserted. Collapses whitespace runs (incl.
     * spacing around a slash, e.g. "a / b" vs "a/ b") and strips trailing
     * punctuation, so two rows that are the same activity but differ only by
     * a missing period or a doubled space aren't treated as distinct records.
     * Case is intentionally left untouched - matching here stays case-sensitive.
     */
    protected function normalizeTitleForComparison(?string $title): string
    {
        $normalized = (string) $title;
        $normalized = preg_replace('/\s*\/\s*/u', '/', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return rtrim($normalized, ".,;:!?");
    }
}
