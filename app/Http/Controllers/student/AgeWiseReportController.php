<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class AgeWiseReportController extends Controller
{
    public function index(Request $request)
    {
        $gradeId = $request->get('grade_id', $request->get('grade', 0));
        $sub_institute_id = session()->get('sub_institute_id', 0);
        $medium = $request->get('medium', 'PRIMARY');
        $syear = session()->get('syear');

        // Age ranges
        $minAge = 5;
        $maxAge = 21;

        // Classes list
        $classesQuery = DB::table('standard')
            ->select('id', 'name', 'short_name')
            ->where('sub_institute_id', $sub_institute_id)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');

        if ($gradeId && $gradeId > 0) {
            $classesQuery->where('grade_id', $gradeId);
        }

        $classRecords = $classesQuery->get();
        $classes = [];
        foreach ($classRecords as $class) {
            $classes[$class->id] = $class->short_name ?: $class->name;
        }

        if (empty($classes)) {
            $data = [
                'report' => [],
                'classes' => [],
                'grandTotal' => 0,
                'medium' => $medium,
                'minAge' => $minAge,
                'maxAge' => $maxAge,
                'grade_id' => $gradeId,
                'sub_institute_id' => $sub_institute_id,
            ];
            $type = $request->type;
            return is_mobile($type, "student/agewise", $data, "view");
        }

        $classIds = array_keys($classes);

        // Students data with syear filter
        $rows = DB::table('tblstudent_enrollment as e')
            ->join('tblstudent as s', 's.id', '=', 'e.student_id')
            ->select(
                DB::raw("TIMESTAMPDIFF(YEAR, s.dob, CURDATE()) as age"),
                'e.standard_id as class_id',
                's.gender',
                DB::raw('count(*) as total')
            )
            ->where('e.syear', $syear)
            ->whereIn('e.standard_id', $classIds)
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('e.end_date', null)
            ->groupBy('age', 'e.standard_id', 's.gender')
            ->orderBy('age')
            ->get();

        // Report matrix
        $report = [];
        $grandTotal = 0;

        // Init structure
        $report['≤5'] = [];
        for ($age = 6; $age < $maxAge; $age++) {
            $report[(string)$age] = [];
        }
        $report[$maxAge . '+'] = [];

        foreach ($report as $ageKey => &$classesArr) {
            foreach ($classes as $classId => $className) {
                $classesArr[$className] = ['M' => 0, 'F' => 0];
            }
        }

        // Fill data
        foreach ($rows as $r) {
            $ageInt = (int)$r->age;
            $classId = $r->class_id;
            $gender = strtoupper(substr($r->gender ?? '', 0, 1));

            if (!isset($classes[$classId])) continue;

            if ($ageInt <= 5) {
                $ageKey = '≤5';
            } elseif ($ageInt >= $maxAge) {
                $ageKey = $maxAge . '+';
            } else {
                $ageKey = (string)$ageInt;
            }

            $className = $classes[$classId];
            $report[$ageKey][$className][$gender] += (int)$r->total;
            $grandTotal += (int)$r->total;
        }

        $data = [
            'report' => $report,
            'classes' => array_values($classes),
            'grandTotal' => $grandTotal,
            'medium' => $medium,
            'minAge' => $minAge,
            'maxAge' => $maxAge,
            'grade_id' => $gradeId,
            'sub_institute_id' => $sub_institute_id,
        ];

        $type = $request->type;
        return is_mobile($type, "student/agewise", $data, "view");
    }
}
