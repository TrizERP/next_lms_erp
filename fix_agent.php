<?php
use Illuminate\Support\Facades\DB;

DB::table('ai_agents')
    ->where('agent_key', 'k12_fees')
    ->update([
        'allowed_signal_keys' => json_encode(['fee_arrears']),
        'updated_at' => now(),
    ]);

echo 'Updated agent manifest';