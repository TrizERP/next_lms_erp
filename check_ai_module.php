<?php
use Illuminate\Support\Facades\DB;
$m = DB::table('ai_modules')->where('module_key', 'fees')->first();
foreach ((array) $m as $k => $v) echo sprintf('%-20s %s\n', $k, mb_substr((string)$v, 0, 300));