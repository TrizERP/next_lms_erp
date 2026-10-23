<?php

namespace App\Services\StudyDeck\Story;

use Illuminate\Support\Facades\DB;

/**
 * The reusable "Story-Based Branching Scenario" template: what it is, what a lesson made with it must contain, and how it is
 * kept in the existing Template Management (`template_master`).
 *
 * WHAT THE TEMPLATE IS. HTML/CSS stored in `template_master` does NOT change the student's React player, so this is stated
 * rather than assumed. The template is three things held together:
 *   1. a DESIGN CONFIGURATION (this class's defaults, optionally overridden by the stored row): the story parts a lesson needs,
 *      its size budget per act and its scene wording limits. It is what the validator (StoryGraph) enforces;
 *   2. an EXPLICIT BINDING to the player: a lesson made with it carries `interaction.template = "story_branching_scenario"`, and
 *      the Study Deck V3 player (components/study-deck/StoryView.tsx, chosen in SlideView.tsx) draws exactly those slides as
 *      story scenes, decisions and endings. Nothing else selects that layout;
 *   3. a catalogue entry in Template Management (`template_master`, module `study_deck`): a design document holding the
 *      configuration (`data-config`) and the print markup the static PowerPoint/PDF fallback follows. Editing its configuration
 *      there changes the budget and wording limits the validator applies to new lessons; it cannot change the player's look.
 *
 * It is independent of any chapter: the story, concepts, images and branches are supplied per chapter by the generation step.
 */
final class StoryTemplate
{
    public const KEY = 'story_branching_scenario';

    public const NAME = 'Story-Based Branching Scenario';

    public const MODULE = 'study_deck';

    /** Version 2: rich scenes (situation, observation, a concept explanation), three plausible choices, scene variants at merges, pictures. */
    public const VERSION = 2;

    /**
     * How a stored story lesson is told apart from a Classroom Presentation, so publishing one can never touch the other: its own
     * file-name prefix and the content category the library already uses for interactive items.
     */
    public const FILE_PREFIX = 'study_story_';

    public const CATEGORY = 'Interactive';

    /** True for a deck whose lesson is made of story acts (any slide's interaction names this template). */
    public static function isStory(array $deck): bool
    {
        foreach ((array) ($deck['slides'] ?? []) as $s) {
            if (($s['interaction']['template'] ?? null) === self::KEY) {
                return true;
            }
        }

        return false;
    }

    /**
     * How a teaching method shapes the STORY (not the slide sequence): the sentence added to the planner's and writer's prompts. The
     * method is applied to the story itself, so a lesson for "experiential" lets the learner act before the story explains.
     */
    public static function methodRule(string $key): ?string
    {
        return [
            'explicit_instruction' => 'Explain first: each scene states the idea clearly, then a short example in the story shows it at work, and decisions apply what was just explained.',
            'inquiry_based' => 'Start from a question: the story poses something to find out, the learner investigates and predicts, and the scenes then explain what the evidence showed.',
            'problem_based' => 'Start from a problem: a character has a real problem to solve, and the ideas are introduced as the tools the problem needs.',
            'project_based' => 'The story is a small project with a goal; each act is a stage of the project, and the learner makes the decisions a project team would make.',
            'experiential' => 'Learning by doing: the learner acts first (observes, predicts, tries something) and the scene afterwards explains what the action showed.',
            'collaborative' => 'Characters work in a small group: scenes show them sharing and comparing ideas, and decisions are what the group should do together.',
            'socratic' => 'A character keeps asking a guiding question: scenes lead the learner to the idea through what they notice, and explain it only after they have reasoned about it.',
            'flipped_classroom' => 'Open each act with a short statement of the idea, then spend the act on using and testing it in situations.',
            'differentiated_mastery' => 'Offer a way back: a less sound choice leads to a scene that rebuilds the idea step by step before the story goes on, so every learner reaches the same understanding.',
            'gamified' => 'The story has a quest feel: each act is a level with a clear aim, and the ending says how well the quest went and what to try next.',
        ][$key] ?? null;
    }

