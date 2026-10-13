<?php

namespace App\Services\StudyDeck;

/**
 * Instructional-pattern catalogue. PATTERN REFERENCE ONLY.
 *
 * This class never reads an H5P table, library or content row. It holds a small,
 * static description of how each H5P interaction TEACHES, so the planner can
 * decide HOW to teach a concept. WHAT is taught comes from the LMS data and the
 * chapter text. The chosen pattern is recorded as slide metadata
 * ({"h5p_pattern": ..., "reason": ...}) and the finished deck has no runtime
 * dependency on H5P.
 */
class H5pPatternSelector
{
    /**
     * id => [label, move: what the pattern does for a learner, when: the concept
     * signal that justifies it]. Order is the order shown to the planner.
     */
    public const PATTERNS = [
        'course_presentation' => [
            'label' => 'Course presentation',
            'move' => 'Progress through a sequence one idea per slide, each ending with a check.',
            'when' => 'concepts form a sequence where each builds on the last',
        ],
        'branching' => [
            'label' => 'Branching scenario',
            'move' => 'Pose a decision, let the learner commit, then reveal the consequence.',
            'when' => 'one concept depends on another, or a choice shows why an idea matters',
        ],
        'matching' => [
            'label' => 'Matching',
            'move' => 'Pair two related ideas, terms or examples.',
            'when' => 'two or more concepts must be compared or paired',
        ],
        'drag_drop' => [
            'label' => 'Classification',
            'move' => 'Sort items into categories.',
            'when' => 'learners must classify examples into kinds',
        ],
        'flashcards' => [
            'label' => 'Flashcards',
            'move' => 'Term on one side, meaning on the other, for recall.',
            'when' => 'precise terms, symbols or definitions must be recalled',
        ],
        'image_hotspots' => [
            'label' => 'Image hotspots',
            'move' => 'Explore the meaningful parts of one image.',
            'when' => 'a visual has several meaningful parts',
        ],
        'true_false' => [
            'label' => 'True or false',
            'move' => 'Judge a statement, exposing a common wrong idea.',
            'when' => 'a documented misconception needs to be confronted',
        ],
        'fill_blanks' => [
            'label' => 'Fill in the blanks',
            'move' => 'Complete a sentence to reinforce key wording.',
            'when' => 'exact terminology must be reinforced',
        ],
        'question_set' => [
            'label' => 'Question set',
            'move' => 'A short run of questions rising in difficulty.',
            'when' => 'a concept needs repeated, progressive practice',
        ],
    ];

    /** @return array<int,string> */
    public function ids(): array
    {
        return array_keys(self::PATTERNS);
    }

    public function isValid(?string $id): bool
    {
        return $id !== null && isset(self::PATTERNS[$id]);
    }

    /** The catalogue as the planner sees it. */
    public function catalogue(): array
    {
        $out = [];
        foreach (self::PATTERNS as $id => $p) {
            $out[] = ['id' => $id] + $p;
        }

        return $out;
    }

    /**
     * Cheap, subject-neutral signals suggesting which patterns suit a concept.
     * Advisory only: the planner decides, and may choose none.
     *
     * @param array<string,mixed> $concept from ConceptContextBuilder::assemble()
     * @param int $inChapterPrereqs number of in-chapter prerequisites of this concept
     * @return array<int,string>
     */
    public function candidatesFor(array $concept, int $inChapterPrereqs = 0): array
    {
        $text = mb_strtolower(implode(' ', array_merge(
            (array) ($concept['objectives'] ?? []),
            [(string) ($concept['name'] ?? '')]
        )));
        $out = [];

        if ($inChapterPrereqs > 0 || array_filter((array) ($concept['relationships'] ?? []), fn ($r) => ($r['target_id'] ?? null) !== null)) {
            $out[] = 'branching';
        }
        if (!empty($concept['misconceptions'])) {
            $out[] = 'true_false';
        }
        if (preg_match('/\b(differentiate|distinguish|compare|contrast|difference)\b/', $text)) {
            $out[] = 'matching';
        }
        if (preg_match('/\b(classify|categor|sort|types? of|kinds? of)\b/', $text)) {
            $out[] = 'drag_drop';
        }
        if (preg_match('/\b(define|state|recall|symbol|term|unit|precise)\b/', $text)) {
            $out[] = 'flashcards';
            $out[] = 'fill_blanks';
        }
        if (preg_match('/\b(structure|diagram|label|parts?|model|map)\b/', $text)) {
            $out[] = 'image_hotspots';
        }

        return array_values(array_unique($out));
    }
}
