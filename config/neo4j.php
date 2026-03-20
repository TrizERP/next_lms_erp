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
];
