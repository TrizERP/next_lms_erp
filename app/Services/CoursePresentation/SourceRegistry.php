<?php

namespace App\Services\CoursePresentation;

use App\Services\CoursePresentation\Templates\ConceptCoverage;

/**
 * Decides, from rows already read, which stored material really belongs to the chapter and how complete it is.
 *
 * Pure: no database, no files. A row counts only when its chapter (where it has one), standard and subject match
 * the chapter, and its school matches wherever the table has one. Anything else is rejected and counted, never
 * attached. Nothing is invented: a category with no matching row is reported as missing, with what that means.
 *
 * Statuses: present | incomplete | missing | conflicting.
 */
class SourceRegistry
{
    public function __construct(private int $minTextChars = 2000)
    {
    }

    /**
     * @param array<string,mixed> $chapter chapter_master row
     * @param array<string,mixed> $raw     SourceLoader::load()
     * @return array{sources:array<int,array<string,mixed>>,existing_content:array<int,array<string,mixed>>,existing_resources:array<int,array<string,mixed>>,not_used:array<int,array<string,mixed>>}
     */
    public function build(array $chapter, array $raw): array
    {
        // SOURCE MATERIAL: what a presentation is written from. Whether a presentation already exists is a different
        // question and is reported apart (existing_content), so a missing presentation can never make a valid
        // textbook look missing.
        // Learning outcomes and the curriculum they hang from are OPTIONAL context: used when the school has stored them,
        // and not mentioned at all when it has not (no warning, no block, nothing invented in their place).
        $sources = array_values(array_filter([
            $this->curriculum($chapter, $raw),
            $this->textbook($chapter, $raw),
            $this->outcomes($chapter, $raw),
            $this->semantic($chapter, $raw),
        ], fn (array $s) => !(in_array($s['key'], ['curriculum', 'outcomes'], true) && $s['status'] === 'missing')));

        return [
            'sources' => $sources,
            'existing_content' => [
                $this->chapterContent($chapter, $raw),
                $this->studyDeckMaterial($raw),
            ],
            'existing_resources' => $this->existingResources($chapter, $raw),
            'not_used' => $this->notUsed($chapter, $raw),
        ];
    }

    /** Standard, subject, and (when the row has them) chapter and school must all equal the chapter's. */
    private function belongs(array $chapter, array $row, bool $chapterScoped = true): bool
    {
        if ((int) ($row['standard_id'] ?? 0) !== (int) $chapter['standard_id'] || (int) ($row['subject_id'] ?? 0) !== (int) $chapter['subject_id']) {
            return false;
        }
        if (array_key_exists('sub_institute_id', $row) && (int) $row['sub_institute_id'] !== (int) $chapter['sub_institute_id']) {
            return false;
        }
        if ($chapterScoped && array_key_exists('chapter_id', $row) && (int) $row['chapter_id'] !== (int) $chapter['id']) {
            return false;
        }

        return true;
    }

    /**
     * Three separate questions about a source, never one label: is it THERE (availability), is it GOOD (quality), and
     * does generating slides NEED it (needed_for_slides). `status` stays as the one-word summary of the first two.
     */
    private function entry(string $key, string $label, string $status, string $summary, string $impact, array $records = [], int $rejected = 0, string $review = 'not_applicable'): array
    {
        [$availability, $quality] = match ($status) {
            'present' => ['available', 'complete'],
            'incomplete' => ['available', 'partial'],
            'conflicting' => ['conflicting', 'unknown'],
            default => ['unavailable', 'not_applicable'],
        };

        return ['key' => $key, 'label' => $label, 'status' => $status, 'availability' => $availability, 'quality' => $quality,
            'needed_for_slides' => in_array($key, (array) config('course_presentation_pilot.format_capability.interactive_slides.required_sources', []), true),
            'review_status' => $review, 'summary' => $summary, 'impact' => $impact, 'records' => $records, 'rejected' => $rejected];
    }

