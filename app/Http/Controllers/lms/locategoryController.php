<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\lms\locategoryModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class locategoryController extends Controller
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
        $res['data'] = $data['locategory_data'];

        return is_mobile($type, 'lms/show_locategory', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['locategory_data'] = locategoryModel::select('lo_category.*', 'standard.name as standard_name',
            'academic_section.title as grade_name', 'subject_name')
            ->join('standard', 'standard.id', '=', 'lo_category.standard_id')
            ->join('academic_section', 'academic_section.id', '=', 'lo_category.grade_id')
            ->join('subject', 'subject.id', '=', 'lo_category.subject_id')
            ->where('lo_category.sub_institute_id', $sub_institute_id)
            ->get();

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = array();

        return is_mobile($type, 'lms/add_locategory', $data, "view");
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
        $user_id = $request->session()->get('user_id');
        $show_hide = $request->get('show_hide');
        $show_hide_val = $show_hide ?? '';

        $content = [
            'grade_id'         => $request->get('grade'),
            'standard_id'      => $request->get('standard'),
            'subject_id'       => $request->get('subject'),
            'title'            => $request->get('title'),
            'show_hide'        => $show_hide_val,
            'sort_order'       => $request->get('sort_order'),
            'availability'     => $request->get('availability'),
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ];

        locategoryModel::insert($content);

        $res = [
            "status_code" => 1,
            "message"     => "Lo-Category Added Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "lo_category.index", $res, "redirect");
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
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['locategory_data'] = locategoryModel::find($id)->toArray();

        $std_id = $data['locategory_data']['standard_id'];
        $stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
            ->orderBy('display_name')->get()->toArray();
        $data['subjects'] = $stdData;

        $subject_id = $data['locategory_data']['subject_id'];
        $chapterData = chapterModel::where(['sub_institute_id' => $sub_institute_id, 'subject_id' => $subject_id])
            ->get()->toArray();
        $data['chapters'] = $chapterData;

        return is_mobile($type, "lms/add_locategory", $data, "view");
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
        $user_id = $request->session()->get('user_id');
        $show_hide = $request->get('show_hide');
        $show_hide_val = $show_hide ?? '';

        $data = [
            'grade_id'         => $request->get('grade'),
            'standard_id'      => $request->get('standard'),
            'subject_id'       => $request->get('subject'),
            'title'            => $request->get('title'),
            'show_hide'        => $show_hide_val,
            'sort_order'       => $request->get('sort_order'),
            'availability'     => $request->get('availability'),
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ];

        locategoryModel::where(["id" => $id])->update($data);
        $res = [
            "status_code" => 1,
            "message"     => "Lo-Category Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "lo_category.index", $res, "redirect");
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
        locategoryModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Lo-Category Deleted Successfully";

        return is_mobile($type, "lo_category.index", $res);
    }

    public function ajax_Chapterwiselocategory(Request $request)
    {
        $chapter_id = $request->input("chapter_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return locategoryModel::where(['sub_institute_id' => $sub_institute_id, 'chapter_id' => $chapter_id])
            ->get()->toArray();
    }

    public function ajax_SubjectwiseLoCategory(Request $request)
    {
        $sub_id = $request->input("sub_id");
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return locategoryModel::where([
            'sub_institute_id' => $sub_institute_id,
            'subject_id'       => $sub_id,
            'standard_id'      => $std_id,
            'availability'     => 1,
        ])->get()->toArray();
    }

}
