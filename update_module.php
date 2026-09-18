<?php
use Illuminate\Support\Facades\DB;

$m = DB::table('ai_modules')->where('module_key', 'fees')->first();
echo 'Before: ' . json_encode($m->capabilities) . "\n";

DB::table('ai_modules')
    ->where('module_key', 'fees')
    ->update([
        'capabilities' => json_encode([
            'conversational' => true,
            'generative' => true,
            'agent' => true,
            'workflow' => true,
            'ontology' => false,
        ]),
        'updated_at' => now(),
    ]);

$m = DB::table('ai_modules')->where('module_key', 'fees')->first();
echo 'After: ' . json_encode($m->capabilities) . "\n";