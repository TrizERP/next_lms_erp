<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\classteacherModel;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class classteacherController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;
        $res['academic_section_id'] = '';
        $res['standard_id'] = '';
        $res['division_id'] = '';
        $res['teacher_data'] = $this->getusers($request);
        $res['button'] = "Add";

        return is_mobile($type, 'school_setup/show_classteacher', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');
        return classteacherModel::from("class_teacher as ct")
            ->select('ct.*', 'a.title as academic_section_name', 's.name as standard_name', 'd.name as division_name',
                DB::raw('concat(u.first_name," ",u.middle_name," ",u.last_name) as teacher_name')
            )
            ->join('academic_section as a', 'a.id', '=', 'ct.grade_id')
            ->join('standard as s',function($join) use($marking_period_id){
                $join->on( 's.id', '=', 'ct.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division as d', 'd.id', '=', 'ct.division_id')
            ->join('tbluser as u', 'u.id', '=', 'ct.teacher_id')
            ->where(['ct.sub_institute_id' => $sub_institute_id, 'ct.syear' => $syear])
            ->orderBy('a.sort_order')
            ->orderBy('s.sort_order')
            ->orderBy('d.name')
            ->get();
    }

    public function getusers(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return tbluserModel::select('tbluser.*',
            DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as teacher_name'))
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', "=", 'tbluser.user_profile_id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher', 'tbluser.status' => 1])
            ->orderby('tbluser.first_name')
            ->get();
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        //check for exisiting classteacher
        $data = classteacherModel::Join('tbluser as u','u.id','=','class_teacher.teacher_id')
            ->where([
                'class_teacher.sub_institute_id' => $sub_institute_id,
                'class_teacher.grade_id'         => $request->get('grade'),
                'class_teacher.standard_id'      => $request->get('standard'),
                'class_teacher.division_id'      => $request->get('division'),
                'class_teacher.syear'            => $syear,
            ])
            ->first();
        // display teacher name whom lecture is assigned
        if (!empty($data) && isset($data->first_name)) {
            $res = [
                "status_code" => 1,
                "message"     => "Class Teacher Already Exist to teacher ".$data->first_name.' '.$data->last_name,
                "class"       => "alert-danger",
            ];
        } else {
            $ct = new classteacherModel([
                'grade_id'         => $request->get('grade'),
                'standard_id'      => $request->get('standard'),
                'division_id'      => $request->get('division'),
                'teacher_id'       => $request->get('teacher_id'),
                'syear'            => $syear,
                'sub_institute_id' => $sub_institute_id,
            ]);
            $ct->save();
            $res = [
                "status_code" => 1,
                "message"     => "Class Teacher Added Successfully",
                "class"       => "alert-success",

            ];
        }

        $type = $request->input('type');

        return is_mobile($type, "classteacher.index", $res, "redirect");
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $res = classteacherModel::find($id)->toArray();
        $data['id'] = $res['id'];
        $data['academic_section_id'] = $res['grade_id'];
        $data['standard_id'] = $res['standard_id'];
        $data['division_id'] = $res['division_id'];
        $data['teacher_id'] = $res['teacher_id'];
        $data['teacher_data'] = $this->getusers($request);
        $data['data'] = $this->getData($request);
        $data['button'] = "Update";

        return is_mobile($type, 'school_setup/show_classteacher', $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        //check for exisiting classteacher
        $data = classteacherModel::select('*')
            ->where([
                'sub_institute_id' => $sub_institute_id,
                'grade_id'         => $request->get('grade'),
                'standard_id'      => $request->get('standard'),
                'division_id'      => $request->get('division'),
            ])
            ->where('id', '!=', $id)
            ->get();
        if (isset($data) && count($data) > 0) {
            $data = [
                "status_code" => 1,
                "message"     => "Class Teacher Already Exist",
                "class"       => "alert-danger",
            ];
        } else {
            $ins_data = [
                'grade_id'         => $request->get('grade'),
                'standard_id'      => $request->get('standard'),
                'division_id'      => $request->get('division'),
                'teacher_id'       => $request->get('teacher_id'),
                'syear'            => $syear,
                'sub_institute_id' => $sub_institute_id,
            ];
            classteacherModel::where(["id" => $id])->update($ins_data);
            $data = [
                "status_code" => 1,
                "message"     => "Class Teacher Updated Successfully",
                "class"       => "alert-success",
            ];
        }
        $type = $request->input('type');

        return is_mobile($type, "classteacher.index", $data, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        classteacherModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Class Teacher Deleted Successfully";

        return is_mobile($type, "classteacher.index", $res);
    }
}
