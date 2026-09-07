<?php

namespace App\Http\Controllers\api\HRITDashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\ResolvesApiIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobroleApiController extends Controller
{
    use ResolvesApiIdentity;

    public function getDepartmentWise(Request $request)
{
    $subInstituteId = $this->apiTenantId($request);
    $departmentId   = $request->department_id;

    if (!$subInstituteId) {
        return response()->json([
            "status" => false,
            "message" => "sub_institute_id is required"
        ], 400);
    }

    // Base query
    $query = DB::table('s_user_jobrole AS j')
        ->leftJoin('hrms_departments AS d', 'j.department_id', '=', 'd.id')
        ->where('j.sub_institute_id', $subInstituteId)
        ->select(
            'j.id',
            'j.jobrole',
            'j.description',
            'j.jobrole_category',
            'j.department_id',
            'd.department AS department_name'
        );

    // Department filter
    if ($departmentId) {
        $query->where('j.department_id', $departmentId);
    }

    $records = $query
        ->orderBy('d.department', 'ASC')
        ->orderBy('j.jobrole', 'ASC')
        ->get();

    // If department_id given → return simplified list + department_name
    if ($departmentId) {

        // Fetch department name for top level
        $departmentName = DB::table('hrms_departments')
            ->where('id', $departmentId)
            ->value('department');

        return response()->json([
            "status" => true,
            "department_id" => $departmentId,
            "department_name" => $departmentName,
            "data" => $records
        ]);
    }

    // Else group by department_name
    $grouped = $records->groupBy('department_name');

    return response()->json([
        "status" => true,
        "data" => $grouped
    ]);
}
}