    private function curriculum(array $chapter, array $raw): array
    {
        $rows = (array) $raw['curricula'];
        $ok = array_values(array_filter($rows, fn ($r) => $this->belongs($chapter, $r, false) && strtolower((string) ($r['status'] ?? '')) === 'active'));
        $rejected = count($rows) - count($ok);
        $label = 'Curriculum framework';
        $impact = 'Without it the presentation cannot be aligned to the curriculum.';

        if (!$ok) {
            return $this->entry('curriculum', $label, 'missing', 'No active curriculum for this standard and subject is linked to the chapter\'s learning outcomes.', $impact, [], $rejected);
        }
        if (count($ok) > 1) {
            return $this->entry('curriculum', $label, 'conflicting', 'More than one active curriculum is linked to the chapter\'s learning outcomes.', 'Which curriculum applies must be settled before generation.', $this->refs('lms_curriculum', $ok), $rejected);
        }
        $c = $ok[0];
        $docs = array_values(array_filter((array) $raw['curriculum_docs'], fn ($d) => (int) $d['id'] === (int) ($c['extraction_id'] ?? 0)
            && strtolower((string) $d['document_type']) === 'curriculum' && $this->belongs($chapter, $d, false)));
        $chars = $docs ? mb_strlen(trim((string) $docs[0]['md_content'])) : 0;
        $records = $this->refs('lms_curriculum', $ok);
        if ($docs) {
            $records[] = ['type' => 'document_extractions', 'ref' => (int) $docs[0]['id'], 'status' => 'stored', 'characters' => $chars];
        }
        if ($chars < $this->minTextChars) {
            return $this->entry('curriculum', $label, 'incomplete', $docs ? 'The curriculum document is very short.' : 'The curriculum record has no readable document behind it.', $impact, $records, $rejected);
        }

        return $this->entry('curriculum', $label, 'present', 'An active curriculum with its document is linked to the chapter.', $impact, $records, $rejected);
    }

    private function textbook(array $chapter, array $raw): array
    {
        $label = 'Textbook chapter text';
        $impact = 'The presentation\'s facts are taken from this text; without it nothing can be grounded.';
        $rows = (array) $raw['textbook'];
        $ok = array_values(array_filter($rows, fn ($r) => strtolower((string) $r['document_type']) === 'chapter' && $this->belongs($chapter, $r)));
        $rejected = count($rows) - count($ok);

        if (!$ok) {
            return $this->entry('textbook', $label, 'missing', 'No chapter text is stored for this chapter.', $impact, [], $rejected);
        }
        if (count($ok) > 1) {
            return $this->entry('textbook', $label, 'conflicting', 'More than one chapter text is stored for this chapter.', 'Which one is the textbook must be settled before generation.', $this->docRefs($ok), $rejected);
        }
        $chars = mb_strlen(trim((string) $ok[0]['md_content']));
        $linked = (int) $ok[0]['id'] === (int) ($chapter['extraction_id'] ?? 0);
        $records = $this->docRefs($ok);
        if ($chars < $this->minTextChars) {
            return $this->entry('textbook', $label, 'incomplete', 'The stored chapter text is very short.', $impact, $records, $rejected);
        }
        if (!$linked) {
            return $this->entry('textbook', $label, 'conflicting', 'The stored chapter text is not the one the chapter record points at.', 'Which one is the textbook must be settled before generation.', $records, $rejected);
        }

        return $this->entry('textbook', $label, 'present', 'The chapter\'s extracted text is stored and readable.', $impact, $records, $rejected);
    }

