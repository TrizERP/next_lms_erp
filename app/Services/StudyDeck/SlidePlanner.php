<?php

namespace App\Services\StudyDeck;

use App\Services\StudyDeck\Contracts\Completer;

/**
 * Stage 1 of the two-call flow: Claude turns the learning map into a slide plan.
 *
 * The plan decides concept order, which concept each slide TEACHES (as opposed to
 * merely touching), which relationships are taught explicitly, where each existing
 * question goes, what visual each slide needs and which instructional pattern (if
 * any) shapes it. It writes no slide wording - that is stage 2.
 *
 * The reply is validated mechanically and, if it fails, sent back with the exact
 * problems listed. Nothing downstream runs on an invalid plan.
 */
class SlidePlanner
{
    use ExtractsJson;

    public const SLIDE_TYPES = [
        'cover', 'hook', 'objectives', 'prior_knowledge', 'concept_intro', 'concept_visual',
        'worked_example', 'relationship', 'misconception', 'scenario', 'recall', 'practice',
        'application', 'summary', 'concept_map', 'challenge', 'exit_ticket',
    ];

    /** Framing slides: no bank question may sit on them, and none teaches a concept. */
    public const FRAMING = ['cover', 'hook', 'objectives'];

    /** Slide types that are about particular concepts, and so must name them. */
    public const NEEDS_CONCEPT = ['concept_intro', 'concept_visual', 'worked_example', 'relationship', 'misconception', 'scenario', 'recall', 'practice', 'application'];

    public const VISUAL_ROLES = ['object', 'instrument', 'phenomenon', 'diagram'];

    /** Most visuals a deck may ask for: pictures earn their place or are left out. */
    public const MAX_VISUALS = 10;

    /**
     * Question-bank practice is OPTIONAL in a study deck: it is offered, never the lesson. One question
     * on a slide, and a handful in the whole deck, on the concepts where practice helps most.
     */
    public const MAX_QUESTIONS_PER_SLIDE = 1;

    public const MAX_PRACTICE = 10;

    /** Slide types that are practice rather than teaching: a few at most. */
    public const PRACTICE_SLIDES = ['recall', 'practice', 'exit_ticket'];

    /** Patterns that are interactions the system writes itself (InteractionPlanner), not bank-question shapes. */
    public const NOT_FOR_BANK = ['branching', 'image_hotspots'];

    public const MAX_TAUGHT_PER_SLIDE = 2;

    public const SYSTEM = 'You are an experienced classroom teacher planning a lesson for one textbook chapter. '
        . 'You reply with a single JSON object and nothing else. You never add facts that are not in the chapter text.';

    /** The most recent raw model replies, kept so a rejected plan can be inspected. */
    public array $replies = [];

