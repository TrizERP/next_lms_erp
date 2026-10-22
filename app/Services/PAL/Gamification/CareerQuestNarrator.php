<?php

namespace App\Services\PAL\Gamification;

use App\CareerIntelligence\CaiCoreService;
use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Exceptions\GraphUnavailableException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The Career Quest narrative — a short, bounded paragraph explaining what a
 * learner's Career Pathway Report snapshot means, following exactly the
 * retrieval-then-generation split `CoherenceMapController::explain()`
 * established: `CaiCoreService::evaluate()` does the one graph read (zero
 * LLM calls in that class, unchanged by this), and this class does the one
 * LLM call, grounded in nothing but what `evaluate()` and the quest snapshot
 * actually returned.
 *
 * `CaiCoreService` and `CareerQuestService` are two separate PAL career
 * subsystems built on two separate Neo4j graphs — CAI-CORE's O*NET
 * occupation/stream/subject/exam graph (`database/neo4j/cai/`), versus
 * `CareerQuestState`'s own derived RIASEC/pathway/cluster model, which is not
 * graph-backed at all. This class is the bridge: it narrates both sets of
 * real facts side by side without inventing a connection neither one
 * actually states.
 *
 * Distinct outcomes, matching explain()'s contract: a graph/Neo4j failure
 * from `CaiCoreService` returns `graph_unavailable`; an LLM failure returns
 * `model_unavailable`; genuinely insufficient evidence (no aspiration on
 * file, the occupation not yet seeded, RIASEC not ready) is NOT an error —
 * it is relayed as `insufficient_evidence` with an honest message, never a
 * fabricated narrative.
 */
class CareerQuestNarrator
{
    public function __construct(
        private readonly CaiCoreService $cai,
        private readonly AiModelClientFactory $aiFactory,
    ) {
    }

