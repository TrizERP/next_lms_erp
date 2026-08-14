<?php

namespace App\Services\PAL\ContentModel;

use App\Services\PAL\Content\PalVocabulary;

/**
 * Fills the parts of the Content Model the extraction cannot answer.
 *
 * Four jobs, each corresponding to a row of the PAL V4 comparison sheet that
 * the database alone cannot satisfy:
 *
 *   cultural_context   the 6 Indian contexts (spec §2.3). A cheap lexicon pass
 *                      runs first; the LLM is consulted only when the lexicon
 *                      is inconclusive, so most nodes cost nothing.
 *   framework_tags     RIASEC / Gardner / NGSS / CASEL / NCDG / HPC lens /
 *                      career cluster (spec §5.1). Not present in the
 *                      extraction in any form, so LLM-only.
 *   translation        the 9 language variants (spec §2.2).
 *   variant_draft      authoring assistance: drafts the material for a Type 1
 *                      variant slot that has no extracted backing, on request.
 *
 * Two invariants hold for everything here:
 *   - the model chooses from CLOSED vocabularies (config/pal_content.php) and
 *     anything outside them is discarded rather than stored, so one hallucinated
 *     tag cannot introduce a 400th spelling of "apply";
 *   - output is always tagged_by = 'ai', quality_status = 'draft'. Approval is
 *     a human action (CONTENT LAW C5).
 */
class ContentModelEnrichmentService
{
    public function __construct(protected ContentModelLlmClient $llm) {}

    public function available(): bool
    {
        return $this->llm->enabled();
    }

    public function unavailableReason(): ?string
    {
        return $this->llm->unavailableReason();
    }

