<?php
use Illuminate\Support\Facades\DB;
$t = DB::table('ai_templates')->where('template_key', 'k12.fees.pending_summary')->first();
foreach ((array) $t as $k => $v) echo sprintf('%-20s %s\n', $k, mb_substr((string)$v, 0, 200));