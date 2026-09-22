<?php

use App\Domain\Eso\Flow\Stages\DiagnosticEntryStage;
use App\Domain\Eso\Flow\Stages\MasteryVerdictStage;
use App\Domain\Eso\Flow\Stages\MisconceptionScanStage;
use App\Domain\Eso\Flow\Stages\NodeLoopStage;
use App\Domain\Eso\Flow\Stages\NodesPresentStage;
use App\Domain\Eso\Flow\Stages\PhaseMachineStage;
use App\Domain\Eso\Flow\Stages\PrerequisiteGateStage;
use App\Domain\Eso\Flow\Stages\RetrievalDueStage;
use App\Domain\Eso\Flow\Stages\SettledSkipStage;
use App\Domain\Eso\Flow\Stages\StaleMasteryStage;

/*
|--------------------------------------------------------------------------
| PAL adaptive flow — the shipped default a school's flow is measured against
|--------------------------------------------------------------------------
|
| An institute's learning flow is DATA, not code. This file is the baseline
| that data overrides, and its ranks ARE the cascade order EsoPolicyService
| currently hardcodes — transcribed from nextAction() (line 1018) and
| phaseFor() (line 1444), with the source lines named on each entry so the two
| can be checked against each other by eye.
|
| Three rules govern this file.
|
|   1. IT HOLDS STRUCTURE AND PARAMETERS, never learner data and never
|      content. Same division as config/pal_architecture.php.
|
|   2. IT IS A DEFAULT, NOT THE LIVE VALUE. An institute is assigned a profile
|      (pal_flow_profiles, step 5) whose active version carries the whole
|      resolved structure; EsoFlowResolver merges that over this file. An
|      estate that has never been touched has zero rows and runs exactly this.
|
|   3. A STAGE'S TIER IS NOT HERE. tier() is a method on the handler class —
|      see App\Domain\Eso\Flow\EsoFlowStageMeta for why. If the tier were
|      config, a typo could unlock a LOCKED stage and put the D3 precedence
|      bug back.
|
| WHAT IS DELIBERATELY ABSENT
| ---------------------------
| There is no descriptor anywhere below for KNOWLEDGE_MASTERY_THRESHOLD,
| APPLICATION_MASTERY_THRESHOLD, PREREQUISITE_THRESHOLD, MIN_EVENTS_K/A or
| MIN_INDEPENDENT. That is the point, not an omission: a school may change the
| ROUTE a learner takes, never the FINISH LINE. Mastery means the same thing at
| every institute so that attainment is comparable across the estate and the
| multi-year career-intelligence accumulation means something. Because no
| descriptor exists, there is nothing for EsoFlowValidator to accept even if a
| profile row tried to set one.
|
| Nor is RETENTION_LADDER_DAYS here. Changing an interval under a learner
| already part-way up the ladder is its own versioning problem; deferred.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Guard rails
    |--------------------------------------------------------------------------
    */
    'guards' => [

        // Matches ArchitectureRegistry's writer_profiles, so the two admin
        // surfaces cannot disagree about who may reconfigure a school.
        'writer_profiles' => ['admin', 'administrator', 'principal', 'director', 'super admin'],

        // Assigning a profile changes what live learners are served next.
        'confirm_on_assign' => true,

        // Which engine nextAction() dispatches to.
        //
        // 'legacy'   — the hardcoded cascade. The default, and what production
        //              runs until rollout step 4.
        // 'pipeline' — the stage pipeline.
        //
        // One .env line, revertible without a deploy. EsoFlowParityTest proves
        // the two resolve identically before this is ever flipped.
        'engine' => env('PAL_FLOW_ENGINE', 'legacy'),
    ],

    /*
    |--------------------------------------------------------------------------
    | The stage catalogue — a CLOSED set
    |--------------------------------------------------------------------------
    |
    | Ten stages across two scopes. A profile may retune or reorder them and
    | may switch a TOGGLEABLE one off; it may never add or remove one. An
    | unknown key is rejected on write, and a MISSING key is rejected too — a
    | flow is a total order, so a partial one has no meaning.
    |
    | `scope` picks the pipeline: 'concept' runs once per nextAction(), 'node'
    | runs per node inside node_loop. Ranks are compared only within a scope,
    | because stages in different scopes never compete.
    |
    */
    'stages' => [

        'nodes_present' => [
            'scope' => 'concept',
            'rank' => 100,
            'label' => 'Concept has authored nodes',
            'handler' => NodesPresentStage::class,
            'enabled' => true,
            'params' => [],
            'fields' => [],
        ],

        'diagnostic_entry' => [
            'scope' => 'concept',
            'rank' => 200,
            'label' => 'Entry diagnostic',
            'handler' => DiagnosticEntryStage::class,
            'enabled' => true,
            'params' => [
                // EsoPolicyService::diagnosticItems() default.
                'item_count' => 8,
            ],
            'fields' => [
                [
                    'key' => 'item_count',
                    'label' => 'Diagnostic items',
                    'type' => 'number',
                    'min' => 1,
                    'max' => 20,
                    'step' => 1,
                    'help' => 'How many items the entry diagnostic serves. Note that a learner who answers EVERY item correctly on at least 3 distinct items is granted mastery outright (a clean sweep), so a longer diagnostic makes that harder to trigger, not easier.',
                ],
            ],
        ],

        'prerequisite_gate' => [
            'scope' => 'concept',
            'rank' => 300,
            'label' => 'Prerequisite gate',
            'handler' => PrerequisiteGateStage::class,
            'enabled' => true,
            'params' => [],
            'fields' => [],
        ],

        'misconception_scan' => [
            'scope' => 'concept',
            'rank' => 400,
            'label' => 'Misconception correction',
            'handler' => MisconceptionScanStage::class,
            'enabled' => true,
            'params' => [],
            'fields' => [],
        ],

        'node_loop' => [
            'scope' => 'concept',
            'rank' => 500,
            'label' => 'Node loop',
            'handler' => NodeLoopStage::class,
            'enabled' => true,
            'params' => [],
            'fields' => [],
        ],

        'mastery_verdict' => [
            'scope' => 'concept',
            'rank' => 900,
            'label' => 'Mastery verdict',
            'handler' => MasteryVerdictStage::class,
            'enabled' => true,
            'params' => [
                // Both are PRESENTATION on an already-decided verdict. Neither
                // can change whether mastery was reached.
                //
                // Resolved only on a non-silent verdict, and the stage must
                // keep that: chapterDashboard() reaches this through
                // conceptStatusFor(), and nextEligibleConcept() reaches
                // conceptStatusFor() in turn, so resolving them on the silent
                // path recurses without bound (EsoPolicyService.php:2549-2557).
                'offer_enrichment' => true,
                'offer_next_concept' => true,
            ],
            'fields' => [
                ['key' => 'offer_enrichment', 'label' => 'Suggest enrichment after mastery', 'type' => 'toggle'],
                ['key' => 'offer_next_concept', 'label' => 'Suggest the next concept', 'type' => 'toggle'],
            ],
        ],

        'retrieval_due' => [
            'scope' => 'node',
            'rank' => 100,
            'label' => 'Spaced retrieval',
            'handler' => RetrievalDueStage::class,
            'enabled' => true,
            'params' => [],
            'fields' => [],
        ],

        'stale_mastery' => [
            'scope' => 'node',
            'rank' => 200,
            'label' => 'Stale mastery re-check',
            'handler' => StaleMasteryStage::class,
            'enabled' => true,
            'params' => [],
            'fields' => [],
        ],

        'settled_skip' => [
            'scope' => 'node',
            'rank' => 300,
            'label' => 'Skip a settled node',
            'handler' => SettledSkipStage::class,
            'enabled' => true,
            'params' => [],
            'fields' => [],
        ],

        'phase_machine' => [
            'scope' => 'node',
            'rank' => 400,
            'label' => 'Learn / Practice / Check',
            'handler' => PhaseMachineStage::class,
            'enabled' => true,
            'params' => [],
            'fields' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | The phase sequence — inside phase_machine
    |--------------------------------------------------------------------------
    |
    | A THIRD granularity, and the one schools actually ask about. These are
    | the arms of phaseFor() (line 1444), not stages of the cascade: the
    | cascade never sees them, and a phase rank is meaningless against a stage
    | rank.
    |
    | `learn` is LOCKED at rank 100 for a mechanical reason, not a pedagogical
    | one: practiceComplete() returns false while taught_at is null, and
    | checkSettled() turns on cfu_attempts, which only recordCheckUnderstanding()
    | increments. A profile that put practice or check first would deadlock on
    | the first resolve. The validator rejects it by partial order.
    |
    */
    'phases' => [

        'learn' => [
            'rank' => 100,
            'label' => 'Learn',
            'enabled' => true,
            'params' => [],
            'fields' => [],
        ],

        'practice' => [
            'rank' => 200,
            'label' => 'Practice',
            'enabled' => true,
            'params' => [
                // Null means "inherit whatever practiceItemsRequired() decides".
                //
                // A PHASE-EXIT dial, NOT an evidence dial. Lowering it makes
                // practiceComplete() true sooner, so the learner sees fewer
                // questions per lap — it does NOT move MIN_EVENTS_K, so
                // nodeMeetsEvidenceFloor() still refuses to pass the node and
                // phaseFor()'s second route back to practice (lines 1461-1464)
                // keeps serving it until the floor is genuinely met.
                //
                // That is the honest answer to "we only have 45 minutes a
                // week": the SCREEN changes, the STANDARD does not.
                'min_items_override' => null,
            ],
            'fields' => [
                [
                    'key' => 'min_items_override',
                    'label' => 'Questions per practice run',
                    'type' => 'number',
                    'min' => 1,
                    'max' => 10,
                    'step' => 1,
                    'help' => 'Leave blank to inherit. This changes how many questions a learner sees in one run, never how many demonstrations mastery requires.',
                ],
            ],
        ],

        'check' => [
            'rank' => 300,
            'label' => 'Check for understanding',
            'enabled' => true,
            'params' => [
                // EsoPolicyService::CFU_ITEM_COUNT.
                'item_count' => 2,

                // EsoPolicyService::CFU_MAX_CYCLES. Safe to tune because the
                // constant's own docblock is explicit that cfu_attempts "is a
                // loop guard, not a mastery rule: it decides which screen is
                // served, never whether anything is mastered."
                'max_cycles' => 2,
            ],
            'fields' => [
                ['key' => 'item_count', 'label' => 'Check questions', 'type' => 'number', 'min' => 1, 'max' => 5, 'step' => 1],
                ['key' => 'max_cycles', 'label' => 'Re-teach attempts before moving on', 'type' => 'number', 'min' => 1, 'max' => 4, 'step' => 1],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipped profiles
    |--------------------------------------------------------------------------
    |
    | Deltas over the catalogue above. Seeded as version-1 rows at step 5.
    |
    | Four, not forty. The support surface of this whole design is the PROFILE
    | COUNT, not the institute count — 200 schools on 4 profiles is a system
    | that can be reasoned about and tested; 200 bespoke configurations is 200
    | forks with better manners. A school asking for a fifth shape gets mapped
    | to the nearest of these unless enough schools want the same thing to
    | justify a new one.
    |
    | There is deliberately no `mastery_after_learn`. That request is refused at
    | the product level, because it means mastery on zero demonstrations.
    |
    */
    'profiles' => [

        // The parity anchor: today's behaviour, byte for byte. Every institute
        // resolves here until explicitly assigned something else.
        'standard' => [
            'label' => 'Standard',
            'description' => 'Diagnostic, then Learn / Practice / Check per concept, then mastery and spaced recall.',
            'is_default' => true,
            'delta' => [],
        ],

        'diagnostic_free' => [
            'label' => 'No entry diagnostic',
            'description' => 'For schools that place students with their own entrance test.',
            'is_default' => false,
            'delta' => [
                'stages' => ['diagnostic_entry' => ['enabled' => false]],
            ],
        ],

        'no_cfu' => [
            'label' => 'No check step',
            'description' => 'Learn and practise, without the separate check for understanding.',
            'is_default' => false,
            'delta' => [
                'phases' => ['check' => ['enabled' => false]],
            ],
        ],

        // The order that ACTUALLY SHIPPED before September 2026 — the check sat
        // immediately after teaching. Documented at EsoPolicyService.php:1911-1924,
        // which makes this the one reordering already known to work end to end.
        'check_first' => [
            'label' => 'Check before practice',
            'description' => 'The pre-2026 order: Learn, check understanding, then practise.',
            'is_default' => false,
            'delta' => [
                'phases' => [
                    'check' => ['rank' => 200],
                    'practice' => ['rank' => 300],
                ],
            ],
        ],
    ],
];
