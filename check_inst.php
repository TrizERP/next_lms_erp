<?php
use Illuminate\Support\Facades\DB;

$inst = 61;
foreach ([2023, 2024, 2025, 2026] as $yr) {
    $q = DB::table('fees_breakoff_other')->where('sub_institute_id', $inst)->where('syear', $yr);
    $rows = (clone $q)->count();
    $students = (clone $q)->distinct('student_id')->count('student_id');
    echo "inst=$inst syear=$yr rows=$rows students=$students\n";
}

echo "\nChecking fees_collect for inst 61:\n";
foreach (DB::table('fees_collect')->where('sub_institute_id', 61)->selectRaw('syear, count(*) n')->groupBy('syear')->get() as $r) {
    echo "  syear=$r->syear rows=$r->n\n";
}