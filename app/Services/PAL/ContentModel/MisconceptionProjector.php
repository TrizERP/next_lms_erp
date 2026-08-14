<?php

namespace App\Services\PAL\ContentModel;

/**
 * Type 3 — the Misconception Library (spec §4).
 *
 * The extractor emits, per concept, a list of
 *   {misconception, statement, root_cause, correction, concept_name}
 * and — where an assessment rubric was produced — per-distractor
 * `misconception_tested` strings that name the same errors. Both are projected
 * here into the spec's :Misconception node plus its CORRECTS_WITH correctives.
 *
 * The one rule this file exists to enforce is CONTENT LAW C6: a misconception
 * with no corrective may not be served. Detecting a learner's error and then
 * showing them nothing is worse than not detecting it, so an entry whose
 * `correction` text is empty is projected with `c6_ok = false` and is reported
 * rather than quietly delivered.
 *
 * Corrective format follows spec §4.1's core principle: the corrective must use
 * a DIFFERENT modality from the explanation the learner already failed on. The
 * variant ladder in config supplies those modalities, so a concept's corrective
 * set walks visual → story → simulation rather than re-showing text.
 */
class MisconceptionProjector
{
    public function __construct(protected SemanticSourceRepository $source) {}

    /**
     * @return array{
     *   label:string, total:int, servable:int, c6_violations:array, entries:array
     * }
     */
    public function project(int $semanticId, array $concept, array $header): array
    {
        $prevalence = $this->prevalenceFromRubrics($concept);
        $formats = $this->correctiveFormats();

        $entries = [];
        $seen = [];
        $index = 0;

        foreach ($concept['misconceptions'] ?? [] as $row) {
            $title = trim((string) ($row['misconception'] ?? ''));
            $statement = trim((string) ($row['statement'] ?? ''));
            if ($title === '' && $statement === '') {
                continue;
            }
            if ($title === '') {
                $title = $this->truncate($statement, 90);
            }

            $tag = $this->tagFor($title);
            if (isset($seen[$tag])) {
                continue;
            }
            $seen[$tag] = true;

            $correction = trim((string) ($row['correction'] ?? ''));
            $rootCause = trim((string) ($row['root_cause'] ?? ''));

            // Prevalence is EVIDENCE-BASED: the share of this concept's rubric
            // distractors that test this specific misconception. Null when the
            // concept has no rubric items — an invented rate would drive the
            // priority ordering of real remediation.
            $hits = $prevalence['by_tag'][$tag] ?? 0;
            $rate = $prevalence['total_distractors'] > 0
                ? round($hits / $prevalence['total_distractors'], 3)
                : null;

            $correctives = $this->correctivesFor($semanticId, $concept, $tag, $correction, $rootCause, $formats, $index);

            $entries[] = [
                'node_key' => $this->nodeKey('misconception', $semanticId, $concept['slug'], $tag),
                'content_type' => 'misconception',
                'tag' => $tag,
                'title' => $title,
                'statement_source' => $statement ?: $title,
                'concept_name' => $concept['name'],
                'concept_slug' => $concept['slug'],
                'subject' => $header['subject_name'] ?? null,
                'grade' => $header['standard'] ?? null,

                // The spec's :Misconception fields.
                'description' => $statement ?: $title,
                'error_pattern' => $statement ?: null,
                'root_cause' => $rootCause ?: null,
                'corrective_action' => $correction ?: null,
                'typical_wrong_answers' => $prevalence['wrong_answers'][$tag] ?? [],
                'prevalence_rate' => $rate,
                'detected_in_items' => $hits,
                'severity' => $this->severity($rate, $hits, $correction !== ''),
                'priority_level' => $this->priority($rate, $hits),
                'teacher_confirmed' => false,
                'corrective_format' => $correctives[0]['format'] ?? null,
                'quality_status' => 'draft',
                'tagged_by' => 'derived',

                // CONTENT LAW C6.
                'c6_ok' => $correctives !== [],
                'c6_reason' => $correctives === []
                    ? 'The extraction carries no correction text for this misconception, so nothing can be served when it is detected.'
                    : null,

                'correctives' => $correctives,
                'detection' => [
                    'confirm_after_occurrences' => (int) config('pal_content.misconception.confirm_after_occurrences', 2),
                    'teacher_alert_after_occurrences' => (int) config('pal_content.misconception.teacher_alert_after_occurrences', 3),
                    'majority_threshold' => (float) config('pal_content.misconception.majority_threshold', 0.40),
                ],
            ];

            $index++;
        }

        // Misconceptions named only by a rubric distractor, with no library
        // entry of their own. Real gaps in the library, surfaced not swallowed.
        foreach ($prevalence['by_tag'] as $tag => $hits) {
            if (isset($seen[$tag])) {
                continue;
            }
            $seen[$tag] = true;
            $title = $prevalence['titles'][$tag] ?? $tag;

            $entries[] = [
                'node_key' => $this->nodeKey('misconception', $semanticId, $concept['slug'], $tag),
                'content_type' => 'misconception',
                'tag' => $tag,
                'title' => $title,
                'statement_source' => $title,
                'concept_name' => $concept['name'],
                'concept_slug' => $concept['slug'],
                'subject' => $header['subject_name'] ?? null,
                'grade' => $header['standard'] ?? null,
                'description' => $title,
                'error_pattern' => null,
                'root_cause' => null,
                'corrective_action' => null,
                'typical_wrong_answers' => $prevalence['wrong_answers'][$tag] ?? [],
                'prevalence_rate' => $prevalence['total_distractors'] > 0
                    ? round($hits / $prevalence['total_distractors'], 3)
                    : null,
                'detected_in_items' => $hits,
                'severity' => $this->severity(null, $hits, false),
                'priority_level' => $this->priority(null, $hits),
                'teacher_confirmed' => false,
                'corrective_format' => null,
                'quality_status' => 'draft',
                'tagged_by' => 'derived',
                'c6_ok' => false,
                'c6_reason' => 'Named by an assessment distractor but absent from the misconception list — it has no description, no root cause and no corrective.',
                'correctives' => [],
                'origin' => 'assessment_distractor',
                'detection' => [
                    'confirm_after_occurrences' => (int) config('pal_content.misconception.confirm_after_occurrences', 2),
                    'teacher_alert_after_occurrences' => (int) config('pal_content.misconception.teacher_alert_after_occurrences', 3),
                    'majority_threshold' => (float) config('pal_content.misconception.majority_threshold', 0.40),
                ],
            ];
        }

        // Highest-priority first — that is the order a content lead should work in.
        usort($entries, fn ($a, $b) => [$a['priority_level'], $a['tag']] <=> [$b['priority_level'], $b['tag']]);

        $violations = array_values(array_map(
            fn ($e) => ['tag' => $e['tag'], 'reason' => $e['c6_reason']],
            array_filter($entries, fn ($e) => ! $e['c6_ok'])
        ));

        return [
            'label' => config('pal_content.content_types.corrective.label', 'Misconception Library'),
            'total' => count($entries),
            'servable' => count(array_filter($entries, fn ($e) => $e['c6_ok'])),
            // Spec §4.1: minimum viable is 3 misconceptions per concept.
            'required_minimum' => 3,
            'meets_minimum' => count($entries) >= 3,
            'c6_pass' => $violations === [],
            'c6_violations' => $violations,
            'corrective_formats' => config('pal_content.corrective_formats', []),
            'entries' => $entries,
        ];
    }

