<?php

namespace App\Services\StudyDeck\Story;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\ExtractsJson;

/**
 * Stage S1 of a story lesson: the story bible, then a CHECKED skeleton for each act. No prose is written here.
 *
 * Every model reply is parsed and judged by deterministic rules (StorySkeleton, which runs StoryGraph::structure()); a reply that
 * fails is sent back with the exact reasons, twice at most, and a skeleton that still fails stops the run with those reasons. Nothing
 * is repaired silently and nothing the model says is trusted before it has passed.
 */
class StoryPlanner
{
    use ExtractsJson;

    public const SYSTEM = 'You are an experienced classroom teacher and story writer planning an interactive story lesson for one textbook chapter. '
        . 'You reply with a single JSON object and nothing else. You never add facts that are not in the chapter text.';

    /** The most recent raw model replies, kept so a rejected plan can be inspected. */
    public array $replies = [];

    public function __construct(private readonly Completer $completer)
    {
    }

    /**
     * The one story the whole lesson tells: who, where, and what runs through every act.
     *
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,array<string,mixed>> $acts StoryActs::plan()
     * @return array{title:string,setting:string,premise:string,characters:array<int,array{name:string,role:string}>,act_hooks:array<string,string>}
     */
    public function bible(array $context, array $map, array $acts, ?string $method): array
    {
        $prompt = $this->biblePrompt($context, $map, $acts, $method);
        [$bible, $errors] = $this->readBible($this->ask($prompt), $acts);
        for ($round = 1; $errors && $round <= 2; $round++) {
            $retry = $prompt . "\n\nYour previous reply was rejected for these reasons:\n- " . implode("\n- ", $errors) . "\n\nReturn the corrected, COMPLETE JSON object.";
            [$bible, $errors] = $this->readBible($this->ask($retry), $acts);
        }
        if ($errors) {
            throw new \RuntimeException("The story bible is invalid after two repair attempts:\n- " . implode("\n- ", $errors));
        }

        return $bible;
    }

    /**
     * The checked skeleton of one act.
     *
     * @param array<string,mixed> $act one StoryActs::plan() entry
     * @param array<string,mixed> $bible bible()
     * @param array<string,mixed> $config StoryTemplate::resolve()['config']
     * @return array<string,mixed> StorySkeleton::check()['skeleton'], valid
     */
    public function skeleton(array $context, array $map, array $act, array $bible, array $config, ?string $method, ?array $previous = null): array
    {
        $prompt = $this->skeletonPrompt($context, $map, $act, $bible, $config, $method, $previous);
        $result = $this->withFigures(StorySkeleton::check($this->tryJson($this->ask($prompt)), $act, $config), $context, $map);
        for ($round = 1; $result['errors'] && $round <= 3; $round++) {
            $retry = $prompt . "\n\nYOUR PREVIOUS SKELETON (edit this one):\n" . ($this->replies[count($this->replies) - 1] ?? '') . "\n\nIt was rejected for these reasons:\n- " . implode("\n- ", array_slice($result['errors'], 0, 25))
                . "\n\nReturn a corrected, COMPLETE skeleton as strictly valid JSON: take your previous skeleton and change only what the reasons name. Fixing one problem must not break another: every rule above still holds, "
                . 'every path through the act must still end in an ending and teach every concept listed in required_concept_ids.';
            $result = $this->withFigures(StorySkeleton::check($this->tryJson($this->ask($retry)), $act, $config), $context, $map);
        }
        if ($result['errors']) {
            throw new \RuntimeException('The story skeleton for act "' . $act['title'] . "\" is invalid after three repair attempts:\n- " . implode("\n- ", array_slice($result['errors'], 0, 12)));
        }

        return $result['skeleton'];
    }

