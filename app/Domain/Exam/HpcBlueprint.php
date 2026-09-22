<?php

namespace App\Domain\Exam;

/**
 * The shape of an HPC blueprint's `definition`.
 *
 * A Holistic Progress Card has no marks. Where a regular blueprint answers
 * "how many marks, of what type, to which chapter", this one answers:
 *
 *   1. AREAS — what is looked at. For the Foundational Stage these are the five
 *      development domains; from the Middle Stage they are the nine curricular
 *      areas. Each carries the NCF chain the card is actually filled against:
 *      Curricular Goal -> Competency -> Learning Outcomes.
 *   2. PROFICIENCY SCALE — what a judgement can say. The scale is stored, not
 *      assumed, because it genuinely differs by stage: the Foundational card
 *      uses Beginner / Progressive / Proficient, the Middle Stage card uses
 *      Beginner / Proficient / Advanced. Hardcoding either would misreport one
 *      of them.
 *   3. ASSESSORS — whose judgement counts. An HPC is 360-degree by design: the
 *      student, a peer, the teacher and the parent each contribute, and which
 *      of them a school uses is part of the design, not a fixed rule.
 *   4. ABILITIES — the Awareness / Sensitivity / Creativity strands the Middle
 *      Stage rubric scores per activity. Named per area, because the card
 *      prefixes them by subject ("Literary Awareness").
 *   5. ACTIVITY APPROACHES and EVIDENCE — how the judgement is arrived at.
 *
 * Nothing here is validated into refusal. An HPC design is assembled over a
 * term, and a form that will not save a half-built one is a form nobody uses.
 * `validate()` reports what is missing; it never blocks.
 *
 * SCHOOL VOCABULARY. The published lists below are defaults, not limits. Every
 * normalise takes an optional `$vocabulary` of the school's own option lists
 * (HpcVocabularyService), and validates against that instead, so a school can
 * add a "Grandparent" assessor or a "Community-based" activity and have it
 * survive a save. Passing nothing falls back to the published lists, which is
 * what every caller without a tenant in hand wants.
 */
class HpcBlueprint
{
    public const VERSION = 1;

    /** The four NCF stages an HPC is published for. */
    public const STAGES = ['Foundational', 'Preparatory', 'Middle', 'Secondary'];

    /**
     * Who may record a judgement.
     *
     * The parent's contribution is real but different in kind: on the published
     * cards they fill the home-support and general-information parts rather
     * than rating a competency. Both are kept here because both are part of the
     * design a school is choosing.
     */
    public const ASSESSORS = [
        'self' => 'Student (self-reflection)',
        'peer' => 'Peer',
        'teacher' => 'Teacher',
        'parent' => 'Parent / caregiver',
    ];

    /** "Approach of the Activity" on the Middle Stage card, tick all that apply. */
    public const ACTIVITY_APPROACHES = [
        'art_integrated' => 'Art-integrated',
        'sports_integrated' => 'Sports-integrated',
        'toy_based' => 'Toy-based',
        'technology_integrated' => 'Technology-integrated',
        'skill_based' => 'Skill-based learning',
        'experiential' => 'Experiential learning',
        // Printed on the Secondary card's pedagogy list and nowhere else.
        'drama_integrated' => 'Drama/Theatre-integrated',
        'cross_cutting' => 'Cross-cutting theme integrated',
        'iks_integrated' => 'Indian Knowledge Systems integrated',
        'other' => 'Any other',
    ];

    /**
     * How the evidence behind a judgement is gathered.
     *
     * The Foundational guide names observation of the child and analysis of the
     * artefacts they produce as the two methods appropriate at that stage; the
     * rest are the forms those take further up.
     */
    public const EVIDENCE_MODES = [
        'observation' => 'Observation',
        'portfolio' => 'Portfolio / artefacts',
        'activity' => 'Classroom activity',
        'project' => 'Project',
        'conversation' => 'Conversation with the child',
        'worksheet' => 'Worksheet',
        'peer_feedback' => 'Peer feedback sheet',
        'self_reflection' => 'Self-reflection sheet',
    ];

