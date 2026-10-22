<?php

namespace App\Services\CoursePresentation;

use Illuminate\Support\Facades\DB;

/**
 * Reads, and only reads, the stored material that could feed a Course Presentation for one chapter.
 *
 * Every query is keyed on the chapter (and the school where the table has one). The rows come back untouched:
 * deciding what belongs to the chapter, and what is missing, is SourceRegistry's job, so a row that sits under
 * the wrong chapter, subject, standard or school is still seen here and rejected there.
 */
class SourceLoader
{
    /** The chapter, only if the school can see it (it belongs to that school). */
    public function chapter(int $chapterId, int $tenantId): ?array
    {
        $row = DB::table('chapter_master')
            ->leftJoin('standard as s', 's.id', '=', 'chapter_master.standard_id')
            ->leftJoin('subject as sub', 'sub.id', '=', 'chapter_master.subject_id')
            ->where('chapter_master.id', $chapterId)->where('chapter_master.sub_institute_id', $tenantId)
            ->first(['chapter_master.id', 'chapter_master.chapter_name', 'chapter_master.standard_id', 'chapter_master.subject_id', 'chapter_master.sub_institute_id', 'chapter_master.extraction_id', 's.name as standard_name', 'sub.subject_name']);

        return $row ? (array) $row : null;
    }

    /**
     * The visible concepts of the chapter as the deck generator reads them (id => name), nothing else: the chapter's own
     * list of what a complete presentation must teach. Same visibility rule as load().
     *
     * @return array<int,string>
     */
    public function conceptNames(int $chapterId): array
    {
        $out = [];
        foreach (DB::table('lms_concept')->where('chapter_id', $chapterId)
            ->where(fn ($q) => $q->whereNull('concept_show_hide')->orWhere('concept_show_hide', '<>', 0))
            ->orderBy('id')->get(['id', 'name']) as $row) {
            $out[(int) $row->id] = (string) $row->name;
        }

        return $out;
    }

    /** @return array<string,mixed> */
    public function load(array $chapter): array
    {
        $chapterId = (int) $chapter['id'];

        // The chapter text: the extraction the chapter points at, plus any other 'Chapter' extraction filed under it.
        $textbook = DB::table('document_extractions')
            ->where('document_type', 'Chapter')
            ->where(function ($q) use ($chapter, $chapterId) {
                $q->where('chapter_id', $chapterId);
                if (!empty($chapter['extraction_id'])) {
                    $q->orWhere('id', (int) $chapter['extraction_id']);
                }
            })
            ->get(['id', 'document_type', 'chapter_id', 'standard_id', 'subject_id', 'sub_institute_id', 'md_content'])
            ->map(fn ($r) => (array) $r)->all();

        $outcomes = DB::table('lms_learning_outcomes')
            ->where('chapter_id', $chapterId)
            ->get(['id', 'curriculum_id', 'extraction_id', 'standard_id', 'subject_id', 'chapter_id', 'type', 'code', 'description'])
            ->map(fn ($r) => (array) $r)->all();

        $curriculumIds = array_values(array_unique(array_filter(array_column($outcomes, 'curriculum_id'))));
        $curricula = $curriculumIds ? DB::table('lms_curriculum')
            ->whereIn('id', $curriculumIds)
            ->get(['id', 'extraction_id', 'sub_institute_id', 'standard_id', 'subject_id', 'curriculum_name', 'status'])
            ->map(fn ($r) => (array) $r)->all() : [];

        $curriculumDocIds = array_values(array_unique(array_filter(array_column($curricula, 'extraction_id'))));
        $curriculumDocs = $curriculumDocIds ? DB::table('document_extractions')
            ->whereIn('id', $curriculumDocIds)
            ->get(['id', 'document_type', 'chapter_id', 'standard_id', 'subject_id', 'sub_institute_id', 'md_content'])
            ->map(fn ($r) => (array) $r)->all() : [];

        $semantic = DB::table('semantic_intelligence')
            ->where('chapter_id', $chapterId)
            ->get(['id', 'extraction_id', 'sub_institute_id', 'standard_id', 'subject_id', 'chapter_id', 'total_concepts', 'qulity_flag', 'full_intelegance_json'])
            ->map(fn ($r) => (array) $r)->all();

        // The concept records and topics the deck generator reads (visible ones, in its order).
        $concepts = DB::table('lms_concept')->where('chapter_id', $chapterId)
            ->where(fn ($q) => $q->whereNull('concept_show_hide')->orWhere('concept_show_hide', '<>', 0))
            ->orderBy('id')->get(['id', 'name', 'description', 'definition', 'topic_id'])->map(fn ($r) => (array) $r)->all();
        $topics = DB::table('topic_master')->where('chapter_id', $chapterId)
            ->where(fn ($q) => $q->whereNull('topic_show_hide')->orWhere('topic_show_hide', '<>', 0))
            ->orderBy('topic_sort_order')->orderBy('id')->get(['id', 'name', 'description'])->map(fn ($r) => (array) $r)->all();

        $projectionRows = (int) DB::table('lms_concept_intelligence_index')->where('chapter_id', $chapterId)->count();

        $content = DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->get(['id', 'standard_id', 'subject_id', 'chapter_id', 'sub_institute_id', 'content_category', 'title', 'filename', 'show_hide', 'source'])
            ->map(fn ($r) => (array) $r)->all();

        $prayogshala = DB::table('lms_prayogshala_activity')
            ->where('chapter_id', $chapterId)->whereNull('deleted_at')
            ->get(['id', 'sub_institute_id', 'status'])->map(fn ($r) => (array) $r)->all();

        return [
            'textbook' => $textbook,
            'outcomes' => $outcomes,
            'curricula' => $curricula,
            'curriculum_docs' => $curriculumDocs,
            'semantic' => $semantic,
            'concepts' => $concepts,
            'topics' => $topics,
            'projection_rows' => $projectionRows,
            'content' => $content,
            'prayogshala' => $prayogshala,
            'bundle' => $this->bundle($chapterId),
        ];
    }

    /**
     * The result of an earlier, unstored generation run for this chapter, if one is on disk: only whether its own
     * validation passed. It is never opened further and never reused.
     *
     * @return array{found:bool,ok:?bool,errors:array<int,string>,teaching_method:?string}
     */
    private function bundle(int $chapterId): array
    {
        $report = storage_path((string) config('course_presentation_pilot.earlier_bundle_base', 'app/study-deck') . '/chapter-' . $chapterId . '/report.json');
        if (!is_file($report)) {
            return ['found' => false, 'ok' => null, 'errors' => [], 'teaching_method' => null];
        }
        $decoded = json_decode((string) @file_get_contents($report), true);

        return [
            'found' => true,
            'ok' => is_array($decoded) && array_key_exists('ok', $decoded) ? (bool) $decoded['ok'] : null,
            'errors' => is_array($decoded) ? array_values(array_map('strval', array_slice((array) ($decoded['errors'] ?? []), 0, 3))) : [],
            // A deck made for a teaching method says so; one made without a method does not.
            'teaching_method' => is_array($decoded) ? ($decoded['teaching_method']['key'] ?? null) : null,
        ];
    }
}
