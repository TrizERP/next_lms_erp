<?php

namespace App\Services\StudyDeck\Branching;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\ExtractsJson;

/**
 * Stage 1: the skeleton.
 *
 * Asks the model for the SHAPE of a scenario -- nodes, what each is for, where
 * each choice leads -- and sends it back with the exact errors until
 * ScenarioBlueprint accepts it. See that class for why the structure is settled
 * before any prose exists.
 *
 * The model is given the chosen concepts, the paragraphs of the chapter that
 * talk about them, the bank questions it may reuse as knowledge checks, and the
 * titles of scenarios already written for the chapter. It is not given a way to
 * write a question: a check names a bank question by id and the question is
 * reproduced verbatim later.
 */
final class ScenarioPlanner
{
    use ExtractsJson;

    /** @var list<string> every raw reply, kept so a failed run can be inspected */
    public array $replies = [];

    public function __construct(private readonly Completer $completer, private readonly int $maxRepairs = 2)
    {
    }

    /**
     * @param array<string,mixed> $options length, focus, existing
     * @return array<string,mixed> the accepted skeleton
     *
     * @throws \RuntimeException when no reply passes after the repair rounds
     */
    public function plan(SourcePack $pack, array $options, ?callable $progress = null): array
    {
        $length = $pack->length;
        $prompt = $this->prompt($pack, $options);
        $errors = [];

        for ($attempt = 0; $attempt <= $this->maxRepairs; $attempt++) {
            $reply = $this->completer->complete($this->system(), $prompt, 12000);
            $this->replies[] = $reply;

            try {
                $result = ScenarioBlueprint::check($this->extractJson($reply), $pack, $length);
                $errors = $result['errors'];
            } catch (\RuntimeException $e) {
                $result = null;
                $errors = [$e->getMessage()];
            }

            if ($errors === [] && $result !== null) {
                return $result['blueprint'];
            }

            ($progress ?? fn () => null)('plan', 'Skeleton needs another pass (' . count($errors) . ' problem(s))');

            $prompt = $this->repairPrompt($pack, $options, $reply, $errors);
        }

        throw new \RuntimeException("The scenario's structure could not be planned. Problems found:\n- " . implode("\n- ", array_slice($errors, 0, 12)));
    }

    private function system(): string
    {
        return 'You design decision-based lessons (branching scenarios) for school students from the chapter material you are given. '
            . 'A learner steps into a realistic situation, makes decisions, sees the consequence of each decision explained with the ideas of the chapter, '
            . 'and reaches an ending. It is a LESSON, not a quiz: most screens teach. '
            . 'You reply with one JSON object and nothing else. You never invent facts, numbers, names or experiments that the material does not support.';
    }

