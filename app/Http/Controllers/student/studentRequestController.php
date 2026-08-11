<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\studentChangeRequestTypeModel;
use App\Models\student\studentRequestModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class studentRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        if (in_array($type, ["API", "JSON"])) {
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }
        $status = $request->input('status');
        $grade_id = $request->input('grade');
        $standard_id = $request->input('standard');
        $division_id = $request->input('division');

        $requests = DB::table('student_change_request as sr')
            ->join('tblstudent as ts', 'ts.id', '=', 'sr.STUDENT_ID')
            ->join('standard as s', 's.id', '=', 'sr.STANDARD_ID')
            ->join('division as d', 'd.id', '=', 'sr.SECTION_ID')
            ->join('student_change_req_type as srt', 'srt.ID', '=', 'sr.CHANGE_REQUEST_ID')
            ->leftJoin('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = sr.STUDENT_ID')
                    ->whereRaw('se.syear = sr.SYEAR');
            })
            ->leftJoin('academic_section as g', 'g.id', '=', 'se.grade_id')
            ->selectRaw("sr.ID, sr.STATUS, sr.REASON, sr.DESCRIPTION, sr.PROOF_OF_DOCUMENT,
                sr.CREATED_ON, sr.DECIDED_BY, sr.DECIDED_ON,
                ts.enrollment_no, CONCAT_WS(' ', ts.first_name, ts.last_name) AS student_name,
                ts.father_name, s.name AS standard, d.name AS division, g.title AS grade,
                srt.REQUEST_TITLE AS request_title,
                if(ts.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',ts.image)) as student_image")
            ->where('ts.sub_institute_id', $sub_institute_id)
            ->where('sr.SYEAR', $syear)
            ->when($status != '', function ($query) use ($status) {
                $query->where('sr.STATUS', $status);
            })
            ->when($grade_id != '', function ($query) use ($grade_id) {
                $query->where('se.grade_id', $grade_id);
            })
            ->when($standard_id != '', function ($query) use ($standard_id) {
                $query->where('sr.STANDARD_ID', $standard_id);
            })
            ->when($division_id != '', function ($query) use ($division_id) {
                $query->where('sr.SECTION_ID', $division_id);
            })
            ->orderByDesc('sr.CREATED_ON')
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $requests;
        $res['total'] = $requests->count();
        $res['decided'] = $requests->where('STATUS', '!=', 'Pending')->count();

        return is_mobile($type, "student/student_request", $res, "view");
    }

    /**
     * Fixed, non-breaking variant of index(): resolves Grade by joining
     * tblstudent_enrollment on student_id + syear + standard_id (matching
     * student_change_request's own stored standard_id/syear) and reading
     * grade_id from that enrollment record, instead of the unfiltered
     * student_id+syear-only join index() uses.
     * Original index() is untouched.
     *
     * @return Response
     */
    public function indexFixed(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        if (in_array($type, ["API", "JSON"])) {
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }
        $status = $request->input('status');
        $grade_id = $request->input('grade');
        $standard_id = $request->input('standard');
        $division_id = $request->input('division');

        $requests = DB::table('student_change_request as sr')
            ->join('tblstudent as ts', 'ts.id', '=', 'sr.STUDENT_ID')
            ->join('standard as s', 's.id', '=', 'sr.STANDARD_ID')
            ->join('division as d', 'd.id', '=', 'sr.SECTION_ID')
            ->join('STUDENT_CHANGE_REQ_TYPE as srt', 'srt.ID', '=', 'sr.CHANGE_REQUEST_ID')
            ->leftJoin('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = sr.STUDENT_ID')
                    ->whereRaw('se.syear = sr.SYEAR')
                    ->whereRaw('se.standard_id = sr.STANDARD_ID');
            })
            ->leftJoin('academic_section as g', 'g.id', '=', 'se.grade_id')
            ->selectRaw("sr.ID, sr.STATUS, sr.REASON, sr.DESCRIPTION, sr.PROOF_OF_DOCUMENT,
                sr.CREATED_ON, sr.DECIDED_BY, sr.DECIDED_ON,
                ts.enrollment_no, CONCAT_WS(' ', ts.first_name, ts.last_name) AS student_name,
                ts.father_name, s.name AS standard, d.name AS division, g.title AS grade,
                srt.REQUEST_TITLE AS request_title,
                if(ts.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',ts.image)) as student_image")
            ->where('ts.sub_institute_id', $sub_institute_id)
            ->where('sr.SYEAR', $syear)
            ->when($status != '', function ($query) use ($status) {
                $query->where('sr.STATUS', $status);
            })
            ->when($grade_id != '', function ($query) use ($grade_id) {
                $query->where('se.grade_id', $grade_id);
            })
            ->when($standard_id != '', function ($query) use ($standard_id) {
                $query->where('sr.STANDARD_ID', $standard_id);
            })
            ->when($division_id != '', function ($query) use ($division_id) {
                $query->where('sr.SECTION_ID', $division_id);
            })
            ->orderByDesc('sr.CREATED_ON')
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $requests;
        $res['total'] = $requests->count();
        $res['decided'] = $requests->where('STATUS', '!=', 'Pending')->count();

        return is_mobile($type, "student/student_request", $res, "view");
    }

    /**
     * Approve or reject a student change request.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function updateStatus(Request $request, $id)
    {
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');
        if (in_array($type, ["API", "JSON"])) {
            $user_id = $request->user_id;
        }
        $status = $request->input('status');

        if (!in_array($status, ['Approved', 'Rejected'])) {
            $res['status_code'] = 0;
            $res['message'] = "Invalid status";

            return is_mobile($type, "student_request.index", $res);
        }

        $updated = studentRequestModel::where('ID', $id)->update([
            'STATUS' => $status,
            'DECIDED_BY' => $user_id,
            'DECIDED_ON' => now(),
        ]);

        if (!$updated) {
            $res['status_code'] = 0;
            $res['message'] = "Request not found";

            return is_mobile($type, "student_request.index", $res);
        }

        $res['status_code'] = 1;
        $res['message'] = "Request " . strtolower($status);

        return is_mobile($type, "student_request.index", $res);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        if (in_array($type, ["API", "JSON"])) {
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $student_data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear);

        $request_type_data = studentChangeRequestTypeModel::where([
            'SUB_INSTITUTE_ID' => $sub_institute_id, 'SYEAR' => $syear,
        ])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $student_data;
        $res['request_type_data'] = $request_type_data;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;

        return is_mobile($type, "student/student_request", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        if (in_array($type, ["API", "JSON"])) {
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_id = $request->user_id;
        }
        $student_request = $request->input('student_request');
        $CHANGE_REQUEST_IDS = $request->input('CHANGE_REQUEST_IDS');
        $PROOF_OF_DOCUMENTS = $request->input('PROOF_OF_DOCUMENTS');
        $REASONS = $request->input('REASONS');
        $DESCRIPTIONS = $request->input('DESCRIPTIONS');
        $STANDARD_IDS = $request->input('STANDARD_IDS');
        $SECTION_IDS = $request->input('SECTION_IDS');

        if ($student_request == '') {
            $res['status_code'] = 0;
            $res['message'] = "Please select student to proceed";

            return is_mobile($type, "student_request.index", $res);
        }

        foreach ($student_request as $key => $student_id) {
            $studentRequest['STUDENT_ID'] = $student_id;
            $studentRequest['SYEAR'] = $syear;
            $studentRequest['SUB_INSTITUTE_ID'] = $sub_institute_id;
            $studentRequest['CHANGE_REQUEST_ID'] = $CHANGE_REQUEST_IDS[$student_id] ?? '';
            $studentRequest['REASON'] = $REASONS[$student_id];
            $studentRequest['PROOF_OF_DOCUMENT'] = $PROOF_OF_DOCUMENTS[$student_id] ?? '';
            $studentRequest['DESCRIPTION'] = $DESCRIPTIONS[$student_id];
            $studentRequest['STANDARD_ID'] = $STANDARD_IDS[$student_id];
            $studentRequest['SECTION_ID'] = $SECTION_IDS[$student_id];
            $studentRequest['CREATED_BY'] = $user_id;

            studentRequestModel::insert($studentRequest);
        }

        $res['status_code'] = 1;
        $res['message'] = "Student Request Successfully Added";

        return is_mobile($type, "student_request.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }
}