    /** "Areas of Strength" on the teacher's feedback form, tick all that apply. */
    public const STRENGTHS = [
        'Follow Instructions',
        'Collaboration',
        'Independent Work',
        'Responsible',
        'Communication',
        'Creative',
        'Solution-focused Thinking',
        'Empathy',
        'Concentration',
        'Organisation & Prioritisation',
    ];

    /** "Barrier(s) to Success" on the same form. */
    public const BARRIERS = [
        'Lack of Attention',
        'Peer Pressure',
        'Lack of Motivation',
        'Undefined Goals',
        'Lack of Preparation',
        'Domestic Issues',
        'Inappropriate behaviour in classroom',
        'Severe illness or injury',
        'None',
    ];

    /**
     * The Part A elements a card carries before any subject is assessed.
     *
     * Kept as switches rather than assumed, because a school piloting the HPC
     * on one stage often adopts these in stages too.
     */
    public const PART_A_ELEMENTS = [
        'attendance' => 'Monthly attendance',
        'interest' => 'Interests ("I am interested in")',
        'all_about_me' => 'All About Me',
        // The Secondary card replaces "All About Me" with a structured
        // self-assessment the learner fills under the teacher's guidance.
        'self_assessment' => 'Learner self-assessment',
        'goal_setting' => 'Goal setting (academic and personal)',
        'ambition_card' => 'My Ambition Card',
    ];

    public static function defaults(): array
    {
        return [
            'version' => self::VERSION,
            'stage' => 'Middle',
            'proficiency_scale' => [
                ['code' => 'beginner', 'label' => 'Beginner', 'descriptor' => ''],
                ['code' => 'proficient', 'label' => 'Proficient', 'descriptor' => ''],
                ['code' => 'advanced', 'label' => 'Advanced', 'descriptor' => ''],
            ],
            'assessors' => ['teacher'],
            'abilities' => [],
            'areas' => [self::defaultArea()],
            'activity_approaches' => [],
            'evidence_modes' => ['observation'],
            'part_a' => [
                'attendance' => true,
                'interest' => true,
                'all_about_me' => true,
                'self_assessment' => false,
                'goal_setting' => true,
                'ambition_card' => false,
            ],
            'strengths' => self::STRENGTHS,
            'barriers' => self::BARRIERS,
            'notes' => '',
        ];
    }

    public static function defaultArea(string $name = ''): array
    {
        return [
            'id' => 'area-' . substr(md5(uniqid('', true)), 0, 8),
            'name' => $name,
            'code' => '',
            'note' => '',
            'curricular_goals' => [],
        ];
    }

    public static function defaultGoal(): array
    {
        return [
            'id' => 'cg-' . substr(md5(uniqid('', true)), 0, 8),
            'code' => '',
            'name' => '',
            'competencies' => [],
        ];
    }

    public static function defaultCompetency(): array
    {
        return [
            'id' => 'c-' . substr(md5(uniqid('', true)), 0, 8),
            'code' => '',
            'name' => '',
            'learning_outcomes' => [],
            'assessors' => [],
            'evidence_modes' => [],
        ];
    }