    /**
     * Correctives for one misconception, in the modality order the variant
     * ladder defines. Each carries the extractor's correction text targeted at
     * that modality; a modality with nothing to say is simply not produced.
     */
    protected function correctivesFor(
        int $semanticId,
        array $concept,
        string $tag,
        string $correction,
        string $rootCause,
        array $formats,
        int $index
    ): array {
        if ($correction === '') {
            return [];
        }

        $correctives = [];
        $priority = 1;

        foreach ($formats as $format => $spec) {
            $body = $this->correctiveBody($format, $correction, $rootCause, $concept);
            if ($body === null) {
                continue;
            }

            $correctives[] = [
                'node_key' => $this->nodeKey('corrective', $semanticId, $concept['slug'], $tag . '.' . $priority),
                'content_type' => 'corrective',
                'misconception_tag' => $tag,
                'title' => $spec['title'],
                'body' => $body,
                'format' => $format,
                'h5p_type' => $spec['h5p_type'],
                'language' => config('pal_content.default_language', 'en'),
                'priority_level' => $priority,
                'quality_status' => 'draft',
                'tagged_by' => 'derived',
                // Spec §4.1: never re-show the explanation that already failed.
                'different_modality_required' => true,
            ];
            $priority++;
        }

        return $correctives;
    }

    /**
     * What each corrective modality can say, given only what was extracted.
     * Returns null when that modality has no material — so the list shortens
     * instead of repeating the same paragraph under three headings.
     */
    protected function correctiveBody(string $format, string $correction, string $rootCause, array $concept): ?string
    {
        switch ($format) {
            case 'visual':
                // The primary corrective: the correction itself, delivered as a
                // labelled visual rather than re-read prose.
                $parts = [$correction];
                if ($rootCause !== '') {
                    $parts[] = 'Why the learner believes otherwise: ' . $rootCause;
                }

                return implode("\n\n", $parts);

            case 'story':
                $applications = [];
                foreach (array_slice($concept['real_world_applications'] ?? [], 0, 3) as $row) {
                    $example = trim((string) ($row['example'] ?? ''));
                    if ($example !== '') {
                        $applications[] = $example;
                    }
                }
                if ($applications === []) {
                    return null;
                }

                return $correction . "\n\nAnchor it in a familiar situation:\n• " . implode("\n• ", $applications);

            case 'simulation':
                $practical = [];
                $stems = config('pal_content_model.practical_pedagogy_stems', []);
                foreach ($concept['pedagogy_recommendations'] ?? [] as $row) {
                    $strategy = trim((string) ($row['strategy'] ?? ''));
                    $why = trim((string) ($row['why_effective'] ?? ''));
                    $haystack = mb_strtolower($strategy . ' ' . $why);
                    foreach ($stems as $stem) {
                        if ($stem !== '' && str_contains($haystack, $stem)) {
                            $practical[] = $strategy . ($why !== '' ? ' — ' . $why : '');
                            break;
                        }
                    }
                }
                if ($practical === []) {
                    return null;
                }

                return $correction . "\n\nLet the learner test it:\n• " . implode("\n• ", array_slice(array_unique($practical), 0, 3));

            default:
                return null;
        }
    }

