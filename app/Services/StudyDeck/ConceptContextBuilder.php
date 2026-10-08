<?php

namespace App\Services\StudyDeck;

use Illuminate\Support\Facades\DB;

/**
 * Loads everything the study deck is built from, and folds it into one context.
 *
 * Two halves, deliberately separate so the second is testable with no database:
 *   load()     read-only SELECTs against the LMS tables
 *   assemble() pure: raw rows -> the per-concept context the planner consumes
 *
 * Authority, in order: chapter.md (facts) > Concept Intelligence (understanding,
 * sequencing, misconceptions) > existing AI content (baseline only). Nothing
 * here knows a subject, a chapter id or a concept name.
 */
class ConceptContextBuilder
{
    /** Categories that hold a previously generated lesson worth using as a baseline. */
    private const BASELINE_CATEGORIES = ['Classroom Presentation', 'Presentation', 'Revision Notes'];

    private const BASELINE_CHARS = 5000;

    /** @return array<string,mixed> raw rows, untouched */
    public function load(int $chapterId, int $tenantId): array
    {
        $chapter = DB::table('chapter_master as cm')
            ->join('standard as s', 's.id', '=', 'cm.standard_id')
            ->join('subject as sub', 'sub.id', '=', 'cm.subject_id')
            ->where('cm.id', $chapterId)
            ->first(['cm.id', 'cm.chapter_name', 'cm.standard_id', 'cm.subject_id', 'cm.grade_id',
                'cm.sub_institute_id', 'cm.syear', 's.name as standard_name', 'sub.subject_name']);

        if (!$chapter) {
            throw new \InvalidArgumentException('Chapter ' . $chapterId . ' not found.');
        }

        $si = DB::table('semantic_intelligence as si')
            ->leftJoin('document_extractions as de', 'de.id', '=', 'si.extraction_id')
            ->where('si.chapter_id', $chapterId)
            ->first(['si.full_intelegance_json', 'si.learning_objective', 'de.md_content']);

        $intelligence = json_decode($this->utf8((string) ($si->full_intelegance_json ?? '')), true);
        $entries = is_array($intelligence)
            ? ($intelligence['concepts'] ?? $intelligence['intelligence']['concepts'] ?? [])
            : [];

        $topics = DB::table('topic_master')
            ->where('chapter_id', $chapterId)
            ->where(fn ($q) => $q->whereNull('topic_show_hide')->orWhere('topic_show_hide', '<>', 0))
            ->orderBy('topic_sort_order')->orderBy('id')
            ->get(['id', 'name', 'description', 'estimated_minutes'])->map(fn ($r) => (array) $r)->all();

        $concepts = DB::table('lms_concept')
            ->where('chapter_id', $chapterId)
            ->where(fn ($q) => $q->whereNull('concept_show_hide')->orWhere('concept_show_hide', '<>', 0))
            ->orderBy('id')
            ->get(['id', 'name', 'description', 'definition', 'topic_id', 'estimated_mastery_minutes'])
            ->map(fn ($r) => (array) $r)->all();

        $ids = array_column($concepts, 'id');
        $prereqs = $ids ? DB::table('concept_prerequisite')
            ->whereIn('concept_id', $ids)->where('status', 'approved')
            ->get(['concept_id', 'prerequisite_id', 'link_type', 'is_gate', 'reason'])
            ->map(fn ($r) => (array) $r)->all() : [];

        $baseline = DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('source', config('claude.source_label', 'Claude AI'))
            ->whereIn('content_category', self::BASELINE_CATEGORIES)
            ->whereNotNull('description')
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->orderByDesc('id')
            ->get(['id', 'content_category', 'description'])->map(fn ($r) => (array) $r)->all();

        $questions = $ids ? DB::table('lms_question_master as q')
            ->leftJoin('question_type_master as t', 't.id', '=', 'q.question_type_id')
            // The extraction sidecar carries the form the question-bank API reports; the deck must agree with it.
            ->leftJoin('lms_question_extraction as e', 'e.question_id', '=', 'q.id')
            ->where('q.chapter_id', $chapterId)->whereIn('q.concept_id', $ids)
            ->whereNull('q.deleted_at')->where('q.status', 1)
            ->get(['q.id', 'q.concept_id', 'q.question_title', 'q.points', 'q.answer', 'q.g_bloom', 'q.g_difficulty', 'q.g_dok', 'q.question_format_code', 'q.g_qtype_code', 'e.question_type_code as sidecar_code', 't.question_type'])
            ->map(fn ($r) => (array) $r)->all() : [];

        return [
            'chapter' => (array) $chapter,
            'ground_truth' => $this->utf8((string) ($si->md_content ?? '')),
            'learning_objective' => $si->learning_objective ?? null,
            'intelligence' => $entries,
            'topics' => $topics,
            'concepts' => $concepts,
            'prerequisites' => $prereqs,
            'baseline' => $baseline,
            'questions' => $questions,
        ];
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public function assemble(array $raw): array
    {
        $byName = [];
        foreach ((array) $raw['intelligence'] as $entry) {
            $name = $this->key($entry['concept']['concept_name'] ?? '');
            if ($name !== '') {
                $byName[$name] = $this->mergeEntry($byName[$name] ?? [], $entry);
            }
        }

        $concepts = [];
        $unmatched = [];
        foreach ((array) $raw['concepts'] as $row) {
            $intel = $byName[$this->key($row['name'])] ?? null;
            if ($intel === null) {
                $unmatched[] = $row['name'];
            }
            $concepts[(int) $row['id']] = $this->conceptContext($row, $intel ?? []);
        }

        $knownIds = array_keys($concepts);
        $idByName = [];
        foreach ($concepts as $id => $c) {
            $idByName[$this->key($c['name'])] = $id;
        }

        $edges = [];
        foreach ((array) $raw['prerequisites'] as $p) {
            $edges[] = [
                'concept_id' => (int) $p['concept_id'],
                'requires_id' => (int) $p['prerequisite_id'],
                'in_chapter' => in_array((int) $p['prerequisite_id'], $knownIds, true),
                'type' => $p['link_type'],
                'gate' => (bool) $p['is_gate'],
                'reason' => $p['reason'],
            ];
        }

        // A relationship named in the intelligence resolves to a concept id when
        // its target is another concept of this chapter; anything else is an idea
        // from outside the chapter and is kept as text only.
        foreach ($concepts as &$c) {
            foreach ($c['relationships'] as &$rel) {
                $rel['target_id'] = $idByName[$this->key($rel['target'])] ?? null;
            }
            unset($rel);
        }
        unset($c);

        $topics = [];
        foreach ((array) $raw['topics'] as $t) {
            $topics[(int) $t['id']] = [
                'id' => (int) $t['id'],
                'name' => $t['name'],
                'description' => $t['description'] ?? null,
                'minutes' => $t['estimated_minutes'] ?? null,
                'concept_ids' => array_values(array_keys(array_filter($concepts, fn ($c) => $c['topic_id'] === (int) $t['id']))),
            ];
        }

        return [
            'chapter' => $raw['chapter'],
            'ground_truth' => $raw['ground_truth'],
            'learning_objective' => $raw['learning_objective'],
            'topics' => $topics,
            'concepts' => $concepts,
            'prerequisite_edges' => $edges,
            'baseline' => $this->baseline((array) $raw['baseline']),
            'unmatched_concepts' => $unmatched,
        ];
    }

    /** Several intelligence entries can describe one concept; union their lists. */
    private function mergeEntry(array $into, array $entry): array
    {
        if (!$into) {
            return $entry;
        }
        foreach ($entry as $k => $v) {
            if (is_array($v) && array_is_list($v) && is_array($into[$k] ?? null)) {
                $into[$k] = array_merge($into[$k], $v);
            }
        }

        return $into;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $i
     */
    private function conceptContext(array $row, array $i): array
    {
        $take = fn (string $key, string $field, int $max = 6) => array_slice(array_values(array_unique(array_filter(
            array_map(fn ($x) => is_array($x) ? trim((string) ($x[$field] ?? '')) : '', (array) ($i[$key] ?? []))
        ))), 0, $max);

        $blooms = [];
        foreach ((array) ($i['blooms'] ?? []) as $b) {
            if (((float) ($b['coverage_score'] ?? 0)) >= 0.3 && !empty($b['level'])) {
                $blooms[] = strtolower((string) $b['level']);
            }
        }

        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'topic_id' => $row['topic_id'] !== null ? (int) $row['topic_id'] : null,
            'definition' => $i['concept']['definition'] ?? ($row['definition'] ?: $row['description']),
            'importance' => $i['concept']['importance'] ?? null,
            'difficulty' => $i['concept']['difficulty'] ?? null,
            'minutes' => $row['estimated_mastery_minutes'] ?? null,
            'objectives' => $take('learning_objectives', 'objective', 4),
            'knowledge' => $take('knowledge_items', 'statement', 5),
            'terms' => $take('knowledge_items', 'knowledge', 6),
            'misconceptions' => array_slice(array_values(array_filter(array_map(
                fn ($m) => is_array($m) && !empty($m['misconception']) ? [
                    'wrong_idea' => $m['misconception'],
                    'statement' => $m['statement'] ?? null,
                    'correction' => $m['correction'] ?? null,
                ] : null,
                (array) ($i['misconceptions'] ?? [])
            ))), 0, 3),
            'real_world' => $take('real_world_applications', 'example', 3),
            'pedagogy' => $take('pedagogy_recommendations', 'strategy', 3),
            'blooms' => array_values(array_unique($blooms)),
            'dok' => array_values(array_unique(array_map(fn ($d) => (int) ($d['level'] ?? 0), (array) ($i['dok'] ?? [])))),
            'relationships' => array_values(array_map(
                fn ($r) => ['type' => $r['relation_type'] ?? 'related_to', 'target' => $r['target_concept'] ?? ''],
                array_filter((array) ($i['concept_relationships'] ?? []), fn ($r) => !empty($r['target_concept']))
            )),
        ];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function baseline(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $cat = (string) $r['content_category'];
            if (isset($out[$cat])) {
                continue; // newest only
            }
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $r['description']))));
            if ($text !== '') {
                $out[$cat] = [
                    'content_master_id' => (int) $r['id'],
                    'category' => $cat,
                    'text' => mb_substr($text, 0, self::BASELINE_CHARS),
                ];
            }
        }

        return array_values($out);
    }

    private function key(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    private function utf8(string $s): string
    {
        return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
}