    private function outcomes(array $chapter, array $raw): array
    {
        $label = 'Learning outcomes';
        $impact = 'Without them the presentation cannot state what students should achieve.';
        $rows = (array) $raw['outcomes'];
        // The outcomes table has no school column: an outcome counts only through a curriculum of this school,
        // standard and subject.
        $curriculumIds = array_map(fn ($c) => (int) $c['id'], array_filter((array) $raw['curricula'], fn ($c) => $this->belongs($chapter, $c, false)));
        $ok = array_values(array_filter($rows, fn ($r) => $this->belongs($chapter, $r) && in_array((int) $r['curriculum_id'], $curriculumIds, true)));
        $rejected = count($rows) - count($ok);

        if (!$ok) {
            return $this->entry('outcomes', $label, 'missing', 'No learning outcomes tied to this school\'s curriculum are stored for this chapter.', $impact, [], $rejected);
        }
        $byType = array_count_values(array_map(fn ($r) => (string) $r['type'], $ok));
        $summary = sprintf('%d learning outcome(s) and %d competenc%s.', $byType['learning_outcome'] ?? 0, $byType['competency'] ?? 0, ($byType['competency'] ?? 0) === 1 ? 'y' : 'ies');
        $records = [['type' => 'lms_learning_outcomes', 'ref' => count($ok) . ' rows', 'status' => 'stored', 'counts' => $byType]];
        $curricula = array_unique(array_map(fn ($r) => (int) $r['curriculum_id'], $ok));
        if (count($curricula) > 1) {
            return $this->entry('outcomes', $label, 'conflicting', 'The outcomes belong to more than one curriculum.', $impact, $records, $rejected);
        }
        if (empty($byType['learning_outcome'])) {
            return $this->entry('outcomes', $label, 'incomplete', 'There are competencies but no learning outcomes.', $impact, $records, $rejected);
        }

        return $this->entry('outcomes', $label, 'present', $summary, $impact, $records, $rejected);
    }

    /**
     * The learning outcomes and competencies that really belong to the chapter (through a curriculum of this school,
     * standard and subject), with their own wording.
     *
     * @return array<int,array{type:string,code:string,description:string}>
     */
    public function acceptedOutcomes(array $chapter, array $raw): array
    {
        $curriculumIds = array_map(fn ($c) => (int) $c['id'], array_filter((array) $raw['curricula'], fn ($c) => $this->belongs($chapter, $c, false)));
        $ok = array_filter((array) $raw['outcomes'], fn ($r) => $this->belongs($chapter, $r) && in_array((int) $r['curriculum_id'], $curriculumIds, true));
        usort($ok, fn ($a, $b) => strnatcmp((string) $a['code'], (string) $b['code']));

        return array_values(array_map(fn ($r) => ['type' => (string) $r['type'], 'code' => (string) $r['code'], 'description' => (string) ($r['description'] ?? '')], $ok));
    }

    /** The one semantic-intelligence row that really belongs to the chapter, or null (none, or more than one). */
    public function semanticRow(array $chapter, array $raw): ?array
    {
        $ok = array_values(array_filter((array) $raw['semantic'], fn ($r) => $this->belongs($chapter, $r)));

        return count($ok) === 1 ? $ok[0] : null;
    }