    public function __construct(
        private readonly Completer $completer,
        private readonly H5pPatternSelector $patterns = new H5pPatternSelector(),
    ) {
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => questions
     * @return array<string,mixed> the validated plan
     */
    public function plan(array $context, array $map, array $eligible): array
    {
        $prompt = $this->prompt($context, $map, $eligible);
        [$plan, $errors] = $this->read($this->ask($prompt), $map, $eligible);

        for ($round = 1; $errors && $round <= 2; $round++) {
            $min = $map['target_slides']['min'];
            $max = $map['target_slides']['max'];
            $retry = $prompt . "\n\nYour previous plan was rejected for these reasons:\n- "
                . implode("\n- ", array_slice($errors, 0, 25))
                . "\n\nReturn a corrected, COMPLETE plan as strictly valid JSON (every { closed by }, every [ by ], no trailing commas). "
                . "Fixing one problem must not break another, so every rule still holds: "
                . "between {$min} and {$max} slides in total, slide 1 has slide_type \"cover\" and no other slide does, every concept is in some slide's "
                . 'taught_concept_ids, each question id is used at most once and at most ' . self::MAX_QUESTIONS_PER_SLIDE . ' per slide. '
                . 'If you must cut slides, merge or remove practice or review slides, never the cover.';
            [$plan, $errors] = $this->read($this->ask($retry), $map, $eligible);
        }

        if ($errors) {
            throw new \RuntimeException("Slide plan is invalid after two repair attempts:\n- " . implode("\n- ", $errors));
        }

        return $this->normalise($plan);
    }

    /**
     * Parse a reply and validate it. A reply that is not JSON is not a crash: it becomes the
     * reason for a repair round, the same as any other rejected plan.
     *
     * @return array{0:array<string,mixed>,1:array<int,string>}
     */
    private function read(string $reply, array $map, array $eligible): array
    {
        try {
            $plan = $this->tidy($this->extractJson($reply));
        } catch (\RuntimeException $e) {
            return [[], ['Your reply was not valid JSON (' . json_last_error_msg() . '). Nested objects were probably left unclosed.']];
        }

        return [$plan, $this->validate($plan, $map, $eligible)];
    }

    private function ask(string $prompt): string
    {
        return $this->replies[] = $this->completer->complete(self::SYSTEM, $prompt, 16000);
    }

    /** Labels differ in case and separators between runs ("Concept intro", "concept-intro"); the meaning does not. */
    private function tidy(array $plan): array
    {
        foreach ((array) ($plan['slides'] ?? []) as $i => $s) {
            if (isset($s['slide_type'])) {
                $plan['slides'][$i]['slide_type'] = strtolower(str_replace([' ', '-'], '_', trim((string) $s['slide_type'])));
            }
            if (isset($s['visual']['role'])) {
                $plan['slides'][$i]['visual']['role'] = strtolower(trim((string) $s['visual']['role']));
            }
        }

        return $plan;
    }

    /** @return array<int,string> */
    public function validate(array $plan, array $map, array $eligible): array
    {
        $errors = [];
        $slides = $plan['slides'] ?? null;
        if (!is_array($slides) || !$slides) {
            return ['"slides" is missing or empty.'];
        }

        $min = $map['target_slides']['min'];
        $max = $map['target_slides']['max'];
        if (count($slides) < $min || count($slides) > $max) {
            $errors[] = sprintf('Plan has %d slides; it must have between %d and %d.', count($slides), $min, $max);
        }

        // The PPTX renderer reads the first block as the title slide, so a cover
        // must open the deck and must not appear again.
        foreach ($slides as $i => $s) {
            if (($s['slide_type'] ?? '') === 'cover' && $i !== 0) {
                $errors[] = 'Only slide 1 may be the cover; slide ' . ($i + 1) . ' is also a cover.';
            }
        }
        if (($slides[0]['slide_type'] ?? '') !== 'cover') {
            $errors[] = 'Slide 1 must have slide_type "cover".';
        }

        $known = array_keys($map['concepts']);
        $taught = [];
        $usedQuestions = [];
        $eligibleById = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $eligibleById[$q['id']] = $q;
            }
        }
        $titles = [];
        $visuals = 0;

