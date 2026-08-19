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

    /*
    |--------------------------------------------------------------------------
    | Live application -> graph sync  (Phase 15)
    |--------------------------------------------------------------------------
    |
    | Deliberately a SEPARATE switch from `writes_enabled` above. That flag
    | gates the three *legacy* writer routes (POST /lms/pal,
    | POST /assessment_question/store, POST /neo4j/assessment), which still key
    | nodes under the pre-migration convention — turning it on reintroduces
    | defect D2. This flag gates only the App\Services\Graph projections, which
    | write the keys the live graph actually uses.
    |
    | Leave `writes_enabled` false and this true: new application data reaches
    | the graph, the legacy writers stay muted.
    |
    */
    'sync_enabled' => filter_var(env('NEO4J_SYNC_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | When a projection throws (Neo4j down, bolt timeout), record the entity in
    | the `neo4j_sync_outbox` table so `php artisan neo4j:sync-drain` can retry.
    | The originating HTTP request NEVER fails because of a graph error.
    */
    'outbox_enabled' => filter_var(env('NEO4J_OUTBOX_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Max retries the drain command will make before marking a row 'failed'.
    */
    'outbox_max_attempts' => (int) env('NEO4J_OUTBOX_MAX_ATTEMPTS', 5),
];
