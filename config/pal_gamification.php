<?php

/*
|--------------------------------------------------------------------------
| PAL V4 — Gamification & Motivation System
|--------------------------------------------------------------------------
|
| Source of truth: PAL_V4_Gamification_Motivation_System.md.
|
| This file holds the SPECIFICATION only — the rules, thresholds, catalogues,
| vocabularies and copy that the document defines. It holds NO learner data and
| no sample values: every number a student, teacher or parent ever sees is
| computed by App\Services\PAL\Gamification\* from real activity in the
| database. The worked examples in the document (0.81 fluency, a 12-day streak,
| "6 of 20 skills") are illustrations of the schema and are deliberately absent
| here.
|
| The design constraint that governs everything below (document §0):
|   every mechanic must make a struggling student feel MORE capable, not less.
| That is why there is exactly one leaderboard in the whole system, it is
| opt-in, and §9's visibility matrix is enforced server-side rather than by the
| UI choosing what to render.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Mastery tiers — Stream / Mountain / Sky (document §1)
    |--------------------------------------------------------------------------
    |
    | Not new thresholds: these are the existing PAL V4 practice-ladder gates
    | (config/pal_content.php `practice_levels`) named as the three-tier rubric
    | the gamification layer shows the student. Mountain is the L3 bkt_mastery
    | gate (0.70), Sky is the L4 gate (0.85). Keep the two in step.
    |
    */
    'mastery_tiers' => [
        'stream' => [
            'key' => 'stream',
            'label' => 'Stream',
            'min_mastery' => 0.0,
            'bloom_ceiling' => 3,
            'student_message' => 'You are working through this one. Every expert started here.',
        ],
        'mountain' => [
            'key' => 'mountain',
            'label' => 'Mountain',
            'min_mastery' => 0.70,
            'bloom_ceiling' => 4,
            'student_message' => 'You have this. Remember when it felt impossible?',
        ],
        'sky' => [
            'key' => 'sky',
            'label' => 'Sky',
            'min_mastery' => 0.85,
            'bloom_ceiling' => 5,
            'student_message' => 'You can explain this better than most adults.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Net fluency (document §2.2 "fluency_records", §3 Fluency badges)
    |--------------------------------------------------------------------------
    |
    | net_fluency = accuracy x speed_index.
    |
    | `accuracy` is right / (right + wrong) on a real attempt. `speed_index` is
    | the learner's own pace against a baseline pace for the same paper, taken
    | from the attempt history of that paper — never an invented target time.
    | It is clamped so that being fast cannot manufacture fluency out of a poor
    | accuracy, and being slow on one attempt cannot erase a good one.
    |
    | When no baseline exists (a paper nobody else has attempted), speed_index
    | is 1.0 and net fluency degrades gracefully to plain accuracy.
    |
    */
    'fluency' => [
        'speed_index_min' => 0.50,
        'speed_index_max' => 1.25,
        'min_items_for_fluency' => 3,
        // A paper needs this many distinct attempts before its median pace is
        // treated as a baseline worth comparing an individual against.
        'baseline_min_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal Best (document §2)
    |--------------------------------------------------------------------------
    |
    | Every metric here is measured against the learner's OWN prior value. The
    | `frame` string is the only shape of comparison the system is allowed to
    | render — see `forbidden_framings` below, which the visibility layer
    | enforces by never emitting rank, class average or peer counts to a
    | student-facing payload.
    |
    */
    'personal_best' => [
        'metrics' => [
            'net_fluency' => [
                'key' => 'net_fluency',
                'group' => 'fluency_records',
                'label' => 'Net fluency',
                'scope' => 'concept',
                'format' => 'ratio',
                'direction' => 'higher',
                'trigger_copy' => 'Personal Best! Your :scope fluency just hit :value — up from your previous best of :previous. That is real improvement.',
            ],
            'streak_days' => [
                'key' => 'streak_days',
                'group' => 'streak_records',
                'label' => 'Longest learning streak',
                'scope' => 'global',
                'format' => 'days',
                'direction' => 'higher',
                'trigger_copy' => 'You are on a :value-day streak — your longest ever was :previous. You have broken your own record!',
            ],
            'concepts_at_sky' => [
                'key' => 'concepts_at_sky',
                'group' => 'mastery_records',
                'label' => 'Concepts at Sky',
                'scope' => 'global',
                'format' => 'count',
                'direction' => 'higher',
                'trigger_copy' => 'That is :value concepts at Sky level — more than you have ever held at once.',
            ],
            'concepts_at_mountain' => [
                'key' => 'concepts_at_mountain',
                'group' => 'mastery_records',
                'label' => 'Concepts at Mountain',
                'scope' => 'global',
                'format' => 'count',
                'direction' => 'higher',
                'trigger_copy' => 'That is :value concepts at Mountain level — more than you have ever held at once.',
            ],
            'fastest_concept_sessions' => [
                'key' => 'fastest_concept_sessions',
                'group' => 'mastery_records',
                'label' => 'Fastest concept mastered',
                'scope' => 'concept',
                'format' => 'sessions',
                'direction' => 'lower',
                'trigger_copy' => 'You mastered :scope in :value sessions — your fastest concept yet. The more you practise, the better you get at learning itself.',
            ],
            'longest_productive_session_min' => [
                'key' => 'longest_productive_session_min',
                'group' => 'session_records',
                'label' => 'Longest productive session',
                'scope' => 'global',
                'format' => 'minutes',
                'direction' => 'higher',
                'trigger_copy' => 'You stayed with your learning for :value minutes — your longest focused session so far.',
            ],
            'most_concepts_in_one_day' => [
                'key' => 'most_concepts_in_one_day',
                'group' => 'session_records',
                'label' => 'Most concepts in one day',
                'scope' => 'global',
                'format' => 'count',
                'direction' => 'higher',
                'trigger_copy' => 'You made progress on :value concepts in one day — your best day so far.',
            ],
            'most_concepts_in_one_week' => [
                'key' => 'most_concepts_in_one_week',
                'group' => 'session_records',
                'label' => 'Best week',
                'scope' => 'global',
                'format' => 'count',
                'direction' => 'higher',
                'trigger_copy' => 'This week you made progress on :value concepts — your best week this term. Your brain had a great week.',
            ],
            'best_single_session_mastery_gain' => [
                'key' => 'best_single_session_mastery_gain',
                'group' => 'session_records',
                'label' => 'Best single-session gain',
                'scope' => 'global',
                'format' => 'ratio',
                'direction' => 'higher',
                'trigger_copy' => 'Your mastery moved :value in a single session — the biggest jump you have made.',
            ],
        ],

        // §2.4 — the framings the system may never produce. Kept here so the
        // rule is greppable from the copy that has to obey it.
        'forbidden_framings' => [
            'class_rank',
            'peers_ahead_count',
            'class_average_comparison',
            'other_learner_personal_best',
            'peers_at_sky_count',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaks (document §7)
    |--------------------------------------------------------------------------
    */
    'streak' => [
        // A day only counts with real productive engagement (§7.1).
        'min_productive_minutes' => 10,
        'qualifying_activities' => [
            'learning_cell' => ['label' => 'A full concept → practice cycle', 'min_count' => 1],
            'spaced_review' => ['label' => 'Spaced review items', 'min_count' => 3],
            'peer_teaching' => ['label' => 'A peer teaching session', 'min_count' => 1],
            'team_challenge' => ['label' => 'A team challenge contribution', 'min_count' => 1],
        ],
        // Illness, school events, family situations — one forgiven day per week.
        'grace_period_days' => 1,
        'grace_reset_days' => 7,
        // Milestones the badge catalogue and notifications key off.
        'milestones' => [3, 7, 21],
        'language' => [
            // §7.2 — growth framing only. A broken streak is a new streak.
            'reset_copy' => 'New streak starting — every streak starts with day 1.',
            'return_copy' => 'You came back. That is the whole game.',
            'grace_copy' => 'Your streak is safe — everyone misses a day sometimes.',
        ],
        'forbidden_language' => [
            'broke_your_streak',
            'hours_left_warning',
            'friends_streak_comparison',
            'days_to_beat_record',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Badge catalogue (document §3.2)
    |--------------------------------------------------------------------------
    |
    | Each badge is a RULE, not a record. `trigger.type` names the evaluator in
    | App\Services\PAL\Gamification\BadgeService; the badge is awarded the first
    | time that evaluator returns true against the learner's real signal pack.
    | A badge whose evidence this estate does not yet produce simply never
    | awards — that is a genuine empty state, not a hidden default.
    |
    | `scope` = 'global' (awarded once) or 'concept'/'subject' (awarded once per
    | concept/subject, the scope key being stored on the award row).
    |
    */
    'badges' => [

        // ---- Mastery (academic achievement) --------------------------------
        [
            'badge_id' => 'BADGE_FIRST_STEP',
            'name' => 'First step',
            'category' => 'mastery',
            'description' => 'First concept reaches Mountain level',
            'student_message' => 'You moved from Stream to Mountain on your first concept. Every expert started exactly here.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => null,
            'ncdg_goal' => 'EDL1',
            'rarity' => 'common',
            'hpc_evidence_weight' => 0.6,
            'scope' => 'global',
            'trigger' => ['type' => 'concepts_at_tier', 'tier' => 'mountain', 'count' => 1],
        ],
        [
            'badge_id' => 'BADGE_MOUNTAIN_CLIMBER',
            'name' => 'Mountain climber',
            'category' => 'mastery',
            'description' => '10 concepts at Mountain level',
            'student_message' => '10 concepts at Mountain level. You are building real knowledge.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => null,
            'ncdg_goal' => 'EDL1',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.7,
            'scope' => 'global',
            'trigger' => ['type' => 'concepts_at_tier', 'tier' => 'mountain', 'count' => 10],
        ],
        [
            'badge_id' => 'BADGE_SKY_ACHIEVER',
            'name' => 'Sky achiever',
            'category' => 'mastery',
            'description' => 'First concept reaches Sky level',
            'student_message' => 'Sky level on :concept. You can now explain this to others better than most adults.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => null,
            'ncdg_goal' => 'EDL1',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.8,
            'scope' => 'global',
            'trigger' => ['type' => 'concepts_at_tier', 'tier' => 'sky', 'count' => 1],
        ],
        [
            'badge_id' => 'BADGE_SKY_MASTER',
            'name' => 'Sky master',
            'category' => 'mastery',
            'description' => '5 concepts at Sky level',
            'student_message' => '5 Sky-level concepts. You have genuine expertise in these areas.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => null,
            'ncdg_goal' => 'EDL1',
            'rarity' => 'rare',
            'hpc_evidence_weight' => 0.9,
            'scope' => 'global',
            'trigger' => ['type' => 'concepts_at_tier', 'tier' => 'sky', 'count' => 5],
        ],
        [
            'badge_id' => 'BADGE_COMPLETE_THINKER',
            'name' => 'Complete thinker',
            'category' => 'mastery',
            'description' => 'Sky level in all 3 HPC lenses (Awareness, Sensitivity, Creativity)',
            'student_message' => 'You have shown mastery across thinking, feeling and creating. That is rare.',
            'hpc_domain' => 'all',
            'casel_domain' => null,
            'ncdg_goal' => 'EDL1',
            'rarity' => 'legendary',
            'hpc_evidence_weight' => 1.0,
            'scope' => 'global',
            'trigger' => ['type' => 'all_hpc_lenses_at_tier', 'tier' => 'sky'],
        ],
        [
            'badge_id' => 'BADGE_SUBJECT_CHAMPION',
            'name' => 'Subject champion',
            'category' => 'mastery',
            'description' => 'Sky level in 5+ concepts within one subject',
            'student_message' => 'You are a :subject champion. This is a real strength.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => null,
            'ncdg_goal' => 'EDL1',
            'rarity' => 'rare',
            'hpc_evidence_weight' => 0.9,
            'scope' => 'subject',
            'trigger' => ['type' => 'subject_concepts_at_tier', 'tier' => 'sky', 'count' => 5],
        ],

        // ---- Fluency (speed + accuracy) ------------------------------------
        [
            'badge_id' => 'BADGE_QUICK_AND_CORRECT',
            'name' => 'Quick and correct',
            'category' => 'fluency',
            'description' => 'Net fluency above 0.80 on any concept',
            'student_message' => 'You are not just getting it right — you are getting it right fast. That is mastery.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => null,
            'ncdg_goal' => 'EDL1',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.7,
            'scope' => 'concept',
            'trigger' => ['type' => 'net_fluency_at_least', 'threshold' => 0.80],
        ],
        [
            'badge_id' => 'BADGE_FLUENCY_STREAK',
            'name' => 'Fluency streak',
            'category' => 'fluency',
            'description' => 'Net fluency above 0.75 for 5 consecutive sessions on the same concept',
            'student_message' => 'You have been sharp and accurate for 5 sessions in a row on :concept.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => 'self_management',
            'ncdg_goal' => 'EDL1',
            'rarity' => 'rare',
            'hpc_evidence_weight' => 0.8,
            'scope' => 'concept',
            'trigger' => ['type' => 'consecutive_fluency', 'threshold' => 0.75, 'sessions' => 5],
        ],
        [
            'badge_id' => 'BADGE_SPEED_DEMON',
            'name' => 'Speed demon',
            'category' => 'fluency',
            'description' => 'Top 10% fluency score on a Challenge Mode task',
            'student_message' => 'Top 10% on that Challenge Mode task. Sharp work.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => null,
            'ncdg_goal' => null,
            'rarity' => 'rare',
            'hpc_evidence_weight' => 0.3,
            'scope' => 'global',
            // Challenge Mode only — never shown or evaluated in regular learning.
            'challenge_mode_only' => true,
            'trigger' => ['type' => 'challenge_mode_percentile', 'percentile' => 90],
        ],

        // ---- Persistence (CASEL self-management) ---------------------------
        [
            'badge_id' => 'BADGE_STREAK_DAY_3',
            'name' => 'Day 3 streak',
            'category' => 'persistence',
            'description' => '3 consecutive days with a session',
            'student_message' => 'Day 3 — you showed up again. Consistency is how learning happens.',
            'hpc_domain' => 'positive_learning_habits',
            'casel_domain' => 'self_management',
            'ncdg_goal' => 'PS2',
            'rarity' => 'common',
            'hpc_evidence_weight' => 0.5,
            'scope' => 'global',
            'trigger' => ['type' => 'streak_days', 'days' => 3],
        ],
        [
            'badge_id' => 'BADGE_STREAK_DAY_7',
            'name' => 'Day 7 streak',
            'category' => 'persistence',
            'description' => '7 consecutive days',
            'student_message' => 'A full week of learning. That takes real commitment.',
            'hpc_domain' => 'positive_learning_habits',
            'casel_domain' => 'self_management',
            'ncdg_goal' => 'PS2',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.6,
            'scope' => 'global',
            'trigger' => ['type' => 'streak_days', 'days' => 7],
        ],
        [
            'badge_id' => 'BADGE_STREAK_DAY_21',
            'name' => 'Day 21 streak',
            'category' => 'persistence',
            'description' => '21 consecutive days',
            'student_message' => '21 days. This is no longer just a habit — it is part of who you are.',
            'hpc_domain' => 'positive_learning_habits',
            'casel_domain' => 'self_management',
            'ncdg_goal' => 'PS2',
            'rarity' => 'legendary',
            'hpc_evidence_weight' => 0.9,
            'scope' => 'global',
            'trigger' => ['type' => 'streak_days', 'days' => 21],
        ],
        [
            'badge_id' => 'BADGE_COMEBACK_KID',
            'name' => 'Comeback kid',
            'category' => 'persistence',
            'description' => 'Returns after a 5+ day gap and completes a full session',
            'student_message' => 'You came back. That is what matters.',
            'hpc_domain' => 'positive_learning_habits',
            'casel_domain' => 'self_management',
            'ncdg_goal' => 'PS2',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.6,
            'scope' => 'global',
            'trigger' => ['type' => 'return_after_gap', 'gap_days' => 5],
        ],
        [
            'badge_id' => 'BADGE_BOUNCED_BACK',
            'name' => 'Bounced back',
            'category' => 'persistence',
            'description' => 'Recovers from a misconception in the same session',
            'student_message' => 'You got it wrong, then figured out why, then got it right. That is exactly how learning works.',
            'hpc_domain' => 'positive_learning_habits',
            'casel_domain' => 'self_management',
            'ncdg_goal' => 'PS2',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.7,
            'scope' => 'global',
            'trigger' => ['type' => 'misconception_resolved_same_session'],
        ],
        [
            'badge_id' => 'BADGE_KEEPS_GOING',
            'name' => 'Keeps going',
            'category' => 'persistence',
            'description' => 'Completes 5 practice items after getting the first 2 wrong',
            'student_message' => 'You did not give up when it got hard. That is one of the most important skills there is.',
            'hpc_domain' => 'positive_learning_habits',
            'casel_domain' => 'self_management',
            'ncdg_goal' => 'PS2',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.7,
            'scope' => 'global',
            'trigger' => ['type' => 'persisted_after_errors', 'leading_wrong' => 2, 'then_items' => 5],
        ],

        // ---- Curiosity (self-directed learning) ----------------------------
        [
            'badge_id' => 'BADGE_EXPLORER',
            'name' => 'Explorer',
            'category' => 'curiosity',
            'description' => 'Opens a content node the system did not assign',
            'student_message' => 'You went looking for something new without being asked. That is curiosity.',
            'hpc_domain' => 'positive_learning_habits',
            'casel_domain' => 'self_management',
            'ncdg_goal' => 'EDL2',
            'rarity' => 'common',
            'hpc_evidence_weight' => 0.6,
            'scope' => 'global',
            'trigger' => ['type' => 'self_directed_content', 'count' => 1],
        ],
        [
            'badge_id' => 'BADGE_DEEP_DIVER',
            'name' => 'Deep diver',
            'category' => 'curiosity',
            'description' => 'Spends 20+ minutes on a single concept in one session',
            'student_message' => 'You stayed with something until you really understood it. That is how experts think.',
            'hpc_domain' => 'positive_learning_habits',
            'casel_domain' => 'self_management',
            'ncdg_goal' => 'EDL2',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.7,
            'scope' => 'global',
            'trigger' => ['type' => 'single_concept_minutes', 'minutes' => 20],
        ],
        [
            'badge_id' => 'BADGE_CROSS_CONNECTOR',
            'name' => 'Cross connector',
            'category' => 'curiosity',
            'description' => 'Completes a cross-curricular link activity',
            'student_message' => 'You connected :from to :to. The best thinkers see how things link up.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => 'self_awareness',
            'ncdg_goal' => 'EDL2',
            'rarity' => 'rare',
            'hpc_evidence_weight' => 0.8,
            'scope' => 'global',
            'trigger' => ['type' => 'cross_curricular_completion', 'count' => 1],
        ],
        [
            'badge_id' => 'BADGE_PATHWAY_PIONEER',
            'name' => 'Pathway pioneer',
            'category' => 'curiosity',
            'description' => 'Activates an interest pathway independently',
            'student_message' => 'You took your learning in your own direction. This is your journey.',
            'hpc_domain' => 'aesthetic',
            'casel_domain' => 'self_awareness',
            'ncdg_goal' => 'CM1',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.7,
            'scope' => 'global',
            'trigger' => ['type' => 'interest_pathway_activated', 'domains' => ['music', 'sports', 'finance']],
        ],

        // ---- Social (CASEL relationship skills) ----------------------------
        [
            'badge_id' => 'BADGE_PEER_TEACHER',
            'name' => 'Peer teacher',
            'category' => 'social',
            'description' => 'Completes a peer teaching assignment at Sky level',
            'student_message' => 'You explained :concept to a classmate. Teaching is the highest form of understanding.',
            'hpc_domain' => 'socio_emotional',
            'casel_domain' => 'relationship_skills',
            'ncdg_goal' => 'PS2',
            'rarity' => 'rare',
            'hpc_evidence_weight' => 0.9,
            'scope' => 'global',
            'trigger' => ['type' => 'peer_teaching_sessions', 'count' => 1],
        ],
        [
            'badge_id' => 'BADGE_THREE_TIMES_TEACHER',
            'name' => 'Three times teacher',
            'category' => 'social',
            'description' => 'Completes 3 peer teaching assignments',
            'student_message' => 'You have helped three classmates understand something they were struggling with.',
            'hpc_domain' => 'socio_emotional',
            'casel_domain' => 'relationship_skills',
            'ncdg_goal' => 'PS2',
            'rarity' => 'legendary',
            'hpc_evidence_weight' => 1.0,
            'scope' => 'global',
            'trigger' => ['type' => 'peer_teaching_sessions', 'count' => 3],
        ],
        [
            'badge_id' => 'BADGE_TEAM_PLAYER',
            'name' => 'Team player',
            'category' => 'social',
            'description' => 'Contributes to a completed team challenge',
            'student_message' => 'Your class reached the goal together — and you were part of it.',
            'hpc_domain' => 'socio_emotional',
            'casel_domain' => 'relationship_skills',
            'ncdg_goal' => 'PS2',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.7,
            'scope' => 'global',
            'trigger' => ['type' => 'team_challenge_contribution', 'count' => 1],
        ],
        [
            'badge_id' => 'BADGE_GOOD_QUESTIONER',
            'name' => 'Good questioner',
            'category' => 'social',
            'description' => 'Asks the AI Tutor a question it cannot immediately answer',
            'student_message' => 'That was a genuinely hard question. Keep asking questions like that.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => 'social_awareness',
            'ncdg_goal' => 'EDL2',
            'rarity' => 'rare',
            'hpc_evidence_weight' => 0.8,
            'scope' => 'global',
            'trigger' => ['type' => 'unresolved_tutor_question', 'count' => 1],
        ],

        // ---- Career & pathway (NCDG) ---------------------------------------
        [
            'badge_id' => 'BADGE_CAREER_EXPLORER',
            'name' => 'Career explorer',
            'category' => 'career',
            'description' => 'Completes a first career scenario in a vocational domain',
            'student_message' => 'You have explored what :career looks like. Keep going — your picture is building.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => 'self_awareness',
            'ncdg_goal' => 'CM2',
            'rarity' => 'common',
            'hpc_evidence_weight' => 0.6,
            'scope' => 'global',
            'trigger' => ['type' => 'career_scenarios_completed', 'count' => 1],
        ],
        [
            'badge_id' => 'BADGE_RIASEC_REVEALED',
            'name' => 'RIASEC revealed',
            'category' => 'career',
            'description' => 'Enough RIASEC signals accumulated to generate a first profile',
            'student_message' => 'Your first Career Personality Profile is ready. See what your learning says about you.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => 'self_awareness',
            'ncdg_goal' => 'CM2',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.7,
            'scope' => 'global',
            'trigger' => ['type' => 'riasec_profile_ready'],
        ],
        [
            'badge_id' => 'BADGE_FINANCE_LEVEL_1',
            'name' => 'Finance level 1',
            'category' => 'career',
            'description' => 'Completes Finance Pathway level 1',
            'student_message' => 'You now understand banking basics better than most adults. Financial literacy is a life skill.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => 'responsible_decision_making',
            'ncdg_goal' => 'CM2',
            'rarity' => 'uncommon',
            'hpc_evidence_weight' => 0.7,
            'scope' => 'global',
            'trigger' => ['type' => 'framework_tag_mastered', 'framework_type' => 'finance', 'framework_tag' => 'finance_literacy'],
        ],
        [
            'badge_id' => 'BADGE_SKY_ON_VOCATIONAL',
            'name' => 'Sky on vocational',
            'category' => 'career',
            'description' => 'First NSQF-mapped competency demonstrated at Sky level',
            'student_message' => 'This is the level employers look for. You have demonstrated it.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => null,
            'ncdg_goal' => 'CM3',
            'rarity' => 'rare',
            'hpc_evidence_weight' => 0.9,
            'scope' => 'global',
            'trigger' => ['type' => 'vocational_concept_at_tier', 'tier' => 'sky', 'count' => 1],
        ],
        [
            'badge_id' => 'BADGE_CAREER_CLARITY',
            'name' => 'Career clarity',
            'category' => 'career',
            'description' => 'Career Pathway Report generated',
            'student_message' => 'Years of learning just became a map of where you could go next. Read it.',
            'hpc_domain' => 'cognitive',
            'casel_domain' => 'self_awareness',
            'ncdg_goal' => 'CM4',
            'rarity' => 'legendary',
            'hpc_evidence_weight' => 1.0,
            'scope' => 'global',
            'trigger' => ['type' => 'career_report_generated'],
        ],
    ],

    'badge_categories' => [
        'mastery' => ['label' => 'Mastery', 'blurb' => 'Academic achievement against the Stream → Mountain → Sky rubric.'],
        'fluency' => ['label' => 'Fluency', 'blurb' => 'Speed paired with accuracy — right, and right quickly.'],
        'persistence' => ['label' => 'Persistence', 'blurb' => 'Showing up, coming back, and staying with something hard.'],
        'curiosity' => ['label' => 'Curiosity', 'blurb' => 'Learning nobody assigned.'],
        'social' => ['label' => 'Social', 'blurb' => 'Teaching, contributing and questioning well.'],
        'career' => ['label' => 'Career & pathway', 'blurb' => 'Evidence for the NCDG career-development goals.'],
    ],

    // §3.3 — badges are evidence, not decoration, and never a ranking.
    'badge_rules' => [
        'never_expire' => true,
        'never_ranked_between_students' => true,
        'teacher_may_revoke' => true,
        'visible_on_public_wall' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Team challenges (document §4)
    |--------------------------------------------------------------------------
    */
    'team_challenges' => [
        'types' => [
            'mastery_sprint' => [
                'key' => 'mastery_sprint',
                'label' => 'Mastery sprint',
                'summary' => 'A share of the class reaches a mastery tier on one concept by a deadline.',
                'target_metric' => 'class_share_at_tier',
                'requires_concept' => true,
                'default_target_value' => 80,
                'target_unit' => 'percent_of_class',
                'inclusive' => false,
            ],
            'collective_fluency' => [
                'key' => 'collective_fluency',
                'label' => 'Collective fluency challenge',
                'summary' => 'The class average fluency beats the class\'s own earlier average — never another class.',
                'target_metric' => 'class_average_fluency_vs_baseline',
                'requires_concept' => true,
                'default_target_value' => 0,
                'target_unit' => 'improvement_points',
                'inclusive' => false,
            ],
            'peer_teaching' => [
                'key' => 'peer_teaching',
                'label' => 'Peer teaching challenge',
                'summary' => 'A count of peer teaching sessions completed across the class.',
                'target_metric' => 'peer_teaching_sessions',
                'requires_concept' => false,
                'default_target_value' => 10,
                'target_unit' => 'sessions',
                'inclusive' => false,
            ],
            'exploration' => [
                'key' => 'exploration',
                'label' => 'Exploration challenge',
                'summary' => 'Every student opens at least one piece of self-directed content. Inclusive by design — mastery level is irrelevant.',
                'target_metric' => 'students_with_self_directed_content',
                'requires_concept' => false,
                'default_target_value' => 100,
                'target_unit' => 'percent_of_class',
                'inclusive' => true,
            ],
        ],

        // §4.3 — governance.
        'max_active_per_class_per_week' => 2,
        'teacher_initiated_only' => true,
        'teacher_may_end_early' => true,
        'reward_must_be_content' => true,

        // What a student may see about a challenge. Everything else is
        // teacher-only and stripped by the visibility layer.
        'student_visible_fields' => [
            'title', 'type', 'reward', 'deadline', 'days_remaining',
            'class_progress', 'own_contribution', 'status',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Career quest (document §5)
    |--------------------------------------------------------------------------
    |
    | Stages are chosen by the learner's real grade. The pathway CATALOGUE below
    | is reference data (the same career-cluster vocabulary the content model
    | already uses, config/pal_content.php `career_clusters`); which pathways a
    | given learner is shown is computed from their own RIASEC evidence and
    | mastery — never from this file.
    |
    */
    'career_quest' => [
        'stages' => [
            'explorer' => [
                'key' => 'explorer',
                'label' => 'Explorer quest',
                'grade_min' => 1,
                'grade_max' => 2,
                'quest_message' => 'You are an Explorer. Every day you discover something new.',
                'shows_career_framing' => false,
                'shows_riasec' => false,
                'shows_pathways' => false,
                // Grades 1–2 explore the five HPC domains as "islands".
                'islands' => ['physical', 'socio_emotional', 'cognitive', 'aesthetic', 'positive_learning_habits'],
            ],
            'skill_builder' => [
                'key' => 'skill_builder',
                'label' => 'Skill builder quest',
                'grade_min' => 3,
                'grade_max' => 5,
                'quest_message' => 'You are building the skills that will open doors. Which door interests you most?',
                'shows_career_framing' => true,
                'shows_riasec' => false,
                'shows_pathways' => false,
                'interest_declaration_after_scenarios' => 5,
            ],
            'pathway_seeker' => [
                'key' => 'pathway_seeker',
                'label' => 'Pathway seeker',
                'grade_min' => 6,
                'grade_max' => 8,
                'quest_message' => 'Based on how you learn and what you do well, here are paths that might suit you. They are not locked in — you can explore all of them.',
                'shows_career_framing' => true,
                'shows_riasec' => true,
                'shows_pathways' => true,
                'pathway_suggestions' => 3,
            ],
            'career_builder' => [
                'key' => 'career_builder',
                'label' => 'Career builder',
                'grade_min' => 9,
                'grade_max' => 12,
                'quest_message' => 'You have time to get ready. Here is what the evidence recommends — and you can adjust it as you learn more about yourself.',
                'shows_career_framing' => true,
                'shows_riasec' => true,
                'shows_pathways' => true,
                'pathway_suggestions' => 3,
                'generates_pathway_report' => true,
            ],
        ],

        // The first grade at which a RIASEC profile may be shown at all, and
        // the minimum evidence before one is generated. Below either bar the
        // API returns "not ready yet" rather than a guess.
        'riasec_min_grade' => 5,
        'riasec_min_signals' => 8,
        'riasec_min_distinct_types' => 2,

        'riasec_types' => [
            'R' => ['label' => 'Realistic', 'blurb' => 'you like building, fixing and working with real things'],
            'I' => ['label' => 'Investigative', 'blurb' => 'you love finding out HOW things work'],
            'A' => ['label' => 'Artistic', 'blurb' => 'you think in images, sound and story'],
            'S' => ['label' => 'Social', 'blurb' => 'you learn best with and for other people'],
            'E' => ['label' => 'Enterprising', 'blurb' => 'you like leading, persuading and starting things'],
            'C' => ['label' => 'Conventional', 'blurb' => 'you like order, accuracy and getting the detail right'],
        ],

        /*
        | Pathway catalogue. `riasec` lists the signal types whose evidence
        | counts toward a pathway; `clusters` binds a pathway to the career
        | cluster vocabulary already used by ULUs and content metadata, which is
        | how real learner evidence reaches it. Nothing here is a recommendation
        | — CareerQuestService ranks these using the learner's own signals only.
        */
        'pathways' => [
            'data_science' => [
                'key' => 'data_science',
                'label' => 'Data science & analytics',
                'riasec' => ['I', 'C'],
                'clusters' => ['stem', 'it_ites'],
                'skills_blurb' => 'Numerical reasoning, logical analysis, independent investigation.',
            ],
            'life_sciences' => [
                'key' => 'life_sciences',
                'label' => 'Life sciences & healthcare',
                'riasec' => ['I', 'S'],
                'clusters' => ['health_science', 'stem', 'agriculture'],
                'skills_blurb' => 'Observation, careful method, care for people.',
            ],
            'engineering' => [
                'key' => 'engineering',
                'label' => 'Engineering & technology',
                'riasec' => ['R', 'I'],
                'clusters' => ['stem', 'manufacturing', 'it_ites'],
                'skills_blurb' => 'Modelling, building, systematic problem solving.',
            ],
            'business_finance' => [
                'key' => 'business_finance',
                'label' => 'Business & finance',
                'riasec' => ['E', 'C'],
                'clusters' => ['business_finance', 'government'],
                'skills_blurb' => 'Numeracy, planning, decisions under uncertainty.',
            ],
            'creative_media' => [
                'key' => 'creative_media',
                'label' => 'Creative & media',
                'riasec' => ['A', 'E'],
                'clusters' => ['arts_av', 'hospitality'],
                'skills_blurb' => 'Composition, expression, audience sense.',
            ],
            'education_social' => [
                'key' => 'education_social',
                'label' => 'Education & social work',
                'riasec' => ['S', 'A'],
                'clusters' => ['education', 'government'],
                'skills_blurb' => 'Explanation, empathy, patience with other people\'s thinking.',
            ],
            'skilled_trades' => [
                'key' => 'skilled_trades',
                'label' => 'Skilled trades & manufacturing',
                'riasec' => ['R', 'C'],
                'clusters' => ['manufacturing', 'agriculture', 'hospitality'],
                'skills_blurb' => 'Precision, procedure, working with your hands.',
            ],
        ],

        // How many mastered concepts a pathway counts as "ready" when the
        // estate has no explicit skill map. Overridable per institute later.
        'default_skill_target' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Challenge Mode (document §6) — the ONLY leaderboard in PAL V4
    |--------------------------------------------------------------------------
    */
    'challenge_mode' => [
        'min_grade' => 4,
        'opt_in_required' => true,
        'teacher_can_disable_for_class' => true,
        'affects_bkt_mastery' => false,
        'min_items_to_qualify' => 5,
        'difficulty_min' => 4,
        'difficulty_max' => 5,
        'difficulty_scale_max' => 5,
        'speed_bonus_cap' => 2.0,
        'score_multiplier' => 1000,
        'leaderboard' => [
            'top_n' => 5,
            'reset' => 'weekly',
            'week_starts_on' => 'monday',
            'first_names_only' => true,
            'visible_to_parents' => false,
            'teacher_sees_full_names' => true,
            'removed_on_opt_out' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Celebrations (document §8)
    |--------------------------------------------------------------------------
    */
    'celebration' => [
        // §8.1 — one genuine celebration per session, not one per question.
        'max_per_session' => 1,
        'levels' => [
            'micro' => ['label' => 'Micro', 'trigger' => 'correct_answer', 'treatment' => 'tick', 'duration_ms' => 400],
            'small' => ['label' => 'Small', 'trigger' => 'perfect_practice_set', 'treatment' => 'inline', 'duration_ms' => 1200],
            'medium' => ['label' => 'Medium', 'trigger' => 'tier_mountain', 'treatment' => 'progress', 'duration_ms' => 2000],
            'large' => ['label' => 'Large', 'trigger' => 'tier_sky|badge|personal_best', 'treatment' => 'fullscreen', 'duration_ms' => 2000],
            'milestone' => ['label' => 'Milestone', 'trigger' => 'career_quest|streak_record|challenge_win', 'treatment' => 'session_start_card', 'duration_ms' => 0],
        ],
        'never_celebrate' => [
            'faster_than_classmate',
            'top_of_a_ranking',
            'any_peer_comparison',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Visibility matrix (document §9.1) — enforced server-side
    |--------------------------------------------------------------------------
    |
    | Grain per audience: 'full' | 'summary' | 'current' | 'milestones'
    | 'aggregate' | 'count' | 'per_student' | 'opt_in' | 'own_child' | 'none'.
    | GamificationVisibility reads this to shape every payload; nothing relies
    | on the UI choosing not to render a field.
    |
    */
    'visibility' => [
        'own_mastery' => ['student' => 'full', 'classmate' => 'none', 'teacher' => 'full', 'parent' => 'summary', 'admin' => 'aggregate'],
        'own_badges' => ['student' => 'full', 'classmate' => 'none', 'teacher' => 'full', 'parent' => 'milestones', 'admin' => 'count'],
        'own_streak' => ['student' => 'full', 'classmate' => 'none', 'teacher' => 'full', 'parent' => 'current', 'admin' => 'none'],
        'own_personal_bests' => ['student' => 'full', 'classmate' => 'none', 'teacher' => 'full', 'parent' => 'summary', 'admin' => 'none'],
        'own_career_quest' => ['student' => 'full', 'classmate' => 'none', 'teacher' => 'full', 'parent' => 'full', 'admin' => 'none'],
        'class_aggregate_mastery' => ['student' => 'aggregate', 'classmate' => 'aggregate', 'teacher' => 'per_student', 'parent' => 'none', 'admin' => 'aggregate'],
        'team_challenge_progress' => ['student' => 'aggregate', 'classmate' => 'aggregate', 'teacher' => 'per_student', 'parent' => 'none', 'admin' => 'none'],
        'challenge_mode_scores' => ['student' => 'opt_in', 'classmate' => 'opt_in', 'teacher' => 'full', 'parent' => 'none', 'admin' => 'none'],
        'class_ranking' => ['student' => 'none', 'classmate' => 'none', 'teacher' => 'full', 'parent' => 'none', 'admin' => 'aggregate'],
        'another_students_data' => ['student' => 'none', 'classmate' => 'none', 'teacher' => 'full', 'parent' => 'own_child', 'admin' => 'aggregate'],
        'school_vs_school' => ['student' => 'none', 'classmate' => 'none', 'teacher' => 'aggregate', 'parent' => 'none', 'admin' => 'full'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-gaming safeguards (document §10.3)
    |--------------------------------------------------------------------------
    */
    'safeguards' => [
        'points_currency' => false,
        'badges_are_spendable' => false,
        // A paper far below the learner's demonstrated level does not move
        // mastery or earn badges.
        'exclude_trivial_attempts_below_accuracy_ceiling' => 0.98,
        'exclude_attempts_with_fewer_items_than' => 3,
        'streak_requires_productive_minutes' => 10,
        'peer_teaching_requires_assessment' => true,
        'teacher_may_nullify_badge' => true,
    ],
];