    // ══════════════════════════════════════════════════════════════════════
    // Indian cultural context (spec §2.3)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Lexicon-first classification. Returns the context plus how it was decided,
     * so the UI can show "matched on 'farm', 'harvest'" rather than an opaque tag.
     *
     * @return array{context:?string, method:string, score:int, matched:array, needs_llm:bool, candidates:array}
     */
    public function classifyCulturalContext(string $text): array
    {
        $lexicon = config('pal_content_model.cultural_lexicon', []);
        $settings = config('pal_content_model.cultural_classifier', []);
        $haystack = mb_strtolower($text);

        if (trim($haystack) === '') {
            return [
                'context' => $settings['unmatched'] ?? 'none',
                'method' => 'empty',
                'score' => 0,
                'matched' => [],
                'needs_llm' => false,
                'candidates' => [],
            ];
        }

        $scores = [];
        $matched = [];
        foreach ($lexicon as $context => $terms) {
            $score = 0;
            $hits = [];
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($haystack, $term)) {
                    $score++;
                    $hits[] = $term;
                }
            }
            if ($score > 0) {
                $scores[$context] = $score;
                $matched[$context] = $hits;
            }
        }

        if ($scores === []) {
            return [
                'context' => null,
                'method' => 'lexicon_miss',
                'score' => 0,
                'matched' => [],
                'needs_llm' => true,
                'candidates' => array_keys($lexicon),
            ];
        }

        arsort($scores);
        $top = array_key_first($scores);
        $topScore = $scores[$top];
        $second = array_slice($scores, 1, 1, true);
        $secondScore = $second === [] ? 0 : reset($second);

        $minScore = (int) ($settings['min_score'] ?? 2);
        $tieMargin = (int) ($settings['tie_margin'] ?? 1);

        // Two contexts within the tie margin means the material genuinely spans
        // both — "mixed" is the honest answer, not a coin toss.
        if ($secondScore > 0 && ($topScore - $secondScore) <= $tieMargin && $topScore >= $minScore) {
            return [
                'context' => $settings['fallback'] ?? 'mixed',
                'method' => 'lexicon_tie',
                'score' => $topScore,
                'matched' => $matched,
                'needs_llm' => false,
                'candidates' => array_keys($scores),
            ];
        }

        if ($topScore >= $minScore) {
            return [
                'context' => $top,
                'method' => 'lexicon',
                'score' => $topScore,
                'matched' => [$top => $matched[$top]],
                'needs_llm' => false,
                'candidates' => array_keys($scores),
            ];
        }

        // A single weak hit is not enough to route a learner on.
        return [
            'context' => null,
            'method' => 'lexicon_weak',
            'score' => $topScore,
            'matched' => $matched,
            'needs_llm' => true,
            'candidates' => array_keys($scores),
        ];
    }

    /**
     * Ask the model to pick a cultural context when the lexicon could not.
     *
     * @return array{ok:bool, data?:array, error?:string, cached?:bool}
     */
    public function llmCulturalContext(string $nodeKey, array $context, int $tenant, ?int $userId): array
    {
        $allowed = config('pal_content.cultural_contexts', []);

        $input = [
            'task' => 'cultural_context',
            'allowed' => $allowed,
            'concept' => $context['concept_name'] ?? null,
            'subject' => $context['subject'] ?? null,
            'grade' => $context['grade'] ?? null,
            'text' => mb_substr((string) ($context['text'] ?? ''), 0, 4000),
        ];

        return $this->call($nodeKey, 'cultural_context', null, $input, $tenant, $userId, function (array $data) use ($allowed) {
            $context = $data['cultural_context'] ?? null;
            if (! is_string($context) || ! in_array($context, $allowed, true)) {
                return null;
            }

            return [
                'cultural_context' => $context,
                'reason' => is_string($data['reason'] ?? null) ? $data['reason'] : null,
                'suggested_example' => is_string($data['suggested_example'] ?? null) ? $data['suggested_example'] : null,
                'confidence' => $this->unit($data['confidence'] ?? null),
            ];
        }, $this->culturalSystemPrompt($allowed), $this->culturalUserPrompt($input));
    }

    protected function culturalSystemPrompt(array $allowed): string
    {
        return "You classify Indian school learning material into one of a fixed set of everyday-life contexts, "
            . "so an adaptive tutor can pick examples a student will recognise.\n\n"
            . "Allowed values, and NOTHING else: " . implode(', ', $allowed) . ".\n"
            . "Use 'mixed' when the material genuinely spans several contexts, and 'none' when it is abstract "
            . "and no everyday context applies. Never invent a new value.\n\n"
            . 'Reply with JSON only: {"cultural_context": "...", "reason": "one short sentence", '
            . '"suggested_example": "one concrete Indian daily-life example that would suit this concept", '
            . '"confidence": 0.0-1.0}';
    }

    protected function culturalUserPrompt(array $input): string
    {
        return "Subject: {$input['subject']}\nGrade: {$input['grade']}\nConcept: {$input['concept']}\n\n"
            . "Material:\n{$input['text']}";
    }

    // ══════════════════════════════════════════════════════════════════════
    // Framework tags (spec §5.1 standards block)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Propose the standards-framework fields the extraction does not carry.
     * Every returned value is validated against the closed vocabulary before it
     * is cached; anything unrecognised is dropped, and the field stays missing.
     */
    public function llmFrameworkTags(string $nodeKey, array $context, array $fields, int $tenant, ?int $userId): array
    {
        $vocab = $this->frameworkVocabulary();
        $fields = array_values(array_intersect($fields, array_keys($vocab)));

        if ($fields === []) {
            return ['ok' => false, 'error' => 'No enrichable framework fields were requested.'];
        }

        $input = [
            'task' => 'framework_tags',
            'fields' => $fields,
            'concept' => $context['concept_name'] ?? null,
            'subject' => $context['subject'] ?? null,
            'grade' => $context['grade'] ?? null,
            'bloom_level' => $context['bloom_level'] ?? null,
            'skills' => array_slice($context['skills'] ?? [], 0, 12),
            'text' => mb_substr((string) ($context['text'] ?? ''), 0, 4000),
        ];

        $allowedForRequested = array_intersect_key($vocab, array_flip($fields));

        return $this->call(
            $nodeKey,
            'framework_tags',
            implode(',', $fields),
            $input,
            $tenant,
            $userId,
            function (array $data) use ($allowedForRequested) {
                $tags = is_array($data['tags'] ?? null) ? $data['tags'] : $data;

                $clean = [];
                $rejected = [];
                foreach ($allowedForRequested as $field => $allowed) {
                    $value = $tags[$field] ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    // gardner_intelligence is a list on content nodes.
                    if (is_array($value)) {
                        $kept = array_values(array_filter($value, fn ($v) => in_array($v, $allowed, true)));
                        $dropped = array_values(array_diff($value, $kept));
                        if ($kept !== []) {
                            $clean[$field] = $kept;
                        }
                        if ($dropped !== []) {
                            $rejected[$field] = $dropped;
                        }
                        continue;
                    }
                    if (in_array($value, $allowed, true)) {
                        $clean[$field] = $value;
                    } else {
                        $rejected[$field] = $value;
                    }
                }

                if ($clean === []) {
                    return null;
                }

                return [
                    'tags' => $clean,
                    // Surfaced, not hidden: a reviewer should see the model
                    // reaching outside the vocabulary if it does.
                    'rejected' => $rejected,
                    'rationale' => is_array($data['rationale'] ?? null) ? $data['rationale'] : [],
                    'confidence' => $this->unit($data['confidence'] ?? null),
                ];
            },
            $this->frameworkSystemPrompt($allowedForRequested),
            $this->frameworkUserPrompt($input)
        );
    }

    /** field → the closed set it must be chosen from. */
    public function frameworkVocabulary(): array
    {
        return [
            'riasec_signal' => array_keys(config('pal_content.riasec_signals', [])),
            'gardner_intelligence' => config('pal_content.gardner_intelligences', []),
            'ngss_practice' => config('pal_content.ngss_practices', []),
            'casel_domain' => config('pal_content.casel_domains', []),
            'ncdg_goal' => config('pal_content.ncdg_goals', []),
            'career_cluster_signal' => config('pal_content.career_clusters', []),
            'aptitude_domain' => config('pal_content.aptitude_domains', []),
            'p21_skill' => config('pal_content.p21_skills', []),
            'soft_skill_signal' => config('pal_content.soft_skill_signals', []),
            'hpc_lens_primary' => config('pal_content.hpc_lenses', []),
            'gender_representation' => config('pal_content.gender_representation', []),
            'nep_vocational_stream' => config('pal_content.nep_vocational_streams', []),
        ];
    }

    protected function frameworkSystemPrompt(array $allowed): string
    {
        $lines = [];
        foreach ($allowed as $field => $values) {
            $lines[] = "  {$field}: " . implode(' | ', $values);
        }

        return "You tag Indian K-12 learning content against standards frameworks for an adaptive learning "
            . "platform (NEP 2020 / HPC, NGSS, CASEL, NCDG, RIASEC, Gardner).\n\n"
            . "Choose ONLY from these closed sets. A value outside them is discarded:\n"
            . implode("\n", $lines) . "\n\n"
            . "Omit any field you cannot justify from the material — an absent tag is far better than a wrong "
            . "one, because a wrong tag routes a student to the wrong content. gardner_intelligence may be an "
            . "array; every other field is a single value.\n\n"
            . 'Reply with JSON only: {"tags": {...}, "rationale": {"field": "one short sentence"}, "confidence": 0.0-1.0}';
    }

    protected function frameworkUserPrompt(array $input): string
    {
        $skills = $input['skills'] === [] ? '(none extracted)' : implode(', ', $input['skills']);

        return "Subject: {$input['subject']}\nGrade: {$input['grade']}\nConcept: {$input['concept']}\n"
            . "Bloom level: {$input['bloom_level']}\nSkills: {$skills}\n"
            . 'Fields requested: ' . implode(', ', $input['fields']) . "\n\n"
            . "Material:\n{$input['text']}";
    }

    // ══════════════════════════════════════════════════════════════════════
    // Language variants (spec §2.2 — 9 Indian languages)
    // ══════════════════════════════════════════════════════════════════════

    public function llmTranslate(string $nodeKey, array $context, string $targetLanguage, int $tenant, ?int $userId): array
    {
        $languages = config('pal_content.languages', []);
        if (! in_array($targetLanguage, $languages, true)) {
            return ['ok' => false, 'error' => "'{$targetLanguage}' is not one of the registered languages: " . implode(', ', $languages)];
        }

        $input = [
            'task' => 'translation',
            'target' => $targetLanguage,
            'concept' => $context['concept_name'] ?? null,
            'subject' => $context['subject'] ?? null,
            'grade' => $context['grade'] ?? null,
            'title' => (string) ($context['title'] ?? ''),
            'body' => mb_substr((string) ($context['body'] ?? ''), 0, 6000),
        ];

        return $this->call(
            $nodeKey,
            'translation',
            $targetLanguage,
            $input,
            $tenant,
            $userId,
            function (array $data) use ($targetLanguage) {
                $body = $data['body'] ?? null;
                if (! is_string($body) || trim($body) === '') {
                    return null;
                }

                return [
                    'language' => $targetLanguage,
                    'title' => is_string($data['title'] ?? null) ? $data['title'] : null,
                    'body' => $body,
                    'glossary' => is_array($data['glossary'] ?? null) ? $data['glossary'] : [],
                    'notes' => is_string($data['notes'] ?? null) ? $data['notes'] : null,
                ];
            },
            $this->translationSystemPrompt($targetLanguage),
            "Subject: {$input['subject']}\nGrade: {$input['grade']}\nConcept: {$input['concept']}\n\n"
                . "Title:\n{$input['title']}\n\nBody:\n{$input['body']}"
        );
    }

    protected function translationSystemPrompt(string $target): string
    {
        $names = [
            'en' => 'English', 'hi' => 'Hindi', 'gu' => 'Gujarati', 'ta' => 'Tamil',
            'te' => 'Telugu', 'mr' => 'Marathi', 'bn' => 'Bengali', 'kn' => 'Kannada', 'ml' => 'Malayalam',
        ];
        $language = $names[$target] ?? $target;

        return "You translate Indian school learning material into {$language} for classroom use.\n\n"
            . "Rules:\n"
            . "- Keep the meaning exact. This is curriculum content; a paraphrase that shifts meaning is a defect.\n"
            . "- Keep technical terms recognisable: give the {$language} term with the English term in brackets "
            . "the first time it appears, because students sit English-medium board exams.\n"
            . "- Keep numbers, units, chemical symbols and equations unchanged.\n"
            . "- Preserve the line and bullet structure of the source.\n\n"
            . 'Reply with JSON only: {"title": "...", "body": "...", "glossary": [{"en": "...", "translated": "..."}], "notes": null}';
    }

    // ══════════════════════════════════════════════════════════════════════
    // Authoring assistance — draft a missing Type 1 variant (spec §9.1)
    // ══════════════════════════════════════════════════════════════════════

    public function llmVariantDraft(string $nodeKey, array $context, int $tenant, ?int $userId): array
    {
        $slot = (int) ($context['variant_number'] ?? 0);
        $spec = config('pal_content_model.variant_blueprint.' . $slot);
        if ($spec === null) {
            return ['ok' => false, 'error' => "Variant slot {$slot} is not in the variant ladder."];
        }

        $input = [
            'task' => 'variant_draft',
            'variant' => $slot,
            'format' => $spec['format'],
            'format_label' => $spec['label'],
            'concept' => $context['concept_name'] ?? null,
            'definition' => (string) ($context['definition'] ?? ''),
            'subject' => $context['subject'] ?? null,
            'grade' => $context['grade'] ?? null,
            'cultural_context' => $context['cultural_context'] ?? null,
            'evidence' => array_slice($context['evidence'] ?? [], 0, 6),
            'misconceptions' => array_slice($context['misconceptions'] ?? [], 0, 5),
        ];

        return $this->call(
            $nodeKey,
            'variant_draft',
            'V' . $slot,
            $input,
            $tenant,
            $userId,
            function (array $data) use ($spec, $slot) {
                $body = $data['body'] ?? null;
                if (! is_string($body) || trim($body) === '') {
                    return null;
                }

                $h5p = $data['h5p_type'] ?? null;
                if (! in_array($h5p, config('pal_content.h5p_types', []), true)) {
                    $h5p = $spec['h5p_type'];
                }

                return [
                    'variant_number' => $slot,
                    'format' => $spec['format'],
                    'title' => is_string($data['title'] ?? null) ? $data['title'] : null,
                    'body' => $body,
                    'production_notes' => is_string($data['production_notes'] ?? null) ? $data['production_notes'] : null,
                    'h5p_type' => $h5p,
                    'checkpoints' => is_array($data['checkpoints'] ?? null) ? array_values($data['checkpoints']) : [],
                ];
            },
            $this->variantSystemPrompt($spec),
            $this->variantUserPrompt($input)
        );
    }

    protected function variantSystemPrompt(array $spec): string
    {
        return "You draft one delivery variant of a concept-learning node for an Indian K-12 adaptive tutor.\n\n"
            . "This is variant format '{$spec['format']}' ({$spec['label']}), served when: {$spec['when_served']}.\n\n"
            . "Rules:\n"
            . "- The variant must teach the SAME concept in a DIFFERENT modality from a plain text explanation. "
            . "A student reaches this variant because an earlier one failed them; repeating it is the failure mode.\n"
            . "- Ground every claim in the supplied textbook evidence. Do not add facts that are not in it.\n"
            . "- Use Indian daily-life examples and Indian names.\n"
            . "- Where the format implies media (video, simulation), write the SCRIPT or the ACTIVITY SPEC a "
            . "producer would build from — do not pretend an asset exists.\n"
            . "- Address the listed misconceptions head-on.\n\n"
            . 'Reply with JSON only: {"title": "...", "body": "...", "production_notes": "...", '
            . '"h5p_type": "...", "checkpoints": ["question a learner answers mid-way", ...]}';
    }

    protected function variantUserPrompt(array $input): string
    {
        $evidence = $input['evidence'] === [] ? '(none)' : "• " . implode("\n• ", $input['evidence']);
        $misconceptions = $input['misconceptions'] === [] ? '(none listed)' : "• " . implode("\n• ", $input['misconceptions']);

        return "Subject: {$input['subject']}\nGrade: {$input['grade']}\nConcept: {$input['concept']}\n"
            . "Cultural context to use: " . ($input['cultural_context'] ?: 'any suitable Indian context') . "\n\n"
            . "Definition:\n{$input['definition']}\n\n"
            . "Textbook evidence:\n{$evidence}\n\n"
            . "Known misconceptions to pre-empt:\n{$misconceptions}";
    }

    // ══════════════════════════════════════════════════════════════════════
    // Shared call path
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Cache lookup → call → validate → cache write.
     *
     * @param  callable(array):?array  $validate  returns the cleaned payload, or
     *                                            null to reject the response
     */
    protected function call(
        string $nodeKey,
        string $kind,
        ?string $variant,
        array $input,
        int $tenant,
        ?int $userId,
        callable $validate,
        string $system,
        string $user
    ): array {
        $fingerprint = $this->llm->fingerprint($input);

        $cached = $this->llm->cached($nodeKey, $kind, $variant, $fingerprint, $tenant);
        if ($cached !== null) {
            return [
                'ok' => true,
                'data' => $cached['payload'],
                'cached' => true,
                'model' => $cached['model'],
                'generated_at' => $cached['generated_at'],
                'tagged_by' => config('pal_content_model.llm.forced_tagged_by', 'ai'),
                'quality_status' => config('pal_content_model.llm.forced_status', 'draft'),
            ];
        }

        if (! $this->llm->enabled()) {
            return ['ok' => false, 'error' => $this->llm->unavailableReason() ?? 'AI enrichment is unavailable.'];
        }

        $response = $this->llm->json($system, $user);
        if (! ($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => $response['error'] ?? 'The AI request failed.'];
        }

        $clean = $validate($response['data'] ?? []);
        if ($clean === null) {
            return [
                'ok' => false,
                'error' => 'The AI response did not contain a usable value from the registered vocabulary, so nothing was saved.',
            ];
        }

        $confidence = $this->unit($clean['confidence'] ?? null);

        $this->llm->remember(
            $nodeKey,
            $kind,
            $variant,
            $fingerprint,
            $tenant,
            $clean,
            $confidence,
            $response['model'] ?? null,
            $response['usage'] ?? [],
            $userId
        );

        return [
            'ok' => true,
            'data' => $clean,
            'cached' => false,
            'model' => $response['model'] ?? null,
            'usage' => $response['usage'] ?? [],
            // CONTENT LAW C5 — a machine proposes; a human approves.
            'tagged_by' => config('pal_content_model.llm.forced_tagged_by', 'ai'),
            'quality_status' => config('pal_content_model.llm.forced_status', 'draft'),
        ];
    }

    protected function unit($value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return ($n < 0 || $n > 1) ? null : round($n, 2);
    }

    /**
     * Whether a proposed metadata patch is safe to persist. Reuses the same
     * validator the existing Content Intelligence layer writes through, so the
     * two cannot diverge on what a legal tag is.
     */
    public function validatePatch(array $patch): array
    {
        return PalVocabulary::validate($patch);
    }
}
