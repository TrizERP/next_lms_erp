<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class AgeWiseReportController extends Controller
{
    public function index(Request $request)
    {
        $gradeId = $request->get('grade_id', $request->get('grade', 0));
        $standardId = $request->get('standard', 0);
        $divisionId = $request->get('division', 0);
        $type = $request->get('type');
        $sub_institute_id = session()->get('sub_institute_id', 0);
        $syear = session()->get('syear');
        if (in_array($type, ["API", "JSON"])) {
            $sub_institute_id = $request->get('sub_institute_id', 0);
            $syear = $request->get('syear');
        }
        $medium = $request->get('medium', 'PRIMARY');

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

        if ($standardId && $standardId > 0) {
            $classesQuery->where('id', $standardId);
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
                'standard_id' => $standardId,
                'division_id' => $divisionId,
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
                DB::raw("IFNULL(TIMESTAMPDIFF(YEAR, s.dob, CURDATE()), 0) AS age"),
                'e.standard_id as class_id',
                's.gender',
                DB::raw('count(*) as total')
            )
            ->where('e.syear', $syear)
            ->whereIn('e.standard_id', $classIds)
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('e.end_date', null)
            ->whereNotNull('s.gender')
            ->where('s.gender', '!=', '')
            ->when($divisionId && $divisionId > 0, function ($query) use ($divisionId) {
                $query->where('e.section_id', $divisionId);
            })
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
            //$gender = strtoupper(substr($r->gender ?? '', 0, 1));
            $gender = preg_replace('/[^A-Za-z]/', '', $r->gender ?? '');
            $gender = strtoupper(substr($gender, 0, 1));

            if (!isset($classes[$classId])) continue;

            if ($ageInt <= 5) {
                $ageKey = '≤5';
            } elseif ($ageInt >= $maxAge) {
                $ageKey = $maxAge . '+';
            } else {
                $ageKey = (string)$ageInt;
            }

            $className = $classes[$classId];
            //$report[$ageKey][$className][$gender] += (int)$r->total;
            $report[$ageKey][$className][$gender] = ($report[$ageKey][$className][$gender] ?? 0) + (int)$r->total;
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
            'standard_id' => $standardId,
            'division_id' => $divisionId,
            'sub_institute_id' => $sub_institute_id,
        ];

        $type = $request->type;
        return is_mobile($type, "student/agewise", $data, "view");
    }
}
