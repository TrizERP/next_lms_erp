<?php

/**
 * Std-10 chapter/concept remap configuration.
 *
 * Every threshold here is a DEFAULT. `remap:calibrate` overwrites the
 * entries under `thresholds` and `lexical.reference` from measured
 * control-set performance and persists them on the run row, so a run
 * never silently depends on a hand-picked constant.
 */
return [

    // ---------------------------------------------------------------
    // Scope. Nothing outside this is ever read or written.
    // ---------------------------------------------------------------
    'scope' => [
        'sub_institute_id'    => 1,
        'standard_id'         => 43,   // Class 10
        'chapter_syear'       => 2026, // the re-seeded chapter set
        'science_subject_id'  => 3975, // control set, excluded from chapter remap
        'maths_subject_id'    => 3976, // the only subject with real chapter titles
        'science_chapter_ids' => [1012, 1024], // inclusive range, calibration control
    ],

    // ---------------------------------------------------------------
    // Lexical ranking (BM25 + bonuses). No LLM involved.
    // ---------------------------------------------------------------
    'lexical' => [
        'k1'                 => 1.2,
        'b'                  => 0.75,
        'shortlist_per_tier' => 5,
        'shortlist_max'      => 10,
        'min_token_len'      => 3,
        'same_subject_prior' => 0.05, // also a fraction of top raw score
        // Bonuses are expressed as FRACTIONS OF THE TOP RAW BM25 SCORE
        // for the group, not absolute points. Raw scores range from ~60
        // to ~900 depending on how much text a legacy group has, so an
        // absolute bonus is either meaningless or overwhelming.
        'exact_hit_bonus'    => 0.08,
        'exact_hit_cap'      => 0.24,
        'bigram_bonus'       => 0.03,
        'bigram_cap'         => 0.15,
        // A cross-subject candidate must beat the best same-subject
        // candidate by more than this fraction before the move is even
        // proposed. Cross-subject moves are wanted, but they need real
        // evidence rather than a marginally higher noise score.
        'cross_subject_margin' => 0.15,
        'jaccard_min'        => 0.8,
        'similar_text_min'   => 0.85,
        // Divisor for lex_norm. Replaced by remap:calibrate with the median
        // BM25 of correct matches on the Science control set.
        'reference'          => 12.0,
        // A profile with more than this share of Devanagari characters only
        // considers cross-subject candidates that are also Devanagari-heavy.
        'script_gate'        => 0.30,
        // A term counts as topical for concept matching when it appears
        // in at most this share of the 115 chapters.
        'concept_term_df_share' => 0.10,
    ],

    // ---------------------------------------------------------------
    // Decision banding.
    // ---------------------------------------------------------------
    'thresholds' => [
        'auto_apply'             => 0.70,
        'low_conf_floor'         => 0.55,
        'thin_profile'           => 0.75,
        'thin_profile_items'     => 3,
        'thin_profile_chars'     => 200,
        'min_matched_concepts'   => 2,
        'syllabus_coverage_drop' => 0.25, // below this => out-of-syllabus candidate
        'duplicate_dice'         => 0.90,
        'duplicate_group_share'  => 0.30,
        'calibration_floor'      => 0.90, // control accuracy below this aborts the run
        'loo_recall_floor'       => 0.80, // per-subject recall@8 below this widens shortlist
        'concept_agreement_floor'=> 0.85,
        'concept_dominance_flag' => 0.60,
        'concept_min_margin'     => 0.15,
    ],

    // ---------------------------------------------------------------
    // Composite score weights. Must be interpreted together with the
    // banding table; changing these invalidates calibration.
    // ---------------------------------------------------------------
    'weights' => [
        'lex_norm'       => 0.35,
        'lex_margin'     => 0.15,
        'adjudicator'    => 0.25,
        'verifier'       => 0.25,
        'duplicate'      => 0.15,
        'same_subject'   => 0.05,
    ],

    // ---------------------------------------------------------------
    // Decision source.
    //
    // This pipeline uses NO LLM API. Chapter decisions are authored
    // offline as a reviewed crosswalk file and loaded as data, so a run
    // is deterministic, free, and reproducible. See database/remap/.
    // ---------------------------------------------------------------
    'crosswalk_file' => 'database/remap/crosswalk_std10.php',

    // ---------------------------------------------------------------
    // Batching.
    // ---------------------------------------------------------------
    'batch' => [
        'chunk'          => 500, // rows per apply transaction
        'concept_batch'  => 10,  // questions per concept-assignment LLM call
        'profile_sample' => 40,  // max text items sampled per legacy profile
        'prompt_items'   => 15,  // representative items shown to the adjudicator
        'snippet_chars'  => 200,
    ],

    // ---------------------------------------------------------------
    // Entities the apply path may touch, and how each is keyed.
    // `soft_delete_column` = null means v1 refuses to soft-delete that
    // entity (semantics unproven) and reports needs_review instead.
    // ---------------------------------------------------------------
    'entities' => [
        'questions' => [
            'table'              => 'lms_question_master',
            'soft_delete_column' => 'deleted_at',
            'snapshot_columns'   => ['id', 'subject_id', 'chapter_id', 'concept_id', 'concept', 'subconcept', 'topic_id', 'deleted_at'],
            // Columns this pipeline is allowed to write. Gate 6 demands
            // an exact audit match for these; drift in any other
            // snapshot column is reported as external activity, since
            // this is a shared database with other users on it.
            'owned_columns'      => ['subject_id', 'chapter_id', 'concept_id', 'concept', 'topic_id', 'deleted_at'],
        ],
        'content' => [
            'table'              => 'content_master',
            'soft_delete_column' => null, // show_hide semantics unproven; preflight probes
            'snapshot_columns'   => ['id', 'subject_id', 'chapter_id', 'concept_id', 'show_hide'],
            'owned_columns'      => ['subject_id', 'chapter_id'],
        ],
        'teacher_resource' => [
            'table'              => 'lms_teacher_resource',
            'soft_delete_column' => null, // status varchar(5) semantics unproven
            'snapshot_columns'   => ['id', 'subject_id', 'chapter_id', 'status'],
            'owned_columns'      => ['subject_id', 'chapter_id'],
        ],
    ],

    // Populate lms_question_master.concept varchar alongside concept_id?
    // Only enabled if preflight measures the Science precedent as >= 90%
    // consistent with lms_concept.name. Default off.
    'write_concept_varchar' => false,

    'storage_dir' => 'remap',
];
