<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Documents\Writers\ActivityWriter;
use App\Services\StudyDeck\Documents\Writers\DocumentWriter;
use App\Services\StudyDeck\Documents\Writers\RemedialWriter;
use App\Services\StudyDeck\Documents\Writers\RevisionNotesWriter;
use App\Services\StudyDeck\H5pPatternSelector;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\InteractionPlanner;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;

/**
 * Orchestrates chapter -> study document, for revision notes, a remedial class or classroom activities:
 *
 *   load chapter, topics, concepts, intelligence, prerequisites, questions        (read-only)
 *   -> pick self-contained bank questions                                        (QuestionSelector, the deck's own)
 *   -> learning map: the teaching order, prerequisites first                     (LearningPlanBuilder, the deck's own)
 *   -> choose the bank questions each part will use                              (QuestionPlacement, deterministic)
 *   -> write the wording of each part                                             (a DocumentWriter, the model)
 *   -> assemble the document: numbering, diagrams, hotspots, activity specs      (DocumentAssembler, code)
 *   -> render the design-system markup the content row holds                      (DocumentHtmlRenderer)
 *   -> validate                                                                    (DocumentValidator)
 *
 * It is the study deck's pipeline with the kind-specific middle swapped: the same context loader, the same question
 * filter, the same map, the same Completer and the same checks. It STORES NOTHING. The caller decides what to do with
 * a validated result (the artisan command writes a review bundle; ContentGenerationService persists to
 * content_master). Intermediate artefacts are returned so a run can be inspected.
 */
class StudyDocumentService
{
    /** @param array<string,DocumentWriter> $writers by DocumentKind value */
    public function __construct(
        private readonly ConceptContextBuilder $contextBuilder,
        private readonly QuestionSelector $questionSelector,
        private readonly LearningPlanBuilder $learningPlan,
        private readonly QuestionPlacement $placement,
        private readonly DocumentAssembler $assembler,
        private readonly DocumentHtmlRenderer $html,
        private readonly DocumentValidator $validator,
        private readonly array $writers,
    ) {
    }

    /** @param ConceptContextBuilder|null $context where the chapter's rows come from (tests hand in one that reads no database) */
    public static function make(Completer $completer, DiagramImageStore $images, ?ConceptImageSearchService $search = null, ?ConceptContextBuilder $context = null): self
    {
        $checker = new InteractionPlanner($completer);
        $placement = new QuestionPlacement();

        return new self(
            $context ?? new ConceptContextBuilder(),
            new QuestionSelector(),
            new LearningPlanBuilder(new H5pPatternSelector()),
            $placement,
            new DocumentAssembler(new ImagePlanner($search ?? app(ConceptImageSearchService::class), $images), $checker, $placement),
            new DocumentHtmlRenderer(),
            new DocumentValidator($checker),
            [
                DocumentKind::RevisionNotes->value => new RevisionNotesWriter($completer, 6),
                DocumentKind::Remedial->value => new RemedialWriter($completer, 3, $checker),
                DocumentKind::Activities->value => new ActivityWriter($completer, 3, $checker),
            ]
        );
    }

    /**
     * @param array<string,mixed> $options concept_ids (limit the document to these concepts), per_concept, allowed_forms
     * @param callable(string $stage, string $message):void|null $progress
     * @return array<string,mixed>
     */
    public function generate(DocumentKind $kind, int $chapterId, int $tenantId, array $options = [], ?callable $progress = null): array
    {
        $say = $progress ?? fn () => null;

        $say('load', 'Loading chapter data (read-only)');
        $raw = $this->contextBuilder->load($chapterId, $tenantId);
        if (trim($raw['ground_truth']) === '') {
            throw new \RuntimeException('Chapter ' . $chapterId . ' has no ground-truth text (document_extractions.md_content); refusing to generate.');
        }

        return $this->fromRaw($kind, $raw, $options, $say);
    }

