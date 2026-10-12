<?php

namespace App\Services\StudyDeck\Branching;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\QuestionSelector;

/**
 * Orchestrates the chapter -> branching scenario pipeline:
 *
 *   load chapter, topics, concepts, intelligence, questions      (read-only)
 *   -> choose the concepts the scenario rests on                 (teacher, or ranked)
 *   -> pick self-contained bank questions to reuse as checks     (QuestionSelector)
 *   -> stage 1: the skeleton                                     (model, then checked)
 *   -> stage 2: the words, node by node                          (model, then checked)
 *   -> assemble the payload the editor saves
 *   -> validate; if specific nodes fail, ask for just those again, once
 *
 * It is the sibling of StudyDeckService and StudyDocumentService and shares
 * their inputs: the same ConceptContextBuilder, the same QuestionSelector, the
 * same Completer, the same grounding rule. It adds no data source and no model
 * integration of its own.
 *
 * It stores NOTHING. The caller decides what to do with a validated result: the
 * H5P controller returns it to the browser for review, and saving it is the
 * ordinary store() that makes a draft.
 */
class BranchingScenarioService
{
    public function __construct(
        private readonly ?Completer $completer,
        private readonly ConceptContextBuilder $contextBuilder = new ConceptContextBuilder(),
        private readonly QuestionSelector $questionSelector = new QuestionSelector(),
        private readonly ScenarioValidator $validator = new ScenarioValidator(),
        private readonly ScenarioAssembler $assembler = new ScenarioAssembler(),
    ) {
    }

    /** A completer-less service can still describe a chapter (see sources()). */
    public static function make(?Completer $completer): self
    {
        return new self($completer);
    }

    /** Question forms a decision can carry: a choice among options. */
    private const FORMS = ['mcq', 'true_false'];

    /**
     * What a teacher can build a scenario from. Read-only; no model call.
     *
     * @return array<string,mixed>
     */
    public function sources(int $chapterId, int $tenantId): array
    {
        $raw = $this->contextBuilder->load($chapterId, $tenantId);
        $context = $this->contextBuilder->assemble($raw);
        $eligible = $this->questionSelector->select((array) $raw['questions'], 6, self::FORMS)['eligible'];

        $ids = array_keys($context['concepts']);
        $scores = SourcePack::scores($ids, $context['concepts'], $eligible, $context);

        $concepts = [];
        foreach ($context['concepts'] as $id => $c) {
            $concepts[] = [
                'id' => $id,
                'name' => $c['name'],
                'topic_id' => $c['topic_id'],
                'questions' => count($eligible[$id] ?? []),
                // Enough to build a consequence from: knowledge or objectives on record.
                'has_material' => ! empty($c['knowledge']) || ! empty($c['objectives']) || ! empty($c['definition']),
                'suitability' => $scores[$id] ?? 0,
            ];
        }

        $topics = [];
        foreach ($context['topics'] as $topic) {
            $topics[] = ['id' => $topic['id'], 'name' => $topic['name'], 'concept_ids' => $topic['concept_ids']];
        }

        return [
            'chapter' => [
                'id' => (int) $raw['chapter']['id'],
                'name' => $raw['chapter']['chapter_name'],
                'standard' => $raw['chapter']['standard_name'],
                'subject' => $raw['chapter']['subject_name'],
                'has_text' => trim((string) $raw['ground_truth']) !== '',
            ],
            'topics' => $topics,
            'concepts' => $concepts,
            'limits' => ['max_concepts' => SourcePack::MAX_CONCEPTS],
        ];
    }