        foreach ($slides as $i => $s) {
            $n = $i + 1;
            $type = $s['slide_type'] ?? '';
            if (!in_array($type, self::SLIDE_TYPES, true)) {
                $errors[] = "Slide $n: slide_type \"$type\" is not one of: " . implode(', ', self::SLIDE_TYPES);
            }
            $title = mb_strtolower(trim((string) ($s['title'] ?? '')));
            if ($title === '') {
                $errors[] = "Slide $n has no title.";
            } elseif (isset($titles[$title])) {
                $errors[] = "Slide $n repeats the title of slide {$titles[$title]}.";
            } else {
                $titles[$title] = $n;
            }
            if (trim((string) ($s['teaches'] ?? '')) === '') {
                $errors[] = "Slide $n has no \"teaches\" (what the learner should take from it).";
            }

            $ids = array_map('intval', (array) ($s['concept_ids'] ?? []));
            if ($ids === [] && in_array($type, self::NEEDS_CONCEPT, true)) {
                $errors[] = "Slide $n is a $type slide and must list the concept_ids it is about (at least one).";
            }
            if ($i > 0 && count($ids) > 3) {
                $errors[] = "Slide $n carries more than 3 concepts; split it.";
            }
            foreach ($ids as $cid) {
                if (!in_array($cid, $known, true)) {
                    $errors[] = "Slide $n names concept id $cid, which is not in this chapter.";
                }
            }

            $t = array_map('intval', (array) ($s['taught_concept_ids'] ?? []));
            if (count($t) > self::MAX_TAUGHT_PER_SLIDE) {
                $errors[] = "Slide $n teaches " . count($t) . ' concepts; at most ' . self::MAX_TAUGHT_PER_SLIDE . ' so each gets a real explanation.';
            }
            if (in_array($type, self::FRAMING, true) && $t) {
                $errors[] = "Slide $n is a $type slide and must not claim to teach a concept; leave taught_concept_ids empty.";
            }
            foreach ($t as $cid) {
                if (!in_array($cid, $ids, true)) {
                    $errors[] = "Slide $n teaches concept $cid but does not list it in concept_ids.";
                } else {
                    $taught[$cid] = $n;
                }
            }

            $qids = array_map('intval', (array) ($s['question_ids'] ?? []));
            if (count($qids) > self::MAX_QUESTIONS_PER_SLIDE) {
                $errors[] = "Slide $n has " . count($qids) . ' questions; the limit is ' . self::MAX_QUESTIONS_PER_SLIDE . '. Spread them over practice slides.';
            }
            if ($qids && in_array($type, self::FRAMING, true)) {
                $errors[] = "Slide $n is a $type slide; no bank question may sit on it.";
            }
            foreach ($qids as $qid) {
                if (!isset($eligibleById[$qid])) {
                    $errors[] = "Slide $n uses question $qid, which is not in the eligible list.";
                } elseif (isset($usedQuestions[$qid])) {
                    $errors[] = "Question $qid is used on slides {$usedQuestions[$qid]} and $n.";
                } else {
                    $usedQuestions[$qid] = $n;
                }
            }

            $p = $s['h5p_pattern'] ?? null;
            if ($p !== null) {
                if (!is_array($p) || !$this->patterns->isValid($p['type'] ?? null) || trim((string) ($p['reason'] ?? '')) === '') {
                    $errors[] = "Slide $n: h5p_pattern needs a valid type (" . implode(', ', $this->patterns->ids()) . ') and a reason, or must be null.';
                } elseif (in_array($p['type'] ?? '', self::NOT_FOR_BANK, true)) {
                    $errors[] = "Slide $n: h5p_pattern \"{$p['type']}\" is not a way to ask a bank question; scenarios and hotspots are planned separately. Use null or another pattern.";
                }
            }

            $v = $s['visual'] ?? null;
            if (is_array($v) && !empty($v['required'])) {
                $visuals++;
                if (!in_array($v['role'] ?? '', self::VISUAL_ROLES, true)) {
                    $errors[] = "Slide $n: a visual needs a role, one of " . implode(', ', self::VISUAL_ROLES) . '.';
                }
                if (trim((string) ($v['query'] ?? '')) === '' || trim((string) ($v['purpose'] ?? '')) === '') {
                    $errors[] = "Slide $n: a required visual needs a search query and a purpose.";
                }
            }
            if (!empty($s["diagram"])) {
                if (!is_array($v) || empty($v["required"])) {
                    $errors[] = "Slide $n: a diagram needs a visual alongside it (role diagram, or a photo role it can fall back from).";
                }
                foreach (DiagramRenderer::problems((array) $s["diagram"]) as $problem) {
                    $errors[] = "Slide $n: diagram $problem.";
                }
            }
        }

        if (count($usedQuestions) > self::MAX_PRACTICE) {
            $errors[] = 'The plan places ' . count($usedQuestions) . ' bank questions; practice is optional, so place at most ' . self::MAX_PRACTICE . ' in the whole deck.';
        }
        $practiceSlides = count(array_filter($slides, fn ($s) => in_array($s['slide_type'] ?? '', self::PRACTICE_SLIDES, true)));
        if ($practiceSlides > 3) {
            $errors[] = "The plan has $practiceSlides recall/practice/exit_ticket slides; at most 3. Teach, show and discuss instead.";
        }
        if ($visuals > self::MAX_VISUALS) {
            $errors[] = "The plan asks for $visuals visuals; ask for at most " . self::MAX_VISUALS . ', only where a picture or diagram truly teaches.';
        }

        $missing = array_diff($known, array_keys($taught));
        if ($missing) {
            $names = array_map(fn ($id) => $map['concepts'][$id]['name'] . " ($id)", $missing);
            $errors[] = 'Concepts that no slide teaches (taught_concept_ids): ' . implode('; ', $names);
        }

