<?php

return [
    /**
     * Diagnostic Level Thresholds
     *
     * These thresholds determine the diagnostic level based on percentage score.
     * They align with the legacy PAL quiz difficulty bands (40/70) and add
     * an advanced tier at 85 to match the four badges rendered in the result blade.
     */
    'level_thresholds' => [
        'beginner'   => 0,    // < 40%
        'developing' => 40,   // 40-69%
        'proficient' => 70,   // 70-84%
        'advanced'   => 85,   // >= 85%
    ],

    /**
     * Per-concept band thresholds (reuses overall level thresholds)
     *
     * weak      => < 40%
     * moderate  => 40-69%
     * strong    => >= 70%
     */
    'concept_band_thresholds' => [
        'weak'      => 0,
        'moderate'  => 40,
        'strong'    => 70,
    ],

    /**
     * Diagnostic paper configuration
     */
    'paper' => [
        'questions_per_band' => 5,  // 5 easy, 5 medium, 5 hard = 15 total
        'min_viable_questions' => 9, // Below this, a percentage is not a measurement
    ],

    /**
     * Time limit for diagnostic (in minutes)
     */
    'time_limit_minutes' => 30,

    /**
     * Recency filter: how many recent attempts to exclude questions from
     */
    'recency_attempts' => 2,

    /**
     * Concept mastery ladder (App\Services\PAL\Questions\MasteryLadder)
     *
     * A band counts as cleared only when BOTH bars are met - accuracy alone is
     * cleared by one lucky answer, attempts alone by a long run of wrong ones.
     *
     * These defaults are the values AdaptiveLearningService has always applied
     * to the hard band (MASTERY_MIN_HARD / MASTERY_ACCURACY), so adopting the
     * ladder does not move the bar for anyone already practising.
     *
     * The ladder itself runs only over bands that HAVE questions for the
     * concept - see the class docblock for why a literal three-band gate would
     * strand about 70% of concepts.
     */
    'mastery' => [
        'min_attempts' => 5,
        'min_accuracy' => 80.0,
    ],
];