    /**
     * Content Intelligence IS Semantic Intelligence: one stored source (the chapter's semantic_intelligence row, tied to
     * the chapter by chapter, standard, subject and school), shown as one category. Nothing is reported missing because
     * a second table or service of that name does not exist. Coverage is counted from the data itself, and only fields
     * that really hold data are listed.
     */
    private function semantic(array $chapter, array $raw): array
    {
        $label = 'Content intelligence / Semantic intelligence';
        $impact = 'Misconceptions, real-world examples and suggested teaching strategies are only available for the concepts it covers; the others are written from the stored concept record alone.';
        $rows = (array) $raw['semantic'];
        $ok = array_values(array_filter($rows, fn ($r) => $this->belongs($chapter, $r)));
        $rejected = count($rows) - count($ok);

        if (!$ok) {
            return $this->entry('semantic_intelligence', $label, 'missing', 'No intelligence is stored for this chapter.', $impact, [], $rejected);
        }
        if (count($ok) > 1) {
            return $this->entry('semantic_intelligence', $label, 'conflicting', 'More than one intelligence record exists for this chapter.', $impact, $this->refs('semantic_intelligence', $ok), $rejected);
        }
        $row = $ok[0];
        $records = $this->refs('semantic_intelligence', $ok);
        $decoded = json_decode((string) ($row['full_intelegance_json'] ?? ''), true);
        if (!is_array($decoded)) {
            return $this->withLabel($this->entry('semantic_intelligence', $label, 'incomplete', 'The stored intelligence could not be read.', $impact, $records, $rejected), 'Available — unreadable');
        }
        $entries = array_values((array) ($decoded['concepts'] ?? $decoded['intelligence']['concepts'] ?? []));
        $covered = [];
        foreach ($entries as $e) {
            $k = $this->norm((string) ($e['concept']['concept_name'] ?? ''));
            if ($k !== '') {
                $covered[$k] = true;
            }
        }
        // How the chapter's concepts are covered: by their own intelligence (matched by name), through their topic only,
        // or from the textbook. The textbook fallback means a concept without intelligence is still explained.
        $cov = $this->coverage($chapter, $raw)['summary'];
        $total = $cov['total_concepts'];
        $matched = $cov['semantic_concept_level'];

        // Only fields that exist AND hold data are reported, with how many analysed concepts carry them.
        $fields = [];
        $without = [];
        foreach ([
            'teaching strategies' => 'pedagogy_recommendations', 'misconceptions' => 'misconceptions', 'real-world examples' => 'real_world_applications',
            'learning outcomes' => 'learning_outcomes', 'learning objectives' => 'learning_objectives', 'Bloom levels' => 'blooms',
            'prerequisites' => 'prerequisites', 'assessment blueprint' => 'assessment_blueprint',
        ] as $name => $key) {
            $withData = array_filter($entries, fn ($e) => $this->hasData($e[$key] ?? null));
            if ($withData) {
                $items = array_sum(array_map(fn ($e) => is_array($e[$key]) ? count($e[$key]) : 1, $withData));
                $fields[] = ['field' => $name, 'concepts' => count($withData), 'items' => $items];
            } else {
                $without[] = $name;
            }
        }

        $records[0]['concepts_in_intelligence'] = count($covered);
        $records[0]['concepts_matched'] = $matched;
        $records[0]['chapter_concepts'] = $total;
        $coverage = ['intelligence_concepts' => count($covered), 'matched' => $matched, 'chapter_concepts' => $total, 'fields' => $fields, 'without_data' => $without, 'summary' => $cov];

        $has = $fields ? ' Verified data: ' . implode('; ', array_map(fn ($f) => "{$f['field']} ({$f['concepts']} concepts, {$f['items']} item" . ($f['items'] === 1 ? '' : 's') . ')', $fields)) . '.' : '';
        $lacks = $without ? ' No data for: ' . implode(', ', $without) . '.' : '';

        if ($total > 0 && $matched < $total) {
            $entry = $this->entry('semantic_intelligence', $label, 'incomplete', sprintf(
                'Content intelligence and semantic intelligence are the same stored data, shown here as one source. It analyses %d concepts. %d of the chapter\'s %d concepts have their own intelligence (%d by exact name, %d by a contained name); %d more are covered only through their topic and %d have none. Every concept is still explained: %d from the stored concept record alone and %d from its intelligence%s.%s%s',
                count($covered), $matched, $total, $cov['semantic_exact'], $cov['semantic_contained'], $cov['semantic_topic_level_only'], $cov['no_semantic'],
                $cov['textbook_fallback_only'], $cov['both_sources'], $cov['unresolved'] > 0 ? "; {$cov['unresolved']} could not be tied to any source" : '', $has, $lacks
            ), $impact, $records, $rejected);

            return $this->withLabel($entry, 'Available — partial coverage', $coverage);
        }

        return $this->withLabel($this->entry('semantic_intelligence', $label, 'present', sprintf('Content intelligence and semantic intelligence are the same stored data, shown here as one source. It covers all %d of the chapter\'s concepts.%s%s', $total, $has, $lacks), $impact, $records, $rejected), 'Available', $coverage);
    }