    /**
     * @param  array<string, mixed>  $quest  CareerQuestService::quest()'s own return
     *                                       shape — already real, derived data; nothing
     *                                       here is invented by this class.
     * @return array{status:string, narrative:?string, message:?string}
     */
    public function narrate(int $learnerId, int $subInstituteId, array $quest): array
    {
        $academicYear = $this->academicYearFor($learnerId);

        try {
            $alignment = $this->cai->evaluate((string) $learnerId, $academicYear);
        } catch (GraphUnavailableException|Throwable $exception) {
            return $this->result('graph_unavailable', null,
                'The career knowledge graph is unavailable right now, so a narrative could not be generated.');
        }

        $riasecReady = (bool) ($quest['riasec']['ready'] ?? false);
        $alignmentKnown = $alignment['alignment_status'] !== 'INSUFFICIENT_DATA';

        if (! $riasecReady && ! $alignmentKnown) {
            return $this->result('insufficient_evidence', null,
                'There is not enough career evidence yet — from either the subject-alignment check or '
                . 'the RIASEC profile — to write an honest narrative.');
        }

        // Provider, model, key and fallbacks come from the school's saved Career Intelligence configuration.
        $client = $this->aiFactory->for('career_intelligence', $subInstituteId);

        if (! $client->isConfigured()) {
            return $this->result('model_unavailable', null, 'No AI model is configured for this institute.');
        }

        try {
            // Same generous ceiling as CoherenceMapController::explain(), and for the
            // same measured reason: the configured model spends output-token budget on
            // internal reasoning before any visible text.
            $narrative = $client->chat(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($quest, $alignment)],
                ],
                model: null,
                maxTokens: 2048,
                temperature: 0.3,
            );
        } catch (AiConfigurationException $exception) {
            return $this->result('model_unavailable', null, $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->result('model_unavailable', null, 'The narrative could not be generated.');
        }

        $narrative = trim((string) $narrative);

        if ($narrative === '') {
            return $this->result('model_unavailable', null, 'The narrative could not be generated.');
        }

        return $this->result('ok', $narrative, null);
    }

    /**
     * @return array{status:string, narrative:?string, message:?string}
     */
    private function result(string $status, ?string $narrative, ?string $message): array
    {
        return ['status' => $status, 'narrative' => $narrative, 'message' => $message];
    }

    private function systemPrompt(): string
    {
        return 'You are writing a short narrative for a student\'s Career Pathway Report. Use only the '
            . 'facts given in the message — never invent a pathway, a RIASEC type, a skill, an occupation '
            . 'requirement, or an alignment status that is not present in the input. If the subject-alignment '
            . 'check found no occupation on file, or the data is insufficient, say so plainly rather than '
            . 'guessing one. Keep the answer to 4-6 sentences of plain, encouraging language, addressed '
            . 'directly to the student.';
    }

    /**
     * @param  array<string, mixed>  $quest
     * @param  array<string, mixed>  $alignment
     */
    private function userPrompt(array $quest, array $alignment): string
    {
        $lines = [];

        $top = $quest['riasec']['top'] ?? null;

        if (is_array($top)) {
            $lines[] = sprintf('Strongest RIASEC signal: %s (%s).', $top['label'], $top['blurb']);
        }

        if (! empty($quest['pathways']) && is_array($quest['pathways'])) {
            $lines[] = 'Ranked pathway suggestions, from the learner\'s own evidence:';

            foreach ($quest['pathways'] as $pathway) {
                $lines[] = sprintf(
                    '- %s (match %s%%): %s',
                    $pathway['label'],
                    $pathway['match_score'],
                    $pathway['why']
                );
            }
        }

        if (! empty($quest['skill_progress']) && is_array($quest['skill_progress'])) {
            $sp = $quest['skill_progress'];
            $lines[] = sprintf(
                'Skill progress toward %s: %d of %d skills mastered (%d%%).',
                $sp['pathway_label'],
                $sp['mastered'],
                $sp['target'],
                $sp['percent']
            );
        }

        if (! empty($quest['career_exposure']) && is_array($quest['career_exposure'])) {
            $titles = array_values(array_unique(array_merge(
                ...array_map(static fn ($c) => is_array($c['titles'] ?? null) ? $c['titles'] : [], $quest['career_exposure'])
            )));

            if ($titles !== []) {
                $lines[] = 'Career roles the learner has been exposed to via scenarios: '
                    . implode(', ', array_slice($titles, 0, 6)) . '.';
            }
        }

        $lines[] = sprintf('Subject-alignment check: %s.', $alignment['alignment_status']);

        if ($alignment['alignment_status'] === 'MISALIGNED' && ! empty($alignment['break_point'])) {
            $bp = $alignment['break_point'];

            if (! empty($bp['missing_subjects'])) {
                $lines[] = 'Missing required subject(s): ' . implode(', ', $bp['missing_subjects']) . '.';
            }

            if (! empty($bp['required_stream']) && ! empty($bp['current_stream'])
                && $bp['required_stream'] !== $bp['current_stream']) {
                $lines[] = sprintf(
                    'Required stream is %s; currently enrolled in %s.',
                    $bp['required_stream'],
                    $bp['current_stream']
                );
            }
        }

        if ($alignment['alignment_status'] === 'INSUFFICIENT_DATA' && ! empty($alignment['insufficient_data_reason'])) {
            $lines[] = 'Reason subject-alignment is unavailable: ' . $alignment['insufficient_data_reason'];
        }

        $lines[] = 'Write a short, honest, encouraging narrative for the student, grounded only in the facts above.';

        return implode("\n", $lines);
    }

    /**
     * The same bare 4-digit-year `syear` convention CaiCoreService's own caller
     * (`lmsCounsellingController::careerAlignment()`) reads from the session —
     * resolved here from the learner's own enrolment instead, since this class is
     * called from a JWT-scoped PAL API route rather than a session-based one.
     */
    private function academicYearFor(int $learnerId): string
    {
        $syear = DB::table('tblstudent_enrollment')
            ->where('student_id', $learnerId)
            ->orderByDesc('syear')
            ->orderByDesc('id')
            ->value('syear');

        return (string) ($syear ?? now()->year);
    }
}
