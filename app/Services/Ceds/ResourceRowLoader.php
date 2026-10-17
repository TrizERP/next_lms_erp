<?php

namespace App\Services\Ceds;

use Illuminate\Support\Facades\DB;

/**
 * SELECT-only reader that feeds ResourceResolver.
 *
 * It issues two SELECTs per chapter and nothing else: no insert, update, delete,
 * schema call or transaction. The tenant rule is the one the library, StudyDeckApiController
 * and PrayogshalaService already apply: a school reads its own rows, and a school flagged
 * is_Lms = 'Y' also reads the platform tenant's (config lms_content.platform_sub_institute_ids).
 *
 * Hidden content rows (show_hide = 0) ARE read, only so the resolver can count them; the
 * resolver never returns one. Soft-deleted or hidden labs are not read at all.
 */
class ResourceRowLoader
{
    /** @return list<int> sub_institute_ids this school may read */
    public function visibleTenants(int $tenant): array
    {
        $platform = array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]));
        $isLms = DB::table('school_setup')->where('Id', $tenant)->value('is_Lms');

        return $isLms === 'Y' ? array_values(array_unique(array_merge([$tenant], $platform))) : [$tenant];
    }

    /** The chapter if this school may see it, else null. */
    public function visibleChapter(int $chapterId, array $tenants): ?object
    {
        return DB::table('chapter_master')
            ->select('id', 'chapter_name', 'standard_id', 'subject_id', 'sub_institute_id')
            ->where('id', $chapterId)
            ->whereIn('sub_institute_id', $tenants)
            ->first();
    }

    /**
     * @param list<int> $tenants
     * @return array{content:list<array<string,mixed>>,labs:list<array<string,mixed>>}
     */
    public function load(int $chapterId, array $tenants): array
    {
        $content = DB::table('content_master')
            ->select('id', 'chapter_id', 'concept_id', 'title', 'filename', 'file_type', 'show_hide', 'content_category', 'source', 'sub_institute_id', 'created_at')
            ->where('chapter_id', $chapterId)
            ->whereIn('sub_institute_id', $tenants)
            ->orderBy('id')
            ->get();

        // The same filter PrayogshalaService applies for a reader: not deleted, shown. Review-status
        // labs ARE included (the resolver labels them "Pending Review"); lab_config is read only
        // as a has-a-lab flag so no lab content leaves the database here.
        $labs = DB::table('lms_prayogshala_activity')
            ->selectRaw('id, chapter_id, topic_id, concept_id, title, status, generation_status, sub_institute_id, (lab_config IS NOT NULL) AS lab_config')
            ->whereNull('deleted_at')
            ->where('show_hide', 1)
            ->where('chapter_id', $chapterId)
            ->whereIn('sub_institute_id', $tenants)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'content' => $content->map(fn ($r) => (array) $r)->all(),
            'labs' => $labs->map(fn ($r) => (array) $r)->all(),
        ];
    }
}