    /**
     * A skeleton that is otherwise valid is also held to the chapter's own figures: the writer follows the skeleton's choice wording, so an
     * invented number caught here is one that never reaches the written story.
     *
     * @param array{skeleton:array<string,mixed>,errors:array<int,string>} $result
     * @return array{skeleton:array<string,mixed>,errors:array<int,string>}
     */
    private function withFigures(array $result, array $context, array $map): array
    {
        if ($result['errors'] !== []) {
            return $result;
        }
        $allowed = StoryFigures::allowed($context, $map);
        $sk = $result['skeleton'];
        $errors = StoryFigures::errors('The premise', (string) ($sk['title'] ?? '') . ' ' . (string) ($sk['premise'] ?? ''), $allowed);
        foreach ((array) $sk['nodes'] as $n) {
            $errors = array_merge($errors, StoryFigures::errors('Node "' . $n['id'] . '"', (string) ($n['covers'] ?? '') . ' ' . (string) ($n['asks'] ?? ''), $allowed));
            foreach ((array) ($n['choices'] ?? []) as $c) {
                $errors = array_merge($errors, StoryFigures::errors('The choice "' . ($c['id'] ?? '?') . '" (' . ($c['intent'] ?? '') . ')', (string) ($c['intent'] ?? ''), $allowed));
            }
        }
        $result['errors'] = $errors;

        return $result;
    }

    private function ask(string $prompt): string
    {
        return $this->replies[] = $this->completer->complete(self::SYSTEM, $prompt, 16000);
    }

    /** @return array<string,mixed>|string a decoded object, or the reason it was not one */
    private function tryJson(string $reply): mixed
    {
        try {
            return $this->extractJson($reply);
        } catch (\RuntimeException) {
            return null; // StorySkeleton reports an empty reply as "nodes is empty"
        }
    }

    /** @return array{0:array<string,mixed>,1:array<int,string>} */
    private function readBible(string $reply, array $acts): array
    {
        $raw = $this->tryJson($reply);
        if (!is_array($raw)) {
            return [[], ['Your reply was not valid JSON.']];
        }
        $errors = [];
        $bible = [
            'title' => $this->s($raw['title'] ?? ''), 'setting' => $this->s($raw['setting'] ?? ''), 'premise' => $this->s($raw['premise'] ?? ''),
            'investigation' => $this->s($raw['investigation'] ?? ''), 'characters' => [], 'act_hooks' => [],
        ];
        foreach (['title', 'setting', 'premise', 'investigation'] as $k) {
            if ($bible[$k] === '') {
                $errors[] = "\"$k\" is missing.";
            }
        }
        foreach ((array) ($raw['characters'] ?? []) as $c) {
            $name = $this->s(is_array($c) ? ($c['name'] ?? '') : $c);
            if ($name !== '' && mb_strlen($name) <= 30) {
                $bible['characters'][] = ['name' => $name, 'role' => $this->s(is_array($c) ? ($c['role'] ?? '') : '')];
            }
        }
        if (count($bible['characters']) < 1 || count($bible['characters']) > 4) {
            $errors[] = '"characters" must name 1 to 4 characters the learner meets in every act (a name and a role each).';
        }
        foreach ($acts as $a) {
            $hook = $this->s(((array) ($raw['act_hooks'] ?? []))[$a['key']] ?? '');
            if ($hook === '') {
                $errors[] = 'act_hooks needs a sentence for act "' . $a['key'] . '" (' . $a['title'] . '): how the same story continues there.';
            }
            $bible['act_hooks'][$a['key']] = $hook;
        }

        return [$bible, $errors];
    }