    /** @param array<string,mixed> $options */
    private function prompt(SourcePack $pack, array $options): string
    {
        $budget = ScenarioBlueprint::BUDGETS[$pack->length] ?? ScenarioBlueprint::BUDGETS['standard'];
        [$nMin, $nMax] = $budget['nodes'];
        [$dMin, $dMax] = $budget['decisions'];
        [$eMin, $eMax] = $budget['endings'];

        $existing = array_values(array_filter(array_map(fn ($e) => trim((string) ($e['title'] ?? '')), (array) ($options['existing'] ?? []))));
        $focus = trim((string) ($options['focus'] ?? ''));
        $objective = trim((string) ($pack->context['learning_objective'] ?? ''));

        $out = [];
        $out[] = 'CLASS AND CHAPTER: ' . $pack->heading();
        $out[] = 'WHO IT IS FOR: ' . ScenarioPrompts::audience($pack->level());
        if ($objective !== '') {
            $out[] = 'CHAPTER LEARNING OBJECTIVE: ' . SourcePack::flat($objective);
        }
        $out[] = "CONCEPTS THE SCENARIO MUST TEACH (in teaching order; cite the number in concept_id):\n" . $pack->conceptsText();

        $excerpts = $pack->excerpts($pack->conceptIds(), 7000);
        if ($excerpts !== '') {
            $out[] = "CHAPTER TEXT (the authority for every fact):\n" . $excerpts;
        }

        $out[] = "BANK QUESTIONS YOU MAY REUSE AS KNOWLEDGE CHECKS (optional; you cannot write or change one):\n" . $pack->questionsText();

        if ($existing !== []) {
            $out[] = "ALREADY WRITTEN FOR THIS CHAPTER (choose a different situation and angle):\n- " . implode("\n- ", $existing);
        }
        if ($focus !== '') {
            $out[] = "THE TEACHER ASKED FOR THIS FOCUS: " . $focus;
        }

        $out[] = <<<TXT
SIZE: {$nMin} to {$nMax} nodes in all; {$dMin} to {$dMax} decisions; {$eMin} to {$eMax} endings; at most {$budget['questions']} question check(s).

DESIGN RULES
1. The first node is a scene with purpose "situation": a concrete, age-appropriate real-world case connected to the chapter, in which the learner will have to decide something. Everything else grows from it.
2. Teach in a logical order that follows the concepts' teaching order. Every concept above must be taught or applied by at least one node (set its concept_id).
3. Node kinds:
   - "scene": a screen of content. purpose is one of situation, explanation, example, consequence, remedial, reflection, summary. It has exactly one "next".
   - "decision": the learner chooses. purpose is investigate (choose what to look into), predict (choose what will happen), or apply (choose how to act). 2 to 4 "choices", each leading to a different node.
   - "decision" with a "question_id": a knowledge check using one of the offered bank questions. Give "on_correct" and "on_wrong" instead of "choices". Use one only straight after the screens that teach what it checks.
   - "ending": where a path stops. outcome is best, good, partial or revisit. No "next".
4. Each decision has at least one "best" choice (sound reasoning), and at least one that is not best: "partial" (reasonable but incomplete) or "misstep" (a tempting wrong idea; use the common wrong ideas listed above).
5. A "misstep" choice, and "on_wrong", must lead to a scene with purpose "consequence" or "remedial" that explains what followed and why, before the path continues or ends.
6. Include at least one "investigate" or "predict" decision, so the learner explores before being told.
7. Paths may split and rejoin. Links only go FORWARD: no node may lead back to a node that leads to it. Every node must be reachable from the first node, and every path must end at an ending.
8. At least one ending has outcome "best" and is reached by deciding soundly. Every other ending says what the path showed and what to revisit.
9. Do not make a scenario that is only questions. Most nodes are scenes that teach.
10. The title is in sentence case (only the first word and proper names capitalised) and names the situation, not the topic. "objectives" are 3 or 4 short items, each starting with a verb (Explain, Decide, Compare...) and at most 20 words.

REPLY with this JSON and nothing else ("..." marks text you write; ids are short, like "s1", "d1", "e1"):
{
  "title": "...",
  "premise": "one or two sentences: the real-world situation the learner steps into",
  "objectives": ["Explain ...", "..."],
  "nodes": [
    {"id": "s1", "kind": "scene", "purpose": "situation", "concept_id": 0, "covers": "one sentence: what this screen establishes", "next": "d1"},
    {"id": "d1", "kind": "decision", "purpose": "investigate", "concept_id": 0, "asks": "the decision, in one sentence",
     "choices": [
       {"intent": "what choosing this means", "quality": "best", "next": "s2"},
       {"intent": "...", "quality": "misstep", "next": "s3"}
     ]},
    {"id": "d2", "kind": "decision", "purpose": "check", "question_id": 0, "on_correct": "s4", "on_wrong": "s5"},
    {"id": "e1", "kind": "ending", "purpose": "summary", "concept_id": 0, "outcome": "best", "covers": "what this path shows"}
  ]
}
TXT;

        return implode("\n\n", $out);
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $errors
     */
    private function repairPrompt(SourcePack $pack, array $options, string $previous, array $errors): string
    {
        return $this->prompt($pack, $options)
            . "\n\nYOUR PREVIOUS REPLY WAS:\n" . $previous
            . "\n\nIT HAS THESE PROBLEMS:\n- " . implode("\n- ", array_slice($errors, 0, 15))
            . "\n\nReturn the COMPLETE corrected JSON object, changing only what is needed to fix these problems.";
    }
}
