<?php

namespace App\Services\StudyDeck;

/**
 * What a deck made for a staged pedagogical workflow must show, checked mechanically. Pure: no database, no model.
 *
 * Used twice with the same rules: by SlidePlanner::validate (a failure goes back to the planner as a controlled repair) and by
 * DeckValidator on the finished deck (a failure fails the run). With no workflow (every chapter outside the pilot) every function
 * returns "nothing to check" and no prompt, plan or deck changes.
 *
 * The workflow is the context PedagogicalWorkflow::generationContext() builds: `stages` (number, name, ...) and optional
 * `outcomes` (ref, type, code, description) read from the school's stored curriculum. Outcomes are never invented: with none
 * stored, outcome coverage is reported as not checked.
 *
 * A slide is a plan slide or a deck slide: it carries `n`, `slide_type`, `concept_ids`, `workflow_stage`, `outcome_refs`.
 */
class WorkflowRules
{
    private const MAX_ERRORS = 12;

    public static function active(?array $workflow): bool
    {
        return !empty($workflow['stages']);
    }

    /** @return array<int,array<string,mixed>> */
    public static function outcomes(?array $workflow): array
    {
        return array_values((array) ($workflow['outcomes'] ?? []));
    }

    /**
     * The extra lines of the planner's reply template for the workflow's fields ('' without a workflow, so other prompts do not change).
     *
     * @param array<string,mixed>|null $workflow
     */
    public static function schemaLines(?array $workflow): string
    {
        if (!self::active($workflow)) {
            return '';
        }
        $n = count((array) $workflow['stages']);
        $lines = ",\n      \"workflow_stage\": a whole number from 1 to $n naming the workflow stage this slide belongs to (omit on the cover)";
        if (self::outcomes($workflow) !== []) {
            $lines .= ",\n      \"outcome_refs\": [refs, copied exactly from the LEARNING OUTCOMES AND COMPETENCIES list, of the ones this slide genuinely helps achieve; [] if none]";
        }

        return $lines;
    }

    /** "2, 3, 4, 7" as "2-4, 7" so one line can name many slides. @param array<int,int|string> $numbers */
    private static function ranges(array $numbers): string
    {
        $n = array_values(array_unique(array_map('intval', $numbers)));
        sort($n);
        $out = [];
        for ($i = 0; $i < count($n); $i++) {
            $j = $i;
            while (isset($n[$j + 1]) && $n[$j + 1] === $n[$j] + 1) {
                $j++;
            }
            $out[] = $j > $i ? "{$n[$i]}-{$n[$j]}" : (string) $n[$i];
            $i = $j;
        }

        return implode(', ', $out);
    }

    /**
     * @param array<int,array<string,mixed>> $slides
     * @param array<string,mixed> $map the learning map (concept => topic_id)
     * @return array<int,string>
     */
    public static function errors(?array $workflow, array $slides, array $map): array
    {
        if (!self::active($workflow)) {
            return [];
        }
        $stages = array_column((array) $workflow['stages'], 'name', 'number');
        $count = count($stages);
        $errors = [];
        $seen = [];
        $noStage = [];
        $lastTopic = null;
        $lastStage = 0;

        foreach ($slides as $i => $s) {
            if (($s['slide_type'] ?? '') === 'cover') {
                continue;
            }
            // A plan slide has no "n" until it is normalised; its position is its number.
            $n = $s['n'] ?? ($i + 1);
            $stage = self::stageOf($s);
            if ($stage === null || $stage < 1 || $stage > $count) {
                $noStage[] = $n;
                continue;
            }
            $seen[$stage] = true;

            // Order: the stage sequence may start again in each topic, but inside one topic it never goes backwards.
            $first = (array) ($s['concept_ids'] ?? []);
            $topic = $first ? ($map['concepts'][(int) $first[0]]['topic_id'] ?? null) : null;
            if ($topic !== null) {
                if ($topic !== $lastTopic) {
                    $lastTopic = $topic;
                    $lastStage = 0;
                }
                if ($stage < $lastStage) {
                    $errors[] = "Slide $n is stage $stage ({$stages[$stage]}) but follows stage $lastStage ({$stages[$lastStage]}) in the same topic; stages keep their order within a topic.";
                }
                $lastStage = max($lastStage, $stage);
            }
        }
        if ($noStage !== []) {
            // One line for all of them, so a plan that left the field out does not crowd every other error out of the repair feedback.
            array_unshift($errors, 'Slide' . (count($noStage) > 1 ? 's ' : ' ') . self::ranges($noStage) . " ha" . (count($noStage) > 1 ? 've' : 's') . " no valid \"workflow_stage\": every slide except the cover needs one, a whole number from 1 to $count naming the workflow stage it belongs to.");
        }
        foreach ($stages as $number => $name) {
            if (!isset($seen[$number])) {
                $errors[] = "Workflow stage $number ($name) has no slide; every stage must appear.";
            }
        }

        $outcomes = self::outcomes($workflow);
        if ($outcomes !== []) {
            $known = array_column($outcomes, 'ref');
            $covered = [];
            foreach ($slides as $i => $s) {
                foreach (self::refsOf($s) as $ref) {
                    if (!in_array($ref, $known, true)) {
                        $errors[] = 'Slide ' . ($s['n'] ?? ($i + 1)) . " lists outcome_refs \"$ref\", which is not one of the listed outcomes or competencies.";
                    } else {
                        $covered[$ref] = true;
                    }
                }
            }
            $missing = array_values(array_diff($known, array_keys($covered)));
            if ($missing) {
                $errors[] = 'Learning outcomes and competencies that no slide addresses (outcome_refs): ' . implode(', ', $missing) . '.';
            }
        }

        return array_slice(array_values(array_unique($errors)), 0, self::MAX_ERRORS);
    }

