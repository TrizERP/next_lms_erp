<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use App\Models\fees\map_year\map_year;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class feesStructureReportController extends Controller
{
    use GetsJwtToken;

    public function feesStructureReportIndex(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = 'Success';

        return is_mobile($type, 'fees/fees_report/show_fees_structure_report', $res, 'view');
    }

    public function feesStructureReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $grade = $request->input('grade');
        $standard = $request->input('standard');

        if (in_array($type, ['API', 'JSON'])) {
            try {
                if (!$this->jwtToken()->validate()) {
                    return response()->json([
                        'status' => 2,
                        'message' => 'Token Auth Failed',
                        'data' => [],
                    ], 401);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 2,
                    'message' => $e->getMessage(),
                    'data' => [],
                ], 401);
            }

            $sub_institute_id = $request->input('sub_institute_id', $sub_institute_id);
            $syear = $request->input('syear', $syear);
        }

        if (empty($sub_institute_id) || empty($syear)) {
            $res['status_code'] = 0;
            $res['status'] = 0;
            $res['message'] = 'Session expired. Please sign in again.';
            $res['months_arr'] = [];
            $res['grade_id'] = $grade;
            $res['standard_id'] = $standard;
            $res['report_data'] = [];
            $res['data'] = [];

            return is_mobile($type, 'fees/fees_report/show_fees_structure_report', $res, 'view');
        }

        $mapYear = map_year::where([
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
        ])->first();

        if (!$mapYear) {
            $res['status_code'] = 1;
            $res['status'] = 1;
            $res['message'] = 'No fee structure is mapped for the selected Section and Standard.';
            $res['months_arr'] = [];
            $res['grade_id'] = $grade;
            $res['standard_id'] = $standard;
            $res['report_data'] = [];
            $res['data'] = [];

            return is_mobile($type, 'fees/fees_report/show_fees_structure_report', $res, 'view');
        }

        $start_month = (int) ($mapYear->from_month ?? 0);
        $months = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
        $months_arr = [];
        $monthYear = (int) $syear;

        if ($start_month > 0) {
            for ($i = 1; $i <= 12; $i++) {
                $months_arr[$start_month . $monthYear] = ($months[$start_month] ?? '') . '/' . $monthYear;
                if ($start_month == 12) {
                    $start_month = 0;
                    $monthYear++;
                }
                $start_month++;
            }
        }

        $std_result = DB::table('standard as s')
            ->selectRaw('*,s.name AS standard_name')
            ->where('s.sub_institute_id', $sub_institute_id);

        if ($grade !== null && $grade !== '') {
            $std_result = $std_result->where('s.grade_id', $grade);
        }
        if ($standard !== null && $standard !== '') {
            $std_result = $std_result->where('s.id', $standard);
        }
        $std_result = $std_result->get()->toArray() ?? [];

        if (empty($std_result)) {
            $res['status_code'] = 1;
            $res['status'] = 1;
            $res['message'] = 'No fee structure records found for the selected Section and Standard.';
            $res['months_arr'] = $months_arr;
            $res['grade_id'] = $grade;
            $res['standard_id'] = $standard;
            $res['report_data'] = [];
            $res['data'] = [];

            return is_mobile($type, 'fees/fees_report/show_fees_structure_report', $res, 'view');
        }

        $quota_result = DB::table('student_quota as q')
            ->selectRaw('*,q.title AS quota_name')
            ->where('q.sub_institute_id', $sub_institute_id)
            ->get()
            ->toArray() ?? [];

        $final_data = [];

        foreach (($std_result ?? []) as $val) {
            foreach (($quota_result ?? []) as $qval) {
                $amt_query = "SELECT sum(amount) as new_amount,month_id FROM fees_breackoff
                WHERE sub_institute_id = ?
                AND standard_id = ? and quota= ? and admission_year = ?
                AND syear = ?
                GROUP BY quota,month_id";
                $amt_result = DB::select($amt_query, [$sub_institute_id, $val->id, $qval->id, $syear, $syear]);
                $amt = json_decode(json_encode($amt_result), true) ?: [];
                foreach (($amt ?? []) as $aval) {
                    $monthId = $aval['month_id'] ?? null;
                    if ($monthId === null) {
                        continue;
                    }
                    $final_data[$val->standard_name][$qval->quota_name]['NEW'][$monthId] = $aval['new_amount'] ?? 0;
                }

                $old_year = (int) $syear - 1;
                $old_amt_query = "SELECT sum(amount) as new_amount,month_id FROM fees_breackoff
                WHERE sub_institute_id = ?
                AND standard_id = ? and quota= ? and admission_year = ?
                AND syear = ?
                GROUP BY quota,month_id";
                $old_amt_result = DB::select($old_amt_query, [$sub_institute_id, $val->id, $qval->id, $old_year, $syear]);
                $old_amt = json_decode(json_encode($old_amt_result), true) ?: [];
                foreach (($old_amt ?? []) as $oval) {
                    $monthId = $oval['month_id'] ?? null;
                    if ($monthId === null) {
                        continue;
                    }
                    $final_data[$val->standard_name][$qval->quota_name]['OLD'][$monthId] = $oval['new_amount'] ?? 0;
                }
            }
        }

        $res['status_code'] = 1;
        $res['status'] = 1;
        $res['message'] = empty($final_data)
            ? 'No fee structure is mapped for the selected Section and Standard.'
            : 'Success';
        $res['months_arr'] = $months_arr;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['report_data'] = $final_data;
        $res['data'] = $final_data;

        return is_mobile($type, 'fees/fees_report/show_fees_structure_report', $res, 'view');
    }
}