    /**
     * @param  mixed  $definition  Decoded JSON, a JSON string, or null.
     * @param  array<string,array<string,string>>|null  $vocabulary  One school's
     *         option lists, keyed by HpcVocabularyService::TYPE_*. Null uses the
     *         published defaults.
     */
    public static function normalize(mixed $definition, ?array $vocabulary = null): array
    {
        $assessors = $vocabulary['assessor'] ?? self::ASSESSORS;
        $approaches = $vocabulary['activity_approach'] ?? self::ACTIVITY_APPROACHES;
        $evidence = $vocabulary['evidence_mode'] ?? self::EVIDENCE_MODES;
        $partAElements = $vocabulary['part_a_element'] ?? self::PART_A_ELEMENTS;

        if (is_string($definition)) {
            $definition = json_decode($definition, true);
        }

        if (! is_array($definition)) {
            return self::defaults();
        }

        $stage = (string) ($definition['stage'] ?? 'Middle');

        $scale = [];

        foreach (self::arrayOf($definition, 'proficiency_scale') as $index => $level) {
            if (! is_array($level)) {
                continue;
            }

            $label = self::text($level['label'] ?? '', 60);

            if ($label === '') {
                continue;
            }

            $scale[] = [
                'code' => self::text($level['code'] ?? '', 40) ?: self::slug($label),
                'label' => $label,
                'descriptor' => self::text($level['descriptor'] ?? '', 500),
            ];
        }

        $abilities = [];

        foreach (self::arrayOf($definition, 'abilities') as $index => $ability) {
            if (! is_array($ability)) {
                continue;
            }

            $label = self::text($ability['label'] ?? '', 120);

            if ($label === '') {
                continue;
            }

            $abilities[] = [
                'id' => self::id($ability, 'ability-' . ($index + 1)),
                'code' => self::text($ability['code'] ?? '', 40) ?: self::slug($label),
                'label' => $label,
            ];
        }

        $areas = [];

        foreach (self::arrayOf($definition, 'areas') as $index => $area) {
            if (! is_array($area)) {
                continue;
            }

            $goals = [];

            foreach (self::arrayOf($area, 'curricular_goals') as $goalIndex => $goal) {
                if (! is_array($goal)) {
                    continue;
                }

                $competencies = [];

                foreach (self::arrayOf($goal, 'competencies') as $compIndex => $competency) {
                    if (! is_array($competency)) {
                        continue;
                    }

                    $competencies[] = [
                        'id' => self::id($competency, 'c-' . ($goalIndex + 1) . '-' . ($compIndex + 1)),
                        'code' => self::text($competency['code'] ?? '', 40),
                        'name' => self::text($competency['name'] ?? '', 500),
                        'learning_outcomes' => array_values(array_filter(array_map(
                            static fn ($outcome) => self::text($outcome, 500),
                            self::arrayOf($competency, 'learning_outcomes')
                        ), static fn ($outcome) => $outcome !== '')),
                        'assessors' => self::keysIn(self::arrayOf($competency, 'assessors'), $assessors),
                        'evidence_modes' => self::keysIn(self::arrayOf($competency, 'evidence_modes'), $evidence),
                    ];
                }

                $goals[] = [
                    'id' => self::id($goal, 'cg-' . ($index + 1) . '-' . ($goalIndex + 1)),
                    'code' => self::text($goal['code'] ?? '', 40),
                    'name' => self::text($goal['name'] ?? '', 500),
                    'competencies' => $competencies,
                ];
            }

            $areas[] = [
                'id' => self::id($area, 'area-' . ($index + 1)),
                'name' => self::text($area['name'] ?? '', 191),
                'code' => self::text($area['code'] ?? '', 40),
                'note' => self::text($area['note'] ?? '', 300),
                'curricular_goals' => $goals,
            ];
        }

        $partA = is_array($definition['part_a'] ?? null) ? $definition['part_a'] : [];
        $normalizedPartA = [];

        foreach (array_keys($partAElements) as $key) {
            $normalizedPartA[$key] = (bool) ($partA[$key] ?? false);
        }

        return [
            'version' => self::VERSION,
            'stage' => in_array($stage, self::STAGES, true) ? $stage : 'Middle',
            'proficiency_scale' => $scale !== [] ? $scale : self::defaults()['proficiency_scale'],
            'assessors' => self::keysIn(self::arrayOf($definition, 'assessors'), $assessors),
            'abilities' => $abilities,
            'areas' => $areas !== [] ? $areas : [self::defaultArea()],
            'activity_approaches' => self::keysIn(self::arrayOf($definition, 'activity_approaches'), $approaches),
            'evidence_modes' => self::keysIn(self::arrayOf($definition, 'evidence_modes'), $evidence),
            'part_a' => $normalizedPartA,
            'strengths' => array_values(array_filter(array_map(
                static fn ($entry) => self::text($entry, 120),
                self::arrayOf($definition, 'strengths')
            ), static fn ($entry) => $entry !== '')),
            'barriers' => array_values(array_filter(array_map(
                static fn ($entry) => self::text($entry, 120),
                self::arrayOf($definition, 'barriers')
            ), static fn ($entry) => $entry !== '')),
            'notes' => self::text($definition['notes'] ?? '', 2000),
        ];
    }