    /**
     * The slides with each topic's stretch put into stage order, which is what errors() asks of them ("inside one topic it never goes
     * backwards"). Only slides next to each other that share a topic are reordered, and only by their stage; ties keep their order. A slide
     * with no concept or no valid stage stays where it is and ends the stretch, so nothing moves across it.
     *
     * @param array<int,array<string,mixed>> $slides
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @return array<int,array<string,mixed>>
     */
    public static function inStageOrder(array $slides, array $map): array
    {
        $out = [];
        $run = [];
        $topic = null;
        $flush = static function () use (&$out, &$run): void {
            usort($run, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            foreach ($run as $entry) {
                $out[] = $entry[2];
            }
            $run = [];
        };
        foreach (array_values($slides) as $i => $s) {
            $stage = is_array($s) ? self::stageOf($s) : null;
            $first = is_array($s) ? (array) ($s['concept_ids'] ?? []) : [];
            $own = $first ? ($map['concepts'][(int) $first[0]]['topic_id'] ?? null) : null;
            if ($stage === null || $own === null || ($s['slide_type'] ?? '') === 'cover') {
                $flush();
                $out[] = $s;
                $topic = null;
                continue;
            }
            if ($own !== $topic) {
                $flush();
                $topic = $own;
            }
            $run[] = [$stage, $i, $s];
        }
        $flush();

        return $out;
    }

    /**
     * The workflow stages (numbers) that no slide carries, in order. The cover never carries one.
     *
     * @param array<int,array<string,mixed>> $slides
     * @return array<int,int>
     */
    public static function missingStages(?array $workflow, array $slides): array
    {
        if (!self::active($workflow)) {
            return [];
        }
        $have = [];
        foreach ($slides as $s) {
            if (is_array($s) && ($s['slide_type'] ?? '') !== 'cover' && ($stage = self::stageOf($s)) !== null) {
                $have[$stage] = true;
            }
        }
        $missing = array_values(array_filter(array_map('intval', array_keys(array_column((array) $workflow['stages'], 'name', 'number'))), fn ($n) => !isset($have[$n])));
        sort($missing);

        return $missing;
    }

    /**
     * The slide types that clearly carry a stage, judged by the stage's NAME, or null when the name says nothing certain (then no slide is given
     * the stage in code, and the model is asked as before). A stage is not a slide type, so this is deliberately short: only types whose job is
     * the stage's job. A concept explanation never stands in for a reflection.
     *
     * @return array<int,string>|null
     */
    public static function typesFittingStage(string $stageName): ?array
    {
        $name = mb_strtolower($stageName);
        foreach ([
            'reflect' => ['challenge', 'scenario', 'relationship', 'summary', 'misconception'],
            'experiment' => ['scenario', 'challenge', 'worked_example'],
            'application' => ['application', 'scenario', 'challenge'],
        ] as $word => $types) {
            if (str_contains($name, $word)) {
                return $types;
            }
        }

        return null;
    }

    /**
     * Slides that could carry a stage the plan left out, best first, as [position, topic] pairs. A slide qualifies only when
     *   - it has a topic and a valid stage of its own, and is not the cover;
     *   - its own stage appears at least twice in its topic, so the topic keeps that stage (a topic is never left without its explanation);
     *   - giving it the stage keeps the order inside the topic (the slide before it is not a later stage, the one after not an earlier one).
     * The nearest stage in the method's own sequence comes first (a Conceptualization slide before an Observation one for Reflection), then
     * the earlier slide. The caller still checks the whole plan afterwards.
     *
     * @param array<int,array<string,mixed>> $slides
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @return array<int,array{0:int,1:mixed}>
     */
    public static function stageCandidates(array $slides, array $map, int $stage, ?array $types = null): array
    {
        $slides = array_values($slides);
        $topicOf = static function ($s) use ($map) {
            $first = is_array($s) ? (array) ($s['concept_ids'] ?? []) : [];

            return $first ? ($map['concepts'][(int) $first[0]]['topic_id'] ?? null) : null;
        };
        $stageOf = static fn ($s) => is_array($s) && ($s['slide_type'] ?? '') !== 'cover' ? self::stageOf($s) : null;
        $inTopic = [];
        foreach ($slides as $s) {
            if (($t = $topicOf($s)) !== null && ($st = $stageOf($s)) !== null) {
                $inTopic[$t][$st] = ($inTopic[$t][$st] ?? 0) + 1;
            }
        }
        $found = [];
        foreach ($slides as $i => $s) {
            $topic = $topicOf($s);
            $own = $stageOf($s);
            if ($topic === null || $own === null || $own === $stage || ($inTopic[$topic][$own] ?? 0) < 2) {
                continue;
            }
            // Only a slide whose own type fits the stage may take it (see typesFittingStage); null = any type.
            if ($types !== null && !in_array($s['slide_type'] ?? '', $types, true)) {
                continue;
            }
            $before = $i > 0 && $topicOf($slides[$i - 1]) === $topic ? $stageOf($slides[$i - 1]) : null;
            $after = isset($slides[$i + 1]) && $topicOf($slides[$i + 1]) === $topic ? $stageOf($slides[$i + 1]) : null;
            if (($before !== null && $before > $stage) || ($after !== null && $after < $stage)) {
                continue;
            }
            $found[] = [abs($own - $stage), $i, $topic];
        }
        usort($found, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(fn ($f) => [$f[1], $f[2]], $found);
    }

    /**
     * The competencies whose OWN ref no slide lists, although a slide lists one of their learning outcomes (code "<competency>-LO-<n>"):
     * that slide develops the competency, so the competency's ref is added to the first such slide's outcome_refs. This keeps the rule that
     * a competency needs its own ref (it is not a heading over its outcomes); it only stops a plan being rejected for leaving out a ref the
     * slide's own outcome already implies. A competency none of whose outcomes any slide lists is left for the model.
     *
     * @param array<int,array<string,mixed>> $slides
     * @return array{0:array<int,array<string,mixed>>,1:array<string,int>} the slides, and competency ref => position of the slide it was added to
     */
    public static function withCompetencyRefs(?array $workflow, array $slides): array
    {
        $slides = array_values($slides);
        $competencies = array_column(array_filter(self::outcomes($workflow), fn ($o) => ($o['type'] ?? '') === 'competency'), 'ref');
        if ($competencies === []) {
            return [$slides, []];
        }
        $listed = [];
        foreach ($slides as $s) {
            foreach (is_array($s) ? self::refsOf($s) : [] as $ref) {
                $listed[$ref] = true;
            }
        }
        $added = [];
        foreach ($competencies as $competency) {
            if (isset($listed[$competency])) {
                continue;
            }
            foreach ($slides as $i => $s) {
                if (!is_array($s)) {
                    continue;
                }
                foreach (self::refsOf($s) as $ref) {
                    if (str_starts_with($ref, $competency . '-LO-')) {
                        $slides[$i]['outcome_refs'] = array_values(array_unique(array_merge((array) ($s['outcome_refs'] ?? []), [$competency])));
                        $added[$competency] = $i;
                        continue 3;
                    }
                }
            }
        }

        return [$slides, $added];
    }

    /**
     * Which slides carry each stage and each outcome, and what is missing. Facts counted from the slides.
     *
     * @param array<int,array<string,mixed>> $slides
     * @return array{stages:array<int,array<int,mixed>>,outcomes:array<string,array<int,mixed>>,missing_outcomes:array<int,string>,outcomes_checked:bool}
     */
    public static function coverage(?array $workflow, array $slides): array
    {
        $stages = [];
        $byOutcome = [];
        foreach ($slides as $s) {
            if (($stage = self::stageOf($s)) !== null) {
                $stages[$stage][] = $s['n'] ?? null;
            }
            foreach (self::refsOf($s) as $ref) {
                $byOutcome[$ref][] = $s['n'] ?? null;
            }
        }
        ksort($stages);
        $known = array_column(self::outcomes($workflow), 'ref');
        $outcomes = [];
        foreach ($known as $ref) {
            $outcomes[$ref] = $byOutcome[$ref] ?? [];
        }

        return [
            'stages' => $stages,
            'outcomes' => $outcomes,
            'missing_outcomes' => array_values(array_filter($known, fn ($r) => $outcomes[$r] === [])),
            'outcomes_checked' => $known !== [],
        ];
    }

    /**
     * The honest statement of what was checked. `validated` is true only when every rule passed on these slides.
     *
     * @param array<int,array<string,mixed>> $slides
     * @return array<string,mixed>
     */
    public static function summary(?array $workflow, array $slides, array $map): array
    {
        $errors = self::errors($workflow, $slides, $map);

        return ['validated' => $errors === [], 'errors' => $errors, 'stage_count' => count((array) ($workflow['stages'] ?? []))]
            + self::coverage($workflow, $slides);
    }

    /** @param array<string,mixed> $slide */
    public static function stageOf(array $slide): ?int
    {
        $v = $slide['workflow_stage'] ?? null;

        return is_int($v) || (is_string($v) && ctype_digit($v)) ? (int) $v : null;
    }

    /** @param array<string,mixed> $slide @return array<int,string> */
    public static function refsOf(array $slide): array
    {
        $refs = [];
        foreach ((array) ($slide['outcome_refs'] ?? []) as $ref) {
            if (is_scalar($ref) && trim((string) $ref) !== '') {
                $refs[trim((string) $ref)] = true;
            }
        }

        return array_keys($refs);
    }
}