    /** The chapter text of the one chapter extraction that really belongs to the chapter ('' when there is none). */
    public function textbookText(array $chapter, array $raw): string
    {
        $ok = array_values(array_filter((array) $raw['textbook'], fn ($r) => strtolower((string) $r['document_type']) === 'chapter' && $this->belongs($chapter, $r)));
        foreach ($ok as $r) {
            if ((int) $r['id'] === (int) ($chapter['extraction_id'] ?? 0)) {
                return (string) $r['md_content'];
            }
        }

        return (string) ($ok[0]['md_content'] ?? '');
    }

    /** @var array<int,array<string,mixed>> */
    private array $coverageMemo = [];

    /**
     * The chapter's concept coverage map (every concept, with the source of its explanation). Built once per chapter.
     *
     * @return array<string,mixed>
     */
    public function coverage(array $chapter, array $raw): array
    {
        return $this->coverageMemo[(int) $chapter['id']] ??= (new ConceptCoverage())->build(
            (array) ($raw['topics'] ?? []), (array) $raw['concepts'], $this->semanticRow($chapter, $raw), $this->textbookText($chapter, $raw)
        );
    }

    /** @param array<string,mixed> $entry @param array<string,mixed>|null $coverage */
    private function withLabel(array $entry, string $statusLabel, ?array $coverage = null): array
    {
        return $entry + ['status_label' => $statusLabel] + ($coverage !== null ? ['coverage' => $coverage] : []);
    }

    private function hasData(mixed $v): bool
    {
        return is_array($v) ? $v !== [] : (is_string($v) ? trim($v) !== '' : $v !== null && $v !== false);
    }
    private function chapterContent(array $chapter, array $raw): array
    {
        $label = 'Approved chapter content';
        $impact = 'No earlier lesson material can be reused or used as a baseline.';
        $cats = array_map('strtolower', (array) config('course_presentation_pilot.chapter_material_categories', []));
        $rows = array_values(array_filter((array) $raw['content'], fn ($r) => in_array(strtolower((string) $r['content_category']), $cats, true)));
        $ok = array_values(array_filter($rows, fn ($r) => $this->belongs($chapter, $r) && (int) ($r['show_hide'] ?? 1) !== 0));
        $rejected = count($rows) - count($ok);

        if (!$ok) {
            return $this->entry('chapter_content', $label, 'missing', 'No presentation, revision notes, remedial class or classroom activity is stored and visible for this chapter.', $impact, [], $rejected);
        }

        return $this->entry('chapter_content', $label, 'present', count($ok) . ' stored item(s) are visible for this chapter.', $impact, $this->refs('content_master', $ok, 'published'), $rejected, 'published');
    }