    /**
     * @param array<string,mixed> $options topic_ids, concept_ids, focus, length, use_questions, existing, model
     * @param callable(string $stage, string $message):void|null $progress
     * @return array<string,mixed> scenario (the save payload), report, and the intermediate artefacts of the run
     *
     * @throws \InvalidArgumentException when the request cannot be built (no such concept, too many)
     * @throws \RuntimeException when the chapter has no text, or a stage cannot produce a usable result
     */
    public function generate(int $chapterId, int $tenantId, array $options = [], ?callable $progress = null): array
    {
        ($progress ?? fn () => null)('load', 'Loading chapter data (read-only)');
        $raw = $this->contextBuilder->load($chapterId, $tenantId);

        if (trim((string) $raw['ground_truth']) === '') {
            throw new \RuntimeException('Chapter ' . $chapterId . ' has no chapter text (document_extractions.md_content); refusing to generate, because every fact in a scenario has to come from the chapter.');
        }

        return $this->fromRaw($raw, $options, $progress);
    }

    /**
     * The same pipeline from already-loaded rows: used by tests and replays.
     *
     * @param array<string,mixed> $raw ConceptContextBuilder::load()
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function fromRaw(array $raw, array $options = [], ?callable $progress = null): array
    {
        if ($this->completer === null) {
            throw new \LogicException('This service was made without a model, so it can describe a chapter but not write a scenario.');
        }

        $say = $progress ?? fn () => null;

        $context = $this->contextBuilder->assemble($raw);
        $selection = $this->questionSelector->select((array) $raw['questions'], 3, self::FORMS);
        $say('context', sprintf('%d topics, %d concepts, %d reusable bank question(s)', count($context['topics']), count($context['concepts']), array_sum(array_map('count', $selection['eligible']))));

        $pack = SourcePack::choose($context, $options, $selection['eligible']);
        $say('choose', 'Scenario rests on ' . count($pack->concepts) . ' concept(s): ' . implode(', ', array_map(fn ($c) => $c['name'], $pack->concepts)));

        $say('plan', 'Stage 1: planning the paths');
        $planner = new ScenarioPlanner($this->completer);
        $blueprint = $planner->plan($pack, $options, $say);
        $say('plan', count($blueprint['nodes']) . ' steps planned');

        $say('write', 'Stage 2: writing the steps');
        $writer = new ScenarioWriter($this->completer);
        $written = $writer->write($pack, $blueprint, $say);

        $meta = ['model' => $options['model'] ?? null, 'focus' => $options['focus'] ?? null];
        $assembled = $this->assembler->assemble($pack, $blueprint, $written, $meta);
        $report = $this->validator->validate($assembled['payload'], $pack, $options, $assembled['suggestions']);

        // One targeted rewrite. A validator finding is about a particular
        // node (an invented number, a thin screen, a repeated one), so only
        // those nodes go back to the model, with the exact findings.
        if (! $report['ok'] && $report['by_node'] !== []) {
            $say('fix', 'Rewriting ' . count($report['by_node']) . ' step(s) that failed checks');
            $written = $writer->fix($pack, $blueprint, $written, $report['by_node']);
            $assembled = $this->assembler->assemble($pack, $blueprint, $written, $meta);
            $report = $this->validator->validate($assembled['payload'], $pack, $options, $assembled['suggestions']);
        }

        $say('validate', $report['ok'] ? 'Validation passed' : count($report['errors']) . ' validation error(s)');

        return [
            'scenario' => $assembled['payload'],
            'report' => $this->publicReport($report, $pack),
            'blueprint' => $blueprint,
            'written' => $written,
            'replies' => ['planner' => $planner->replies, 'writer' => $writer->replies],
        ];
    }

    /**
     * The report as the browser gets it: what was checked and found, and what the
     * scenario rests on. Without the per-node index the service used internally.
     *
     * @param array<string,mixed> $report
     * @return array<string,mixed>
     */
    private function publicReport(array $report, SourcePack $pack): array
    {
        unset($report['by_node']);

        $report['concepts'] = array_values(array_map(
            fn ($id, $c) => ['id' => $id, 'name' => $c['name']],
            array_keys($pack->concepts),
            $pack->concepts
        ));

        return $report;
    }
}
