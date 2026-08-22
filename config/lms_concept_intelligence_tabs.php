<?php

/**
 * Course Master -> Concept Intelligence — the shipped tab catalogue.
 *
 * Ground truth for which tabs exist, what order they appear in, and what each
 * one is called before any institute renames it. Per-institute renames live in
 * `lms_concept_intelligence_tab_labels` and are merged over the top on read
 * (ConceptIntelligenceTabLabelApiController).
 *
 * The keys are contract: they match the tab ids in the Next.js
 * ConceptIntelligenceTabs component and must not be renamed. Changing a value
 * here changes the default for every institute that has not overridden it.
 */
return [

    // Longest label an institute may store. Kept in step with the
    // custom_label column width so the API rejects rather than truncates.
    'max_label_length' => 120,

    'tabs' => [
        'overview'      => 'Overview',
        'knowledge'     => 'Knowledge',
        'abilities'     => 'Abilities',
        'skills'        => 'Skills',
        'competencies'  => 'Competencies',
        'blooms'        => "Bloom's",
        'dok'           => 'DOK',
        'prerequisites' => 'Prerequisites',
        'misconceptions' => 'Misconceptions',
        'realworld'     => 'Real World',
        'pedagogy'      => 'Pedagogy',
        'objectives'    => 'Objectives',
        'outcomes'      => 'Outcomes',
        'blueprint'     => 'Blueprint',
        'rubrics'       => 'Rubrics',
        'relationships' => 'Relationships',
        'evidence'      => 'Evidence',
        'reasoning'     => 'AI Reasoning',
    ],
];
