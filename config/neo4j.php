<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Neo4j Database Connection
    |--------------------------------------------------------------------------
    */
    
    'host' => env('NEO4J_HOST', 'localhost'),
    'port' => env('NEO4J_PORT', 7687),
    'username' => env('NEO4J_USERNAME', 'neo4j'),
    'password' => env('NEO4J_PASSWORD', 'neo4j'),
    'uri' => env('NEO4J_URI', 'bolt://localhost:7687'),

    /*
    |--------------------------------------------------------------------------
    | Application writes to Neo4j  (decision RESIDUAL-WRITERS, 2026-08-10)
    |--------------------------------------------------------------------------
    |
    | Master switch for every application-path write to the graph. Defaults to
    | FALSE so the migration rebuild cannot be polluted by live traffic: three
    | routes still write to Neo4j (POST /lms/pal, POST /assessment_question/store,
    | POST /neo4j/assessment) and any of them firing during the rebuild seeds
    | nodes under the old key convention — that is defect D2, reintroduced.
    |
    | Reads are NOT affected. The artisan loader (neo4j:load) bypasses this flag
    | deliberately; it is the one component that is supposed to write.
    |
    | Turn back on at Phase 15 (live sync), not before.
    |
    */
    'writes_enabled' => filter_var(env('NEO4J_WRITES_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
