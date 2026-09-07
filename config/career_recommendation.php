<?php

/**
 * Knowledge-Based Career Recommendation Engine — all thresholds here are
 * deliberately the ONLY place a numeric cutoff lives. Nothing in
 * KnowledgeMatchService / AlternativeOccupationRecommender /
 * CareerRecommendationExplainer hardcodes a threshold, occupation code, or
 * knowledge mapping — see each class's own doc comment.
 */
return [

    /**
     * matchPercentage (0-100) divided by 100 must be >= this fraction for an
     * aspiration to be considered ALIGNED. Below it: MISALIGNED.
     */
    'alignment_threshold' => (float) env('CAREER_KNOWLEDGE_ALIGNMENT_THRESHOLD', 0.5),

    /**
     * How many alternative occupations AlternativeOccupationRecommender
     * returns from a full-catalog ranking.
     */
    'top_matches' => (int) env('CAREER_KNOWLEDGE_TOP_MATCHES', 5),

    /**
     * Minimum Jaccard token-overlap score (0-1) for a student knowledge item
     * and an occupation knowledge item to be considered a match at all.
     * Guards against a single short/common token producing a spurious match.
     */
    'min_match_score' => (float) env('CAREER_KNOWLEDGE_MIN_MATCH_SCORE', 0.15),

    /**
     * Tokens shorter than this (after normalization) are dropped before
     * overlap scoring — generic noise reduction, not a per-subject rule.
     */
    'min_token_length' => (int) env('CAREER_KNOWLEDGE_MIN_TOKEN_LENGTH', 3),

    /**
     * Presentation-layer alignment bands (AlignmentBandClassifier) — purely
     * an interpretation of the existing matchPercentage, never a second
     * scoring pass. matchPercentage/100 >= strong_match_threshold => "Strong
     * Match"; >= partial_match_threshold (and below strong) => "Partial
     * Match"; below that => "Weak Match". Independent of alignment_threshold
     * above, which still drives the existing binary ALIGNED/MISALIGNED field.
     */
    'strong_match_threshold' => (float) env('CAREER_KNOWLEDGE_STRONG_THRESHOLD', 0.75),
    'partial_match_threshold' => (float) env('CAREER_KNOWLEDGE_PARTIAL_THRESHOLD', 0.5),

    /** Max entries in "Related Careers With Better Alignment". */
    'related_careers_limit' => (int) env('CAREER_KNOWLEDGE_RELATED_CAREERS_LIMIT', 5),

    /** Max knowledge domain names shown per related occupation. */
    'top_matched_domains_limit' => (int) env('CAREER_KNOWLEDGE_TOP_MATCHED_DOMAINS_LIMIT', 3),
];
