<?php

return [
    'mapping_types' => [
        // Parent mapping type name in lms_mapping_type for pedagogy mappings.
        'pedagogy' => env('PAL_PEDAGOGY_MAPPING_TYPE', 'Pedagogy'),
    ],

    'eso' => [
        /*
        |----------------------------------------------------------------
        | Graph-backed prerequisite gate (D2)
        |----------------------------------------------------------------
        |
        | Default OFF. When true, EsoPolicyService::unmetPrerequisiteConceptIds()
        | consults CoherenceMapRepository::rootBlockers() (full transitive
        | closure over the Neo4j-projected prerequisite graph) before falling
        | back to its own single-hop SQL check. The fallback is not a hedge —
        | it fires on every scope the graph has not projected yet, not just
        | on error, so this flag is safe to enable globally without knowing
        | in advance which tenants/subjects have a complete graph.
        */
        'graph_prerequisite_gate' => filter_var(env('PAL_ESO_GRAPH_PREREQUISITE_GATE', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