    /** Where the template is read from when no row exists. */
    public const BUILT_IN = 'built_in';

    public const STORED = 'template_master';

    /** The platform library's school: its row is the shared (central) copy; a school's own row, if it has one, wins. */
    public const PLATFORM_TENANT = 1;

    /**
     * The design configuration. Overridable keys (nodes, decisions, endings, scene_words, decision_share_max, merge_required)
     * are the only ones a stored row may change.
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            'key' => self::KEY,
            'version' => self::VERSION,
            'name' => self::NAME,
            'player' => 'study-deck-v3',
            'interaction' => 'scenario',
            'schema' => 'story/2',
            // The parts of a story act, in the order a learner meets them.
            'parts' => ['intro', 'teaching_scene', 'decision', 'consequence', 'merge', 'ending', 'mission'],
            // Per act (one act per topic, plus the closing mission): scenes, decisions and endings it may hold.
            'nodes' => [8, 20],
            'decisions' => [2, 5],
            // Different paths end differently: an act has two or three endings.
            'endings' => [2, 3],
            // Words of a story scene's narration (the situation and what a character notices) and an ending's text.
            'scene_words' => [45, 170],
            // Words of the plain-language explanation of a concept that a teaching scene carries.
            'explain_words' => [25, 110],
            // Words of a decision's choice, and of the story event and explanation that follow it.
            'choice_words' => [3, 28],
            'event_words' => [10, 60],
            // Words of a merge scene's variant: the few sentences that tie it to the branch the learner came through.
            'variant_words' => [15, 90],
            // At least this many decisions of an act offer three or more plausible approaches (not a right and a wrong answer).
            'three_choice_min' => 2,
            // Pictures an act may carry: a scene's main picture and up to two supporting ones (a photograph, a close-up, a drawn diagram).
            'images' => [0, 10],
            // When true (a run that asks for it), every scene and ending must carry its own picture and no reachable decision may render
            // without one (a decision shows the latest picture on the learner's path). Off here so a story without pictures stays valid.
            'image_coverage' => false,
            // The closing mission applies the chapter: this many concepts (one per topic where it can) must be met on every path of it.
            'mission_concepts' => 6,
            // A story is not a quiz: at most this share of an act's nodes may be decisions.
            'decision_share_max' => 0.45,
            // An act with two or more decisions must bring its branches back together at least once.
            'merge_required' => true,
            // Colours follow the topic family the player already gives every slide (lib/study-deck/theme.ts).
            'theme' => 'topic',
        ];
    }

    /** The settings a stored row may override, with how each is checked. */
    private const OVERRIDABLE = ['nodes', 'decisions', 'endings', 'scene_words', 'explain_words', 'choice_words', 'event_words', 'variant_words', 'three_choice_min', 'images', 'mission_concepts', 'decision_share_max', 'merge_required'];

