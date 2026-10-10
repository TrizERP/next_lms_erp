<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Documents\Writers\ActivityWriter;
use App\Services\StudyDeck\Documents\Writers\DocumentWriter;
use App\Services\StudyDeck\Documents\Writers\RemedialFrameWriter;
use App\Services\StudyDeck\Documents\Writers\RemedialWriter;
use App\Services\StudyDeck\Documents\Writers\RevisionNotesWriter;
use App\Services\StudyDeck\Documents\Writers\TopicRevisionWriter;
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
                // The default design of revision notes and a remedial class is the PURPOSE-based one: sheets for exam
                // preparation, and a class that reteaches the concepts that may be hard (its lessons, then everything around them).
                DocumentKind::RevisionNotes->value => new TopicRevisionWriter($completer, 2),
                DocumentKind::Remedial->value => new RemedialWriter($completer, 3, $checker, false, true),
                self::FRAME => new RemedialFrameWriter($completer, 4),
                // The one-card-per-concept design of both: only reachable by asking for the `standard` profile. It writes the same
                // concepts twice with different field names, which is what the purpose-based design replaced.
                self::standardKey(DocumentKind::RevisionNotes) => new RevisionNotesWriter($completer, 6),
                self::standardKey(DocumentKind::Remedial) => new RemedialWriter($completer, 3, $checker),
                self::compactKey(DocumentKind::RevisionNotes) => new RevisionNotesWriter($completer, 8, true),
                self::compactKey(DocumentKind::Remedial) => new RemedialWriter($completer, 6, $checker, true),
                DocumentKind::Activities->value => new ActivityWriter($completer, 3, $checker),
                self::compactKey(DocumentKind::Activities) => new ActivityWriter($completer, 4, $checker, true),
            ]
        );
    }

    /** The writers' key for the remedial class's objectives, mix-ups and teacher's guide. */
    private const FRAME = 'remedial:frame';

    /** The most questions a revision pack's "test yourself" holds. */
    public const REVISION_CHECK_QUESTIONS = 10;

    /**
     * The questions the remedial class leaves to the revision notes: a few more than the notes use, so that a pack trimmed to fit its
     * pages still takes none of the class's questions. The notes' questions are a prefix of this list.
     */
    public const REVISION_CHECK_RESERVED = 12;

    /**
     * The bank questions of each part of a document, by the name of the part: what was asked where.
     *
     * @param array<string,mixed> $document
     * @return array<string,mixed>
     */
    private function questionIds(array $document): array
    {
        $out = [];
        foreach ($document['sections'] as $s) {
            if (($s['question_ids'] ?? []) === []) {
                continue;
            }
            if ($s['type'] === 'unit') {
                $out['guided'][$s['taught_concept_ids'][0] ?? 0] = $s['question_ids'];
            } else {
                $out[$s['type']] = $s['question_ids'];
            }
        }

        return $out;
    }

    /** The writers' key for the standard (one card per concept) profile of a kind. */
    public static function standardKey(DocumentKind $kind): string
    {
        return $kind->value . ':standard';
    }

    /**
     * Which design is being made. `compact` is the exact-N-page grid; `standard` the one-card-per-concept design; `purpose`
     * (the default for revision notes and a remedial class) the one built for what the document is for. Classroom activities
     * have no purpose-based design: they are `standard` unless compact.
     *
     * @param array<string,mixed> $options
     */
    private function profile(DocumentKind $kind, array $options): string
    {
        if (!empty($options['compact'])) {
            return 'compact';
        }
        if (($options['profile'] ?? '') === 'standard' || $kind === DocumentKind::Activities) {
            return 'standard';
        }

        return 'purpose';
    }

    /** The writers' key for the compact profile of a kind. */
    public static function compactKey(DocumentKind $kind): string
    {
        return $kind->value . ':compact';
    }

    /** The most questions one concept is asked about in a compact pack: the validator's own limit for the kind. */
    private const COMPACT_PER_CONCEPT = ['revision_notes' => 2, 'remedial' => 3, 'activities' => 3];

    /**
     * @param array<string,mixed> $options concept_ids (limit the document to these concepts), per_concept, allowed_forms;
     *        and, for any kind, `compact` (a pack fitted to a page count), `pages` (that count, default 5) and
     *        `measure` (callable(array $document): array{revision:int,practice:int}, the pages each copy of a document
     *        comes to - required with `compact`, since the fit is made by drawing) and optionally `draft` (the draft.json
     *        of an earlier compact run: the parts are not written again, only fitted again, so a change of layout costs no model call)
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

        $profile = $this->profile($kind, $options);
        $compact = $profile === 'compact';
        $purpose = $profile === 'purpose';
        $writerKey = match ($profile) {
            'compact' => self::compactKey($kind),
            'standard' => $kind === DocumentKind::Activities ? $kind->value : self::standardKey($kind),
            default => $kind->value,
        };
        $writer = $this->writers[$writerKey] ?? throw new \InvalidArgumentException('No writer for ' . $writerKey);
        $step = fn (int $done, int $total) => $say('write', "$done/$total written");
        $say('write', 'Writing the ' . ($compact ? 'compact ' : '') . strtolower($kind->label()) . ' with the model' . ($purpose ? ' (purpose-based design)' : ''));

        try {
            [$document, $draft] = match (true) {
                $compact => $this->compactDocument($kind, $writer, $context, $map, $scope, $eligible, $step, $options, $say),
                $purpose && $kind === DocumentKind::RevisionNotes => $this->purposeRevision($writer, $context, $map, $scope, $eligible, $step, $options, $say),
                $purpose => $this->purposeRemedial($writer, $context, $map, $scope, $eligible, $step, $options, $say),
                $kind === DocumentKind::RevisionNotes => $this->revision($writer, $context, $map, $scope, $eligible, $step),
                $kind === DocumentKind::Remedial => $this->remedial($writer, $context, $map, $scope, $eligible, $step),
                default => $this->activities($writer, $context, $map, $scope, $eligible, $step),
            };
        } catch (\RuntimeException $e) {
            $dir = storage_path('app/study-deck/chapter-' . (int) $raw['chapter']['id']);
            @mkdir($dir, 0775, true);
            // A compact or purpose-based run has its own file: it never overwrites the replies an earlier run left for inspection.
            $file = $dir . '/rejected-' . $kind->value . ($compact ? '-compact' : ($purpose ? '-purpose' : '')) . '-replies.json';
            $replies = $writer->replies;
            if ($purpose && $kind === DocumentKind::Remedial) {
                $replies = ['lessons' => $writer->replies, 'frame' => $this->writers[self::FRAME]->replies ?? []];
            }
            file_put_contents($file, json_encode($replies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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
        if ($compact) {
            // The page count is a requirement of the pack, so a pack that missed it does not pass.
            $target = (int) $document['compact']['target_pages'];
            $report['stats']['pages'] = $document['compact']['pages'];
            foreach ($document['compact']['pages'] as $copy => $n) {
                if ($n !== $target) {
                    $report['errors'][] = 'The ' . ($copy === 'revision' ? 'answers-shown' : 'answers-hidden') . ' copy comes to ' . $n . ' pages; the compact pack must be exactly ' . $target . '.';
                }
            }
            $report['ok'] = $report['errors'] === [];
        }
        if ($purpose) {
            $this->checkPages($kind, $document, $report);
        }
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

    // ---------------------------------------------------------------------------------------------------------
    // The purpose-based designs
    //
    // Revision notes and a remedial class are written from the same chapter data, so they would come out as the same
    // document with different field names if both were "a part for every concept". They are not: each is built for what it
    // is used for, from its own plan of the chapter, with its own questions (see DocumentAssembler's purpose section).

    /** The pages each purpose-based kind is aimed at (it must come to 5 to 15 whatever the aim). */
    private const PURPOSE_TARGET = ['revision_notes' => [5, 10], 'remedial' => [8, 15]];

    /**
     * Revision notes: a sheet for every topic, the key terms, and a small exam-style set of the bank's questions.
     *
     * @param array<string,mixed> $options `measure` and `draft` as for a compact pack
     * @return array{0:array<string,mixed>,1:array<string,mixed>} [document, draft]
     */
    private function purposeRevision(DocumentWriter $writer, array $context, array $map, array $scope, array $eligible, callable $step, array $options, callable $say): array
    {
        if (isset($options['draft'])) {
            $draft = $this->checkedPurposeDraft(DocumentKind::RevisionNotes, $map, $scope, (array) $options['draft']);
            // A draft of an earlier run may predate the clean-up of what the document says twice; it is idempotent.
            $draft['topics'] = TopicRevisionWriter::dedupe($draft['topics']);
            $say('write', 'Using the draft of an earlier run (no model call)');
        } else {
            $draft = $writer->write($context, $map, $scope, [], $step);
        }

        // The questions are the bank's, chosen by code. If the pages run over, the fewest that leave a representative set are dropped.
        $build = function (int $n) use ($context, $map, $scope, $eligible, $draft): array {
            $check = $this->placement->forCheck($eligible, $scope, $map['concepts'], $n);
            $document = $this->assembler->revisionPurpose($context, $map, $scope, $draft, $check);
            $document['purpose']['check_questions'] = count($check);

            return $document;
        };

        return [$this->fitPurpose($build, range(self::REVISION_CHECK_QUESTIONS, 6), self::PURPOSE_TARGET['revision_notes'][1], $options, $say), $draft];
    }

    /**
     * A remedial class: a short check, the table of potential difficulties, lessons for the concepts that may be hard (and only
     * those), the common mix-ups, practice on your own, an exit check and a teacher's guide.
     *
     * @param array<string,mixed> $options `measure` and `draft` as for a compact pack, and `lessons` (how many concepts get a lesson)
     * @return array{0:array<string,mixed>,1:array<string,mixed>} [document, draft]
     */
    private function purposeRemedial(DocumentWriter $lessons, array $context, array $map, array $scope, array $eligible, callable $step, array $options, callable $say): array
    {
        $frame = $this->writers[self::FRAME] ?? throw new \InvalidArgumentException('No writer for ' . self::FRAME);

        $analysis = (new GapAnalysis())->analyse($map, $scope, $eligible, isset($options['lessons']) ? (int) $options['lessons'] : null);
        $say('gaps', count($analysis['focus']) . ' of ' . count($scope) . ' concepts get a lesson, ranked from the chapter data alone (no class results exist): '
            . implode('; ', array_map(fn ($id) => $map['concepts'][$id]['name'], $analysis['focus'])));

        // Each stage of the class takes its own questions, and none of the revision notes' (they are for another purpose).
        $reserved = array_column($this->placement->forCheck($eligible, $scope, $map['concepts'], self::REVISION_CHECK_RESERVED), 'id');
        $sets = $this->placement->forRemedialClass($eligible, $scope, $map['concepts'], $analysis['focus'], $reserved);

        if (isset($options['draft'])) {
            $draft = $this->checkedPurposeDraft(DocumentKind::Remedial, $map, $scope, (array) $options['draft'], $analysis['focus']);
            $say('write', 'Using the draft of an earlier run (no model call)');
        } else {
            $written = $lessons->write($context, $map, $analysis['focus'], $sets['guided'], $step);
            $wording = $frame->write($context, $map, $scope, [
                'focus' => $analysis['focus'],
                'lessons' => $written['units'],
                'reasons' => array_map(fn ($r) => $r['reasons'], $analysis['rows']),
            ], $step);
            $draft = ['units' => $written['units'], 'frame' => $wording];
        }

        // A rung is [questions to practise on your own, guided questions per unit, lessons]. Everything that depends on the rung (which
        // concepts keep a lesson, and so which questions each stage takes) is worked out again for it, from the same chapter data.
        $build = function (array $knob) use ($context, $map, $scope, $eligible, $draft, $analysis, $reserved): array {
            [$independent, $guided, $units] = $knob;
            $kept = GapAnalysis::withLessons($analysis, $units);
            $placed = $this->placement->forRemedialClass($eligible, $scope, $map['concepts'], $kept['focus'], $reserved, $guided, $independent);
            $document = $this->assembler->remedialPurpose($context, $map, $scope, $draft, $kept, $placed);
            $document['purpose']['practice_questions'] = ['independent' => count($placed['independent']), 'guided_per_unit' => $guided, 'units' => count($kept['focus'])];

            return $document;
        };

        $document = $this->fitPurpose($build, self::remedialLadder(count($analysis['focus'])), self::PURPOSE_TARGET['remedial'][1], $options, $say);

        // Kept with the draft so a later run can be compared with this one: the ranking and the question allocation.
        $draft['analysis'] = ['focus_written' => $analysis['focus'], 'ranked' => $analysis['focus_ranked'], 'scores' => array_map(fn ($r) => $r['score'], $analysis['rows'])];
        $draft['questions'] = ['reserved_for_revision_notes' => $reserved] + $this->questionIds($document);

        return [$document, $draft];
    }

    /**
     * The rungs a remedial class is fitted to its pages by, most content first: [questions to practise on your own, guided questions
     * for each unit, units]. The practice on your own is trimmed a little first, then the lowest-ranked lesson goes (never below
     * four), and only then is the guided practice cut to one question for each lesson: a lesson is what the class is for, and a
     * hint with the first question is what makes the practice guided.
     *
     * @return array<int,array{0:int,1:int,2:int}>
     */
    public static function remedialLadder(int $lessons): array
    {
        $fewest = min($lessons, max(4, $lessons - 2));
        $ladder = [];
        for ($units = $lessons; $units >= $fewest; $units--) {
            foreach ([[6, 2], [5, 2], [4, 2]] as [$independent, $guided]) {
                $ladder[] = [$independent, $guided, $units];
            }
        }
        array_push($ladder, [4, 1, $fewest], [3, 1, $fewest]);

        return $ladder;
    }

    /**
     * The document at the first rung of the ladder whose copies both fit the page limit. A rung is a way of giving the
     * document less optional content (fewer questions); nothing is ever cut mid-part, and when even the last rung is too
     * long the run stops instead of producing a document that breaks its limit.
     *
     * @param callable(mixed):array<string,mixed> $build the document for one rung
     * @param array<int,mixed> $ladder rungs, most content first
     * @param int $aimHigh the most pages the kind is aimed at
     * @param array<string,mixed> $options `measure`: callable(document): array{revision:int,practice:int}; without it nothing is drawn
     * @return array<string,mixed>
     */
    private function fitPurpose(callable $build, array $ladder, int $aimHigh, array $options, callable $say): array
    {
        $measure = $options['measure'] ?? null;
        if (!is_callable($measure)) {
            return $build($ladder[0]);
        }

        // The limit is a requirement; the aim of the kind is what the fit works towards (the first rung inside it wins), and a rung
        // that only fits the limit is kept as the fallback, with the report saying the aim was missed.
        $max = DocumentAssembler::PURPOSE_PAGES[1];
        $fallback = null;
        foreach ($ladder as $rung) {
            $document = $build($rung);
            $pages = $measure($document);
            $longest = max($pages['revision'], $pages['practice']);
            $document['purpose']['pages'] = ['revision' => $pages['revision'], 'practice' => $pages['practice']];
            if ($longest <= $aimHigh) {
                $say('fit', $pages['revision'] . ' pages (answers shown), ' . $pages['practice'] . ' pages (answers hidden)');

                return $document;
            }
            if ($longest <= $max) {
                $fallback ??= $document;
            }
            $say('fit', $longest . ' pages is over the aim of ' . $aimHigh . '; trying with less practice');
        }
        if ($fallback !== null) {
            $say('fit', 'no rung reaches the aim; keeping the fullest one inside the ' . $max . ' page limit');

            return $fallback;
        }

        throw new \RuntimeException('Even with the least practice the document comes to more than ' . $max . ' pages. Shorten the writers\' limits and write again.');
    }

    /**
     * The page limit is a requirement of the document, so a document that missed it does not pass. The aim of each kind is
     * only advice: a warning, not a failure.
     *
     * @param array<string,mixed> $document
     * @param array<string,mixed> $report
     */
    private function checkPages(DocumentKind $kind, array $document, array &$report): void
    {
        $pages = $document['purpose']['pages'] ?? null;
        if ($pages === null) {
            $report['warnings'][] = 'The page count was not measured (no PDF was drawn), so the 5 to 15 page limit has not been checked.';

            return;
        }
        [$min, $max] = [(int) $document['purpose']['min_pages'], (int) $document['purpose']['max_pages']];
        [$aimLow, $aimHigh] = self::PURPOSE_TARGET[$kind->value] ?? [$min, $max];
        $report['stats']['pages'] = $pages;
        foreach ($pages as $copy => $n) {
            $name = $copy === 'revision' ? 'answers-shown' : 'answers-hidden';
            if ($n < $min || $n > $max) {
                $report['errors'][] = 'The ' . $name . ' copy comes to ' . $n . ' pages; it must come to ' . $min . ' to ' . $max . '.';
            } elseif ($n < $aimLow || $n > $aimHigh) {
                $report['warnings'][] = 'The ' . $name . ' copy comes to ' . $n . ' pages; ' . $kind->label() . ' are aimed at ' . $aimLow . ' to ' . $aimHigh . '.';
            }
        }
        $report['ok'] = $report['errors'] === [];
    }

    /**
     * A draft handed in from an earlier run must still be a draft of THIS chapter: every topic's sheet (revision notes), or every
     * lesson the chapter data now picks, with the wording around them (a remedial class). Otherwise the run stops, not the document.
     *
     * @param array<int,int> $scope
     * @param array<string,mixed> $draft
     * @param array<int,int> $focus the concepts that get a lesson
     * @return array<string,mixed>
     */
    private function checkedPurposeDraft(DocumentKind $kind, array $map, array $scope, array $draft, array $focus = []): array
    {
        if ($kind === DocumentKind::RevisionNotes) {
            $need = [];
            foreach ($map['topics'] as $t) {
                if (array_intersect($t['concept_ids'], $scope)) {
                    $need[] = (int) $t['id'];
                }
            }
            $missing = array_diff($need, array_map('intval', array_keys((array) ($draft['topics'] ?? []))));
            if ($missing !== [] || !isset($draft['overview']['summary'])) {
                throw new \InvalidArgumentException('The draft has no sheet for topic(s) ' . implode(', ', $missing) . ' or no overview; it is not a draft of this chapter.');
            }

            return $draft;
        }

        $missing = array_diff($focus, array_map('intval', array_keys((array) ($draft['units'] ?? []))));
        if ($missing !== [] || !isset($draft['frame']['objectives'], $draft['frame']['clinic'], $draft['frame']['teacher'])) {
            throw new \InvalidArgumentException('The draft has no lesson for concept(s) ' . implode(', ', $missing) . ' or lacks its objectives, mix-ups or teacher guide; it is not a draft of this chapter.');
        }

        return $draft;
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} [document, draft] */
    private function revision(DocumentWriter $writer, array $context, array $map, array $scope, array $eligible, callable $step): array
    {
        $placed = $this->placement->forRevision($eligible, $scope, $map['concepts']);
        $draft = $writer->write($context, $map, $scope, $placed, $step);

        return [$this->assembler->revisionNotes($context, $map, $scope, $draft, $placed), $draft];
    }

    /**
     * A COMPACT document of any kind: every part written short (the only model calls), then as many of the bank's own
     * questions as the page target holds.
     *
     * The notes (units, activities), the diagrams (at most two) and the key terms are the pack; the questions are what fills the rest. So
     * the number of questions is found by DRAWING: the pack is assembled with n questions (deterministic, no model),
     * both copies of its PDF are measured, and the largest n whose copies are no longer than the target is kept. Both
     * copies are measured, and the answer area of a question is the same height in both, so they come out alike. The
     * document records the target and what was measured, so the source and the PDFs are known to be of one run.
     *
     * @param array<string,mixed> $options `pages` (default 5) and `measure` (required)
     * @return array{0:array<string,mixed>,1:array<string,mixed>} [document, draft]
     */
    private function compactDocument(DocumentKind $kind, DocumentWriter $writer, array $context, array $map, array $scope, array $eligible, callable $step, array $options, callable $say): array
    {
        $measure = $options['measure'] ?? null;
        if (!is_callable($measure)) {
            throw new \InvalidArgumentException('A compact pack is fitted to its page count by drawing it; a way to measure the pages is required.');
        }
        $target = max(1, (int) ($options['pages'] ?? 5));

        // Revision notes and a remedial class take their placed questions after the writing (a compact one attaches none to
        // the model's work); activities are planned and written first, and a question joins the activity that covers its concept.
        if (isset($options['draft'])) {
            $draft = $this->checkedDraft($kind, $scope, (array) $options['draft']);
            $say('write', 'Using the draft of an earlier run (no model call)');
        } else {
            $draft = $kind === DocumentKind::Activities ? $writer->write($context, $map, $scope, fn () => [], $step) : $writer->write($context, $map, $scope, [], $step);
        }
        $ordered = $this->placement->forCompact($eligible, $scope, $map['concepts']);
        $perConcept = self::COMPACT_PER_CONCEPT[$kind->value];
        $build = function (int $n) use ($kind, $context, $map, $scope, $draft, $ordered, $perConcept): array {
            $taken = $this->placement->takeCompact($ordered, $n, $scope, $perConcept);

            return match ($kind) {
                DocumentKind::RevisionNotes => $this->assembler->revisionNotes($context, $map, $scope, $draft, $taken, true),
                DocumentKind::Remedial => $this->assembler->remedial($context, $map, $scope, $draft, $taken, true),
                DocumentKind::Activities => $this->assembler->activities($context, $map, $scope, ['questions' => $this->questionsByActivity($draft['plan'], $taken)] + $draft, true),
            };
        };
        $longest = fn (array $pages) => max($pages['revision'], $pages['practice']);

        $say('fit', count($ordered) . ' bank questions can be printed whole; fitting to ' . $target . ' pages');
        $base = $measure($build(0));
        if ($longest($base) > $target) {
            throw new \RuntimeException('The notes alone come to ' . $longest($base) . ' pages (the target is ' . $target . '): the notes are too long for this pack. Shorten them (the COMPACT_* limits of the writer) and write again.');
        }

        // The largest number of questions that still fits: more questions never make the pack shorter.
        $low = 0;
        $high = count($ordered);
        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            $longest($measure($build($mid))) <= $target ? $low = $mid : $high = $mid - 1;
        }

        $document = $build($low);
        $pages = $measure($document);
        $document['compact'] = [
            'target_pages' => $target,
            'pages' => ['revision' => $pages['revision'], 'practice' => $pages['practice']],
            'questions_offered' => count($ordered),
            'questions_printed' => $low,
        ];
        $say('fit', $low . ' of ' . count($ordered) . ' questions fit: ' . $pages['revision'] . ' pages (answers shown), ' . $pages['practice'] . ' pages (answers hidden)');

        return [$document, $draft];
    }

    /**
     * A draft handed in from an earlier run must still be a draft of THIS chapter's concepts: a part for every concept in scope
     * (revision notes, remedial) or a plan that covers them (activities). Otherwise the run stops, not the document.
     *
     * @param array<int,int> $scope
     * @param array<string,mixed> $draft
     * @return array<string,mixed>
     */
    private function checkedDraft(DocumentKind $kind, array $scope, array $draft): array
    {
        $held = match ($kind) {
            DocumentKind::RevisionNotes => array_keys((array) ($draft['notes'] ?? [])),
            DocumentKind::Remedial => array_keys((array) ($draft['units'] ?? [])),
            DocumentKind::Activities => array_values(array_unique(array_merge(...array_map(fn ($a) => (array) ($a['concept_ids'] ?? []), (array) ($draft['plan'] ?? []))))),
        };
        $missing = array_diff($scope, array_map('intval', $held));
        if ($missing !== []) {
            throw new \InvalidArgumentException('The draft has nothing for concept(s) ' . implode(', ', $missing) . '; it is not a draft of this chapter.');
        }
        if ($kind === DocumentKind::Activities) {
            foreach ((array) $draft['plan'] as $a) {
                if (!isset($draft['activities'][$a['id']])) {
                    throw new \InvalidArgumentException('The draft has no written activity for ' . $a['id'] . '.');
                }
            }
        }

        return $draft;
    }

    /**
     * The quiz of a compact activities set: each placed question joins the first activity that covers its concept.
     *
     * @param array<int,array<string,mixed>> $plan the planned activities
     * @param array<int,array<int,array<string,mixed>>> $taken concept_id => questions
     * @return array<string,array<int,array<string,mixed>>> activity id => questions
     */
    private function questionsByActivity(array $plan, array $taken): array
    {
        $out = [];
        foreach ($taken as $conceptId => $questions) {
            foreach ($plan as $a) {
                if (in_array($conceptId, $a['concept_ids'], true)) {
                    foreach ($questions as $q) {
                        $out[$a['id']][] = $q;
                    }
                    break;
                }
            }
        }

        return $out;
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