    /**
     * The same pipeline from already-loaded rows - used by tests and replays.
     *
     * @param array<string,mixed> $raw
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function fromRaw(DocumentKind $kind, array $raw, array $options = [], ?callable $progress = null): array
    {
        $say = $progress ?? fn () => null;

        $context = $this->contextBuilder->assemble($raw);
        $say('context', sprintf('%d topics, %d concepts, %d without intelligence', count($context['topics']), count($context['concepts']), count($context['unmatched_concepts'])));

        $selection = $this->questionSelector->select(
            (array) $raw['questions'],
            (int) ($options['per_concept'] ?? 4),
            $options['allowed_forms'] ?? ['mcq', 'true_false', 'very_short_answer', 'short_answer']
        );
        $eligible = $selection['eligible'];
        $say('questions', sprintf('%d questions considered, %d excluded as not self-contained or unusable', count((array) $raw['questions']), count($selection['excluded'])));

        $map = $this->learningPlan->build($context, $eligible);
        $scope = $this->scope($map, (array) ($options['concept_ids'] ?? []));
        $say('map', count($scope) . ' of ' . count($map['concepts']) . ' concepts in scope' . ($map['warnings'] ? ' (' . count($map['warnings']) . ' map warning(s))' : ''));

        $writer = $this->writers[$kind->value] ?? throw new \InvalidArgumentException('No writer for ' . $kind->value);
        $step = fn (int $done, int $total) => $say('write', "$done/$total written");
        $say('write', 'Writing the ' . strtolower($kind->label()) . ' with the model');

        try {
            [$document, $draft] = match ($kind) {
                DocumentKind::RevisionNotes => $this->revision($writer, $context, $map, $scope, $eligible, $step),
                DocumentKind::Remedial => $this->remedial($writer, $context, $map, $scope, $eligible, $step),
                DocumentKind::Activities => $this->activities($writer, $context, $map, $scope, $eligible, $step),
            };
        } catch (\RuntimeException $e) {
            $dir = storage_path('app/study-deck/chapter-' . (int) $raw['chapter']['id']);
            @mkdir($dir, 0775, true);
            $file = $dir . '/rejected-' . $kind->value . '-replies.json';
            file_put_contents($file, json_encode($writer->replies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            throw new \RuntimeException($e->getMessage() . "\n(raw replies saved to $file)", 0, $e);
        }
        $say('assemble', count($document['sections']) . ' parts, ' . $document['stats']['questions'] . ' bank question(s), ' . $document['stats']['diagrams'] . ' diagram(s)');

        $questions = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $questions[$q['id']] = $q;
            }
        }
        $html = $this->html->render($document, $questions);
        $report = $this->validator->validate($kind, $context, $map, $scope, $eligible, $document, $html, $selection['flagged'] ?? []);
        $say('validate', $report['ok'] ? 'Validation passed' : count($report['errors']) . ' validation error(s)');

        return [
            'kind' => $kind->value,
            'context' => $context,
            'selection' => $selection,
            'map' => $map,
            'scope' => $scope,
            'draft' => $draft,
            'document' => $document,
            'html' => $html,
            'report' => $report,
        ];
    }

    // ---------------------------------------------------------------------------------------------------------

    /**
     * The concepts the document covers, in teaching order: the ones asked for (that this chapter really has), or all.
     *
     * @param array<string,mixed> $map
     * @param array<int,mixed> $asked
     * @return array<int,int>
     */
    private function scope(array $map, array $asked): array
    {
        $order = array_map('intval', $map['sequence']);
        $asked = array_values(array_unique(array_map('intval', $asked)));
        $scope = $asked === [] ? $order : array_values(array_filter($order, fn ($id) => in_array($id, $asked, true)));

        if ($scope === []) {
            throw new \InvalidArgumentException($asked === [] ? 'The chapter has no concepts to write about.' : 'None of the requested concepts belongs to this chapter.');
        }

        return $scope;
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} [document, draft] */
    private function revision(DocumentWriter $writer, array $context, array $map, array $scope, array $eligible, callable $step): array
    {
        $placed = $this->placement->forRevision($eligible, $scope, $map['concepts']);
        $draft = $writer->write($context, $map, $scope, $placed, $step);

        return [$this->assembler->revisionNotes($context, $map, $scope, $draft, $placed), $draft];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function remedial(DocumentWriter $writer, array $context, array $map, array $scope, array $eligible, callable $step): array
    {
        $placed = $this->placement->forRemedial($eligible, $scope);
        $draft = $writer->write($context, $map, $scope, $placed, $step);

        return [$this->assembler->remedial($context, $map, $scope, $draft, $placed), $draft];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function activities(DocumentWriter $writer, array $context, array $map, array $scope, array $eligible, callable $step): array
    {
        /** @var ActivityWriter $writer */
        $used = [];
        // By reference: each activity's questions are taken out of what the next one may use.
        $allocate = function (array $ids, int $limit) use (&$used, $eligible): array {
            return $this->placement->forActivity($eligible, $ids, $limit, $used);
        };
        $draft = $writer->write($context, $map, $scope, $allocate, $step);

        return [$this->assembler->activities($context, $map, $scope, $draft), $draft];
    }
}