    /** Competencies across every area — the real size of the design. */
    public static function competencyCount(array $definition): int
    {
        $count = 0;

        foreach ($definition['areas'] ?? [] as $area) {
            foreach ($area['curricular_goals'] ?? [] as $goal) {
                $count += count($goal['competencies'] ?? []);
            }
        }

        return $count;
    }

    public static function goalCount(array $definition): int
    {
        $count = 0;

        foreach ($definition['areas'] ?? [] as $area) {
            $count += count($area['curricular_goals'] ?? []);
        }

        return $count;
    }

    /**
     * What is missing from this design, in a coordinator's words.
     *
     * Advisory, exactly as on the marks-based side. An HPC is built up over a
     * term and an incomplete one is a normal state, not an error.
     *
     * @return array<int,string>
     */
    public static function validate(array $definition): array
    {
        $warnings = [];

        if (($definition['assessors'] ?? []) === []) {
            $warnings[] = 'No assessor is set. An HPC is meant to carry more than one voice — at minimum the teacher.';
        }

        if (count($definition['proficiency_scale'] ?? []) < 2) {
            $warnings[] = 'A proficiency scale needs at least two levels to say anything.';
        }

        $emptyAreas = [];

        foreach ($definition['areas'] ?? [] as $area) {
            $name = $area['name'] !== '' ? $area['name'] : 'An unnamed area';

            if (($area['curricular_goals'] ?? []) === []) {
                $emptyAreas[] = $name;
            }
        }

        if ($emptyAreas !== []) {
            $warnings[] = count($emptyAreas) === 1
                ? sprintf('%s has no curricular goals yet.', $emptyAreas[0])
                : sprintf('%d areas have no curricular goals yet: %s.', count($emptyAreas), implode(', ', $emptyAreas));
        }

        if (self::competencyCount($definition) === 0 && self::goalCount($definition) > 0) {
            $warnings[] = 'Curricular goals are set but no competencies hang off them — a competency is what actually gets judged.';
        }

        if (($definition['evidence_modes'] ?? []) === []) {
            $warnings[] = 'No evidence method is chosen, so there is nothing to base a judgement on.';
        }

        return $warnings;
    }

    // -- helpers -------------------------------------------------------------

    /**
     * Keeps only the keys that exist in a fixed vocabulary, de-duplicated.
     *
     * A value that is not in the published list is a typo or a stale build, and
     * silently carrying it would let a card render a level or an assessor that
     * no form has a column for.
     *
     * @param  array<int|string,mixed>  $values
     * @param  array<string,string>  $vocabulary
     * @return array<int,string>
     */
    private static function keysIn(array $values, array $vocabulary): array
    {
        $kept = [];

        foreach ($values as $value) {
            $key = trim((string) $value);

            if ($key !== '' && isset($vocabulary[$key])) {
                $kept[$key] = true;
            }
        }

        return array_keys($kept);
    }

    private static function arrayOf(array $source, string $key): array
    {
        return is_array($source[$key] ?? null) ? $source[$key] : [];
    }

    private static function id(array $source, string $fallback): string
    {
        $id = trim((string) ($source['id'] ?? ''));

        return $id !== '' ? mb_substr($id, 0, 40) : $fallback;
    }

    private static function text(mixed $value, int $length): string
    {
        return mb_substr(trim((string) $value), 0, $length);
    }

    private static function slug(string $value): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? $value);

        return trim($slug, '_') ?: 'level';
    }
}