    private function s(mixed $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : ''));
    }

    private function methodLine(?string $method): string
    {
        $line = $method !== null ? StoryTemplate::methodRule($method) : null;

        return $line === null ? '' : "\n- TEACHING METHOD: $line";
    }

    /** @return array<string,mixed> */
    private function conceptsFor(array $map, array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $c = $map['concepts'][$id] ?? null;
            if ($c === null) {
                continue;
            }
            $out[] = [
                'id' => (int) $id, 'name' => $c['name'], 'definition' => $c['definition'], 'objectives' => $c['objectives'] ?? [],
                'misconceptions' => array_column((array) ($c['misconceptions'] ?? []), 'wrong_idea'),
            ];
        }

        return $out;
    }

    private function biblePrompt(array $context, array $map, array $acts, ?string $method): string
    {
        $input = json_encode([
            'chapter' => $context['chapter']['chapter_name'], 'class' => $context['chapter']['standard_name'] . ' ' . $context['chapter']['subject_name'],
            'learning_objective' => $context['learning_objective'] ?? null,
            'acts' => array_map(fn ($a) => ['key' => $a['key'], 'title' => $a['title'], 'kind' => $a['kind'], 'concepts' => array_map(fn ($id) => $map['concepts'][$id]['name'], array_slice($a['concept_ids'], 0, 8))], $acts),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $keys = implode(', ', array_column($acts, 'key'));
        $methodRule = $this->methodLine($method);

        return <<<PROMPT
You are planning ONE continuous story that a student (age 14-15) plays through to learn the chapter below. The story has one act for each topic of the chapter and ends with a final mission. The same characters and the same setting run through every act, so the lesson feels like one story, not nine separate ones.

Rules
- The story is about the chapter's own subject matter. Choose a situation in which the chapter's ideas are really needed (an investigation, a project, a problem someone has to solve). Do not choose an unrelated topic.
- Realistic and school-appropriate. No emoji. No violence.
- The learner is a student in a small team with a real-world mission. Give the story ONE central question to investigate ("investigation": a concrete, answerable question that the chapter's ideas help settle, for example why two things that look alike behave differently), which every act moves forward: what the team finds in one act is what they work from in the next.
- 1 to 4 recurring characters, each with a short first name and a role (for example a student, a teacher, a family member). They have goals and opinions, and sometimes disagree.
- "act_hooks" says, for EVERY act key ({$keys}), in one sentence how the same story continues in that act and why the chapter's ideas for that topic are needed there.{$methodRule}

Reply with this JSON and nothing else:
{
  "title": "story title, sentence case",
  "setting": "where and when the story happens, one sentence",
  "premise": "what the learner is trying to achieve across the whole story, one or two sentences",
  "investigation": "the one central question the team investigates, one sentence",
  "characters": [{"name": "...", "role": "..."}],
  "act_hooks": {"a1": "...", "a2": "..."}
}

CHAPTER DATA
{$input}

CHAPTER TEXT (the only source of facts)
{$context['ground_truth']}
PROMPT;
    }

    /**
     * @param array<string,mixed>|null $previous what the previous act left behind: its title, how it ended and what the team now knows
     */
    private function skeletonPrompt(array $context, array $map, array $act, array $bible, array $config, ?string $method, ?array $previous = null): string
    {
        [$nLo, $nHi] = $config['nodes'];
        [$dLo, $dHi] = $config['decisions'];
        [$eLo, $eHi] = $config['endings'];
        [, $iHi] = $config['images'];
        $three = min((int) ($config['three_choice_min'] ?? 2), $dHi);
        $required = implode(', ', $act['required_ids']);
        $input = json_encode([
            'chapter' => $context['chapter']['chapter_name'],
            'story' => ['title' => $bible['title'], 'setting' => $bible['setting'], 'premise' => $bible['premise'], 'investigation' => $bible['investigation'] ?? '', 'characters' => $bible['characters']],
            'previous' => $previous,
            'this_act' => ['key' => $act['key'], 'title' => $act['title'], 'kind' => $act['kind'], 'hook' => $bible['act_hooks'][$act['key']] ?? '', 'topic' => $act['description']],
            'required_concept_ids' => $act['required_ids'],
            'concepts' => $this->conceptsFor($map, $act['kind'] === StoryActs::MISSION ? $act['required_ids'] : $act['concept_ids']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sceneP = implode(' | ', \App\Services\StudyDeck\Branching\ScenarioBlueprint::SCENE_PURPOSES);
        $decP = implode(' | ', StorySkeleton::DECISION_PURPOSES);
        $out = implode(' | ', \App\Services\StudyDeck\Branching\ScenarioBlueprint::OUTCOMES);
        $methodRule = $this->methodLine($method);
        $mission = $act['kind'] === StoryActs::MISSION
            ? '- This is the FINAL MISSION: one real-life situation where the learner applies the chapter. Its scenes and decisions must meet every concept in required_concept_ids on every path.'
            : '- This act teaches the concepts of ONE topic through the story. EVERY path through the act must meet EVERY concept in required_concept_ids.';

        return <<<PROMPT
Plan ONE act of the story lesson below as a branching graph. You are planning the STRUCTURE only: no story text is written yet.

This is ONE continuous investigation, told in acts. The learner is a student who is part of a small team working on the story's central question (story.investigation). What the team found and decided in earlier acts carries on: this act continues from "previous" below, and the scenes may build on what the team already knows.

Rules
{$mission}
- An act is {$nLo} to {$nHi} nodes: scenes (a narrated moment with ONE way on, "next"), decisions ({$dLo} to {$dHi} in the act) and endings ({$eLo} to {$eHi}). It is mostly SCENES: the learner reads, notices things and discovers; only sometimes decides. It is not a quiz.
- The FIRST node is a scene with purpose "situation". If "previous" is given, it follows on from where the team left off (it does not restart in a laboratory); otherwise it opens the story with a compelling real-world mission and the learner's role.
- Teach in SCENES. Each scene that teaches a concept lists it in "concept_ids" and will carry a plain-language explanation of it, told through what a character notices, tries or finds out. Every path through the act must meet every concept in required_concept_ids.
- DECISIONS are plausible approaches, not a right answer against a wrong one. At least {$three} decisions offer THREE choices; the rest offer two. Every decision has one "best" choice, at least one "partial" choice (a reasonable approach with a real cost or blind spot, for example fast but less certain, or thorough but slow) and at most one "misstep" (a mistake a student would really make). The choices read as competing hypotheses, different ways to investigate, or trade-offs. Describe what each choice IS ("repeat the measurement with a different instrument"), never whether it is good: no giveaway words (never, always, absolute, guess, blindly, untested, no need) in any intent, and the intents are similar in length and tone.
- Each choice leads to its OWN consequence scene (purpose consequence or remedial): what happens, what turns up, what the choice missed. Two choices of one decision never lead to the same scene. A choice that is not "best" never leads straight to an ending.
- Branches come BACK TOGETHER only after their consequences have been shown: a later scene is reached from several consequence scenes (a merge) and will be written to say which path the learner took. At least one merge. Different paths must also end differently: {$eLo} to {$eHi} endings with different outcomes, and EVERY ending must be reachable. Do not funnel all branches through one closing scene: the consequence scenes after the last decision (or a last decision of their own) lead to different endings, so that the learner's choices decide how the act ends.
- Nodes only move forward: a "next" must name a node LATER in the list. Every node must be reachable. Every path ends in an ending.
- Facts and figures come ONLY from the chapter text: write no number, count, measurement or quantity in "covers", "asks" or "intent" that the chapter text does not contain (say "a measurement of mass", not "25 kg"). No invented experimental results.
- PICTURES: for every scene and ending where a picture helps (the place, the apparatus, the thing being observed, the evidence, a comparison), give "image_queries": one or two search phrases of 2 to 5 words naming a concrete thing that can be photographed (for example "bicycle tyre tread", "measuring cylinder", "weather station"). No verbs, no ideas, no numbers. Use the chapter's own vocabulary. Different scenes need different things. Optionally a node may carry ONE "visual" (a diagram drawn from the chapter's own words, at most {$iHi} pictures in the act in all): {"layout":"flow|hub|compare","title":"...", "nodes":[...] / "center":"..." / "left":{"heading","items"},"right":{...}}, every label a word or phrase from the chapter text.
- SCIENTIFIC PROCESS: when a scene is a step of how science finds something out (a character observes, investigates, gathers evidence, or reaches a conclusion), give it "process_step": observation | investigation | evidence | conclusion. The scene then shows the four-step sequence Observation, Investigation, Evidence, Conclusion with that step highlighted, so across the act the learner sees the sequence move on. Use it only where the scene really is that step, at most once for each step in an act, and do not also give that node a "visual".
- Scene purposes: {$sceneP}. Decision purposes: {$decP}. Ending outcomes: {$out}.{$methodRule}

Reply with this JSON and nothing else:
{
  "title": "title of this act of the story, sentence case, specific (not a placeholder)",
  "premise": "where the story is in this act, one or two sentences",
  "nodes": [
    {"id": "s1", "kind": "scene", "purpose": "situation", "concept_ids": [], "covers": "one sentence", "next": "d1", "image_queries": ["..."]},
    {"id": "d1", "kind": "decision", "purpose": "investigate|predict|apply", "concept_ids": [ids], "asks": "the decision, one sentence",
     "choices": [{"intent": "what this approach is", "quality": "best", "next": "s2a"}, {"intent": "...", "quality": "partial", "next": "s2b"}, {"intent": "...", "quality": "partial|misstep", "next": "s2c"}]},
    {"id": "s2a", "kind": "scene", "purpose": "consequence", "concept_ids": [ids], "covers": "...", "next": "s3", "image_queries": ["..."], "visual": null},
    {"id": "e1", "kind": "ending", "outcome": "best|good|partial|revisit", "concept_ids": [ids], "covers": "what this path shows", "image_queries": ["..."]}
  ]
}

STORY SO FAR AND ACT DATA (required_concept_ids: {$required})
{$input}

CHAPTER TEXT (the only source of facts)
{$context['ground_truth']}
PROMPT;
    }
}