    private function studyDeckMaterial(array $raw): array
    {
        $label = 'Earlier generated study deck';
        $b = (array) $raw['bundle'];
        if (empty($b['found'])) {
            return $this->entry('study_deck_bundle', $label, 'missing', 'No earlier generated deck is waiting for review.', 'Nothing to reuse; a new deck would be generated.');
        }
        $made = !empty($b['teaching_method']) ? 'for a teaching method' : 'without a teaching method';
        if ($b['ok'] === true) {
            return $this->entry('study_deck_bundle', $label, 'present', "An earlier generated deck passed its own validation. It was made {$made}, is unpublished and was not checked again here.", 'It is never reused as it stands: it would need re-validation and a teacher\'s review, and it matches the chosen teaching method only if it was made for it.', [['type' => 'study_deck_bundle', 'ref' => 'on disk', 'status' => 'unpublished']], 0, 'unreviewed');
        }
        $errors = array_map([GenerationRun::class, 'clean'], (array) ($b['errors'] ?? []));
        $why = $errors ? ' (' . count($errors) . ' problem(s): ' . implode(' ', array_slice($errors, 0, 2)) . ')' : ' (or its report cannot be read)';
        $repair = count($errors) === 1 ? ' With one problem it may be repairable by re-checking it without a model call once that is corrected, but it is not reused until it passes.' : '';

        return $this->entry('study_deck_bundle', $label, 'incomplete', "An earlier generated deck exists but did not pass validation{$why}. It was made {$made}.{$repair}", 'It is not reused: a deck that has not passed validation is never offered as reusable.', [['type' => 'study_deck_bundle', 'ref' => 'on disk', 'status' => 'failed_validation']], 0, 'unreviewed');
    }
    /** Visible Classroom Presentation rows for the chapter (for "an existing Course Presentation"). */
    private function existingResources(array $chapter, array $raw): array
    {
        $cats = array_map('strtolower', (array) config('course_presentation_pilot.presentation_categories', []));
        $out = [];
        foreach ((array) $raw['content'] as $r) {
            if (!$this->belongs($chapter, $r) || (int) ($r['show_hide'] ?? 1) === 0) {
                continue;
            }
            // Each stored item belongs to ONE format: a story lesson (its own category and file prefix) is not a Classroom Presentation.
            $category = strtolower((string) $r['content_category']);
            $story = str_starts_with((string) ($r['filename'] ?? ''), \App\Services\StudyDeck\Story\StoryTemplate::FILE_PREFIX);
            // `method` is the teaching method the item was stored for (null: stored without one). A stored item answers only
            // for its own format AND method; the poster and decks stored before methods were recorded answer for none.
            $method = \App\Services\StudyDeck\StudyDeckPublisher::variantOf((string) ($r['filename'] ?? ''));
            if ($story && $category === strtolower(\App\Services\StudyDeck\Story\StoryTemplate::CATEGORY)) {
                $out[] = ['type' => 'Story lesson', 'format' => 'branching_scenario', 'method' => $method, 'title' => (string) $r['title'], 'ref' => (int) $r['id'], 'status' => 'published'];
            } elseif (!$story && in_array($category, $cats, true)) {
                $out[] = ['type' => 'Classroom presentation', 'format' => 'interactive_slides', 'method' => $method, 'title' => (string) $r['title'], 'ref' => (int) $r['id'], 'status' => 'published'];
            }
        }

        return $out;
    }

    /** Things that exist for the chapter but are deliberately not attached. */
    private function notUsed(array $chapter, array $raw): array
    {
        $rows = array_values(array_filter((array) $raw['prayogshala'], fn ($r) => (int) $r['sub_institute_id'] === (int) $chapter['sub_institute_id']));
        if (!$rows) {
            return [];
        }
        $by = array_count_values(array_map(fn ($r) => (string) $r['status'], $rows));
        $parts = [];
        foreach ($by as $status => $n) {
            $parts[] = "$n $status";
        }

        return [[
            'key' => 'prayogshala',
            'label' => 'Prayogshala activities',
            'count' => count($rows),
            'detail' => implode(', ', $parts),
            'reason' => 'They are a separate resource, not Course Presentation content, and unpublished activities are never attached as teaching sources.',
        ]];
    }

    private function refs(string $table, array $rows, string $status = 'stored'): array
    {
        return array_map(fn ($r) => ['type' => $table, 'ref' => (int) $r['id'], 'status' => $status], $rows);
    }

    private function docRefs(array $rows): array
    {
        return array_map(fn ($r) => ['type' => 'document_extractions', 'ref' => (int) $r['id'], 'status' => 'stored', 'characters' => mb_strlen(trim((string) $r['md_content']))], $rows);
    }

    private function norm(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
    }
}