    /** Modality → title + H5P recommendation, in ladder order. */
    protected function correctiveFormats(): array
    {
        return [
            'visual' => ['title' => 'Visual correction', 'h5p_type' => 'image_hotspot'],
            'story' => ['title' => 'Story / analogy correction', 'h5p_type' => 'branching_scenario'],
            'simulation' => ['title' => 'Hands-on / simulation correction', 'h5p_type' => 'documentation_tool'],
        ];
    }

    /**
     * How often each misconception is actually tested by this concept's rubric
     * distractors, plus the wrong answers that signal it. This is the only
     * prevalence signal available before the content has been served.
     */
    protected function prevalenceFromRubrics(array $concept): array
    {
        $byTag = [];
        $titles = [];
        $wrongAnswers = [];
        $totalDistractors = 0;

        foreach ($concept['assessment_rubrics'] ?? [] as $item) {
            foreach ($item['answer_key'] ?? [] as $option) {
                if (! is_array($option) || ! empty($option['is_correct'])) {
                    continue;
                }
                $totalDistractors++;

                $tested = trim((string) ($option['misconception_tested'] ?? ''));
                if ($tested === '') {
                    continue;
                }
                $tag = $this->tagFor($tested);
                $byTag[$tag] = ($byTag[$tag] ?? 0) + 1;
                $titles[$tag] = $titles[$tag] ?? $tested;

                $answer = trim((string) ($option['option_text'] ?? ''));
                if ($answer !== '') {
                    $wrongAnswers[$tag] = $wrongAnswers[$tag] ?? [];
                    if (! in_array($answer, $wrongAnswers[$tag], true)) {
                        $wrongAnswers[$tag][] = $answer;
                    }
                }
            }

            // Free-response items name their errors in prose instead.
            foreach ($item['common_errors'] ?? [] as $error) {
                if (! is_string($error) || trim($error) === '') {
                    continue;
                }
                $totalDistractors++;
            }
        }

        return [
            'by_tag' => $byTag,
            'titles' => $titles,
            'wrong_answers' => $wrongAnswers,
            'total_distractors' => $totalDistractors,
        ];
    }

    /**
     * Severity blends how often the error is tested with whether anything can
     * be done about it. An unfixable misconception is high severity however
     * rare it is, because detection with no corrective is a dead end.
     */
    protected function severity(?float $rate, int $hits, bool $hasCorrective): string
    {
        if (! $hasCorrective) {
            return 'critical';
        }
        if ($rate !== null && $rate >= 0.30) {
            return 'high';
        }
        if ($hits >= 2 || ($rate !== null && $rate >= 0.15)) {
            return 'medium';
        }

        return 'low';
    }

    /** 1 (work on this first) … 5, the range config/pal_content.php registers. */
    protected function priority(?float $rate, int $hits): int
    {
        $min = (int) config('pal_content.misconception.priority_range.min', 1);
        $max = (int) config('pal_content.misconception.priority_range.max', 5);

        if ($rate !== null && $rate >= 0.30) {
            return $min;
        }
        if ($hits >= 3) {
            return min($max, $min + 1);
        }
        if ($hits >= 1) {
            return min($max, $min + 2);
        }

        return min($max, $min + 3);
    }

    /**
     * The permanent, lower_snake_case tag. It becomes a foreign key in learner
     * error history, so it is derived from the misconception's own wording and
     * never renumbered — renaming one would orphan every learner record that
     * references it.
     */
    public function tagFor(string $title): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($title))) ?? '';
        $slug = trim($slug, '_');

        if ($slug === '' || ! preg_match('/^[a-z]/', $slug)) {
            $slug = 'mc_' . $slug;
            $slug = trim($slug, '_');
        }

        // The library column is 96 chars and the validator caps tags there too.
        if (strlen($slug) > 96) {
            $slug = substr($slug, 0, 89) . '_' . substr(md5($title), 0, 6);
        }

        return $slug;
    }

    protected function nodeKey(string $type, int $semanticId, string $conceptSlug, string $discriminator): string
    {
        $prefix = config('pal_content_model.node_prefixes.' . $type, strtoupper(substr($type, 0, 2)));

        return "{$prefix}.{$semanticId}.{$conceptSlug}.{$discriminator}";
    }

    protected function truncate(string $value, int $length): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return mb_strlen($value) <= $length ? $value : mb_substr($value, 0, $length - 1) . '…';
    }
}