    /**
     * The catalogue document stored in Template Management: a design document whose root carries the configuration, with the
     * print markup (scene, decision and ending blocks) the PPTX/PDF fallback follows.
     */
    public static function html(?array $config = null): string
    {
        $c = $config ?? self::defaults();
        $json = htmlspecialchars((string) json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<div class="story-template" data-template-key="{$c['key']}" data-version="{$c['version']}" data-player="{$c['player']}" data-schema="{$c['schema']}" data-config="{$json}">
<style>
.story-template{font-family:Inter,Arial,sans-serif;color:#1e293b}
.story-template .mission{background:#eef2ff;border-left:6px solid #4f46e5;padding:12px 16px;border-radius:8px}
.story-template .scene{border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin:10px 0}
.story-template .scene h3{margin:0 0 6px;color:#312e81}
.story-template .decision{background:#f0fdfa;border-left:6px solid #0f766e;padding:10px 14px;border-radius:8px;margin:10px 0}
.story-template .choice{margin:4px 0}
.story-template .event{color:#475569;font-style:italic}
.story-template .ending{background:#ecfdf5;border-left:6px solid #047857;padding:10px 14px;border-radius:8px;margin:10px 0}
</style>
<section class="mission" data-part="intro"><h3>{{mission.title}}</h3><p>{{mission.body}}</p></section>
<section class="scene" data-part="teaching_scene"><h3>{{scene.title}}</h3><p>{{scene.body}}</p><figure data-slot="image">{{scene.image}}</figure></section>
<section class="decision" data-part="decision"><p>{{decision.prompt}}</p><p class="choice">{{choice.text}} <span class="event">{{choice.outcome}}</span> {{choice.why}}</p></section>
<section class="scene" data-part="merge"><h3>{{merge.title}}</h3><p>{{merge.body}}</p></section>
<section class="ending" data-part="ending"><h3>{{ending.title}}</h3><p>{{ending.body}}</p></section>
</div>
HTML;
    }

    /**
     * The configuration inside a stored design document, or null when the document is not this template's.
     *
     * @return array<string,mixed>|null
     */
    public static function parse(string $html): ?array
    {
        if (!preg_match('/data-template-key="' . preg_quote(self::KEY, '/') . '"/', $html) || !preg_match('/data-config="([^"]*)"/', $html, $m)) {
            return null;
        }
        $config = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), true);

        return is_array($config) && ($config['key'] ?? null) === self::KEY ? $config : null;
    }

    /**
     * The configuration a lesson for this school is held to: the school's own Template Management row, else the platform's, else
     * the built-in default. A stored value is used only when it is well-formed; anything else falls back to the default.
     *
     * @return array{source:string,template_id:?int,config:array<string,mixed>}
     */
    public static function resolve(int $tenant): array
    {
        $default = self::defaults();
        try {
            $row = DB::table('template_master')
                ->where('module_name', self::MODULE)->where('title', self::NAME)->where('status', 1)
                ->whereIn('sub_institute_id', array_values(array_unique([$tenant, self::PLATFORM_TENANT])))
                ->orderByRaw('sub_institute_id = ? desc', [$tenant])->orderByDesc('id')->first(['id', 'html_content']);
        } catch (\Throwable) {
            $row = null;
        }
        $stored = $row ? self::parse((string) $row->html_content) : null;
        // A row written for an older version of the design holds limits that no longer fit it (shorter scenes, no explanations): it is not
        // applied, and the built-in version 2 is used until the template is seeded again.
        if ($stored !== null && (int) ($stored['version'] ?? 1) !== self::VERSION) {
            $stored = null;
        }
        if ($stored === null) {
            return ['source' => self::BUILT_IN, 'template_id' => null, 'config' => $default];
        }

        $config = $default;
        foreach (self::OVERRIDABLE as $k) {
            if (array_key_exists($k, $stored) && self::wellFormed($k, $stored[$k])) {
                $config[$k] = $stored[$k];
            }
        }

        return ['source' => self::STORED, 'template_id' => (int) $row->id, 'config' => $config];
    }

    private static function wellFormed(string $key, mixed $v): bool
    {
        return match ($key) {
            'three_choice_min' => is_int($v) && $v >= 0 && $v <= 5,
            'nodes', 'decisions', 'endings', 'scene_words', 'explain_words', 'choice_words', 'event_words', 'variant_words' => is_array($v) && count($v) === 2 && is_int($v[0]) && is_int($v[1]) && $v[0] >= 1 && $v[0] <= $v[1] && $v[1] <= 400,
            'images' => is_array($v) && count($v) === 2 && is_int($v[0]) && is_int($v[1]) && $v[0] >= 0 && $v[0] <= $v[1] && $v[1] <= 12,
            'mission_concepts' => is_int($v) && $v >= 1 && $v <= 12,
            'decision_share_max' => (is_float($v) || is_int($v)) && $v > 0 && $v <= 1,
            'merge_required' => is_bool($v),
            default => false,
        };
    }
}