        return $errors;
    }

    /** @return array<string,mixed> */
    private function normalise(array $plan): array
    {
        foreach ($plan['slides'] as $i => &$s) {
            $s['n'] = $i + 1;
            $s['concept_ids'] = array_values(array_unique(array_map('intval', (array) ($s['concept_ids'] ?? []))));
            $s['taught_concept_ids'] = array_values(array_unique(array_map('intval', (array) ($s['taught_concept_ids'] ?? []))));
            $s['question_ids'] = array_values(array_map('intval', (array) ($s['question_ids'] ?? [])));
            $s['h5p_pattern'] = $s['h5p_pattern'] ?? null;
            $s['visual'] = !empty($s['visual']['required']) ? $s['visual'] : null;
            $s['diagram'] = $s['visual'] !== null && is_array($s['diagram'] ?? null) && !DiagramRenderer::problems($s['diagram']) ? $s['diagram'] : null;
        }

        return $plan;
    }

    private function prompt(array $context, array $map, array $eligible): string
    {
        $concepts = [];
        foreach ($map['concepts'] as $id => $c) {
            $concepts[] = [
                'id' => $id,
                'name' => $c['name'],
                'topic_id' => $c['topic_id'],
                'definition' => $c['definition'],
                'difficulty' => $c['difficulty'],
                'objectives' => $c['objectives'],
                'misconceptions' => array_column($c['misconceptions'], 'wrong_idea'),
                'requires' => $c['requires'],
                'related' => $c['related'],
                'pattern_candidates' => $c['pattern_candidates'],
                'questions' => array_map(fn ($q) => [
                    'id' => $q['id'], 'form' => $q['form'], 'bloom' => $q['bloom'], 'difficulty' => $q['difficulty'],
                    'stem' => mb_substr(trim(strip_tags($q['stem'])), 0, 160),
                ], $eligible[$id] ?? []),
            ];
        }

        $input = json_encode([
            'chapter' => $context['chapter']['chapter_name'],
            'class' => $context['chapter']['standard_name'] . ' ' . $context['chapter']['subject_name'],
            'topics_in_order' => $map['topics'],
            'concepts' => $concepts,
            'existing_ai_lesson' => array_map(fn ($b) => ['category' => $b['category'], 'excerpt' => mb_substr($b['text'], 0, 2500)], $context['baseline']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $patterns = json_encode(array_values(array_filter($this->patterns->catalogue(), fn ($p) => !in_array($p['id'], self::NOT_FOR_BANK, true))), JSON_UNESCAPED_UNICODE);
        $types = implode(', ', self::SLIDE_TYPES);
        $min = $map['target_slides']['min'];
        $max = $map['target_slides']['max'];
        $count = count($concepts);
        $maxQ = self::MAX_QUESTIONS_PER_SLIDE;
        $maxP = self::MAX_PRACTICE;
        $maxScenario = InteractionPlanner::MAX_SCENARIOS;
        $maxT = self::MAX_TAUGHT_PER_SLIDE;
        $maxV = self::MAX_VISUALS;
        $roles = implode(', ', self::VISUAL_ROLES);
        $framing = implode(', ', self::FRAMING);

        return <<<PROMPT
Plan a classroom study presentation of between {$min} and {$max} slides for the chapter below. Nothing is written yet; you are only planning.

This is a TEACHER-LED CLASSROOM LESSON that students also study on their own, like a digital textbook: explain, show a large picture or diagram, explore it, work an example, discuss, then move on. It is not a quiz. WHAT TO TEACH comes from the chapter text and the concept data. HOW TO TEACH comes from concept relationships and what each concept IS. Practice from the listed bank questions is an optional extra. The "existing_ai_lesson" is a baseline to IMPROVE: find where it is weak, repetitive, thin or badly ordered, and do better; do not copy its structure.

Rules
- There are {$count} concepts. EVERY concept must be TAUGHT: list it in "taught_concept_ids" of the slide that explains it, and in that slide's "concept_ids". A concept that is only mentioned, summarised or named in a list is not taught. At most {$maxT} taught concepts per slide; combine closely related concepts only where that teaches better. Do not pad to reach the count.
- Respect the order given in topics_in_order unless a concept's "requires" says otherwise. When one concept helps explain another, plan a slide (slide_type "relationship") that makes the link explicit and fill "relationship".
- Frame the lesson: a hook and objectives at the start; a concept map or summary at the end. Use recall, practice and exit_ticket slides sparingly (at most 3 in all): the lesson is made of teaching, seeing, discussing and doing, not of questions. The {$framing} slides teach no concept (empty taught_concept_ids) and carry no bank question.
- Bank questions are OPTIONAL PRACTICE. Place at most {$maxP} in the whole deck, at most {$maxQ} per slide, each at most once, and only where practising that concept really helps (a term to recall, a misconception to confront, a skill to apply). Most concepts will have none, and that is right. Put one on the slide that follows the teaching of its concept, never on a framing slide. Prefer a question that can be asked as flashcards, true/false, fill in the blanks or matching over a plain multiple-choice one. Do not invent question ids.
- Where the chapter shows a real SITUATION with a decision or a cause-and-effect (an experiment to design, a choice between approaches, an everyday problem to reason through), plan up to {$maxScenario} slides of type "scenario" or "application" for it; the system will write a short branching scenario for them. Only where the chapter text supports it; never invent a situation.
- Visuals: at most {$maxV} in the whole deck, and only where seeing the thing teaches the concept. Give "role": one of {$roles}. A PHOTOGRAPH (role object, instrument or phenomenon) is for a concrete thing worth seeing in itself: an instrument, a natural phenomenon, a specimen. A photo of something merely MENTIONED as an example, or of anything for an abstract idea, a definition, a classification or a chain of reasoning, teaches nothing: leave "visual" null, or give role "diagram" and put a "diagram" spec on the SLIDE (a sibling of "visual", never inside it) which the system will draw. Prefer a drawn diagram (role "diagram") for any concept with several meaningful named parts, ways, steps or kinds: the student will be able to select each part of it and read an explanation, so such a diagram earns a slide of its own and a large size. A diagram spec is {"layout": "flow" | "compare" | "hub", "title": "...", ...}: flow has "nodes": 2 to 6 short labels in order; hub has "center" and "nodes": 2 to 6 labels; compare has "left" and "right", each {"heading", "items": 1 to 5}. Every label must use words and numbers from the chapter text. The query is a plain 2 to 5 word search phrase naming the thing itself (for example "magnetic compass"), never a generic classroom or student photo.
- Choose an h5p_pattern only where it truly fits the concept and a question you placed on THAT slide (reason required); otherwise null. Patterns here are ways to ask a bank question: {$patterns}. flashcards needs a recall-level question; fill_blanks needs a short factual answer; true_false needs a true/false question; matching needs a match-the-following question; drag_drop needs a drag-and-drop question. If the slide has no question, or none of its questions fit, use null. Never use branching or image_hotspots here; hotspots and scenarios are written separately by the system.
- Slide types allowed: {$types}
- No slide may repeat another's title. Every slide has one clear idea and something to look at, think about or discuss.

Reply with this JSON and nothing else:
{
  "teaching_strategy": "two or three sentences",
  "baseline_review": {"weaknesses": ["..."], "improvements": ["..."]},
  "slides": [
    {
      "section": "Introduction | Topic name | Connections | Practice | Review",
      "slide_type": "one of the allowed types",
      "title": "short, sentence case",
      "teaches": "what the learner takes away, one sentence",
      "concept_ids": [ids from the list],
      "taught_concept_ids": [ids this slide explains, at most {$maxT}],
      "relationship": null or {"from": id, "to": id, "kind": "depends_on | builds_on | contrasts_with | related_to", "idea": "how one helps with the other"},
      "visual": null or {"required": true, "role": "object | instrument | phenomenon | diagram", "query": "search phrase", "purpose": "what the picture or diagram teaches"},
      "diagram": null or {"layout": "flow | hub | compare", "title": "...", "nodes": [...]} (see the diagram rules; only with a visual),
      "question_ids": [ids from the list, at most {$maxQ}],
      "h5p_pattern": null or {"type": "pattern id", "reason": "why it fits this concept"},
      "uses_baseline": "kept | improved | new"
    }
  ]
}

CHAPTER DATA
{$input}

CHAPTER TEXT (the only source of facts)
{$context['ground_truth']}
PROMPT;
    }
}
