<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\lms\loindicatorModel;
use App\Models\lms\lomasterModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class loindicatorController extends Controller
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
        $res['data'] = $data['loindicator_data'];

        return is_mobile($type, 'lms/show_loindicator', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['loindicator_data'] = loindicatorModel::select('lo_indicator.*', 'standard.name as standard_name',
            'academic_section.title as grade_name', 'subject_name', 'chapter_name', 'lm.title as lomaster_title')
            ->join('standard', 'standard.id', '=', 'lo_indicator.standard_id')
            ->join('academic_section', 'academic_section.id', '=', 'lo_indicator.grade_id')
            ->join('subject', 'subject.id', '=', 'lo_indicator.subject_id')
            ->join('chapter_master as cm', 'cm.id', '=', 'lo_indicator.chapter_id')
            ->join('lo_master as lm', 'lm.id', '=', 'lo_indicator.lomaster_id')
            ->where('lo_indicator.sub_institute_id', $sub_institute_id)
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
        $data = [];

        return is_mobile($type, 'lms/add_loindicator', $data, "view");
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
            'chapter_id'       => $request->get('chapter'),
            'lomaster_id'      => $request->get('lomaster'),
            'indicator'        => $request->get('indicator'),
            'show_hide'        => $show_hide_val,
            'sort_order'       => $request->get('sort_order'),
            'availability'     => $request->get('availability'),
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ];

        loindicatorModel::insert($content);

        $res = [
            "status_code" => 1,
            "message"     => "Lo-Indicator Added Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "lo_indicator.index", $res, "redirect");
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

        $data['loindicator_data'] = loindicatorModel::find($id)->toArray();

        $std_id = $data['loindicator_data']['standard_id'];
        $stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
            ->orderBy('display_name')->get()->toArray();
        $data['subjects'] = $stdData;

        $subject_id = $data['loindicator_data']['subject_id'];
        $chapterData = chapterModel::where(['sub_institute_id' => $sub_institute_id, 'subject_id' => $subject_id])
            ->get()->toArray();
        $data['chapters'] = $chapterData;

        $chapter_id = $data['loindicator_data']['chapter_id'];
        $lomasterData = lomasterModel::where(['sub_institute_id' => $sub_institute_id, 'chapter_id' => $chapter_id])
            ->get()->toArray();
        $data['lomasters'] = $lomasterData;

        return is_mobile($type, "lms/add_loindicator", $data, "view");
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
            'chapter_id'       => $request->get('chapter'),
            'lomaster_id'      => $request->get('lomaster'),
            'indicator'        => $request->get('indicator'),
            'show_hide'        => $show_hide_val,
            'sort_order'       => $request->get('sort_order'),
            'availability'     => $request->get('availability'),
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ];

        loindicatorModel::where(["id" => $id])->update($data);
        $res = [
            "status_code" => 1,
            "message"     => "Lo-Indicator Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "lo_indicator.index", $res, "redirect");
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
        loindicatorModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Lo-Indicator Deleted Successfully";

        return is_mobile($type, "lo_indicator.index", $res);
    }

    public function ajax_LoMasterwiseLoIndicator(Request $request)
    {
        $lomaster_ids = $request->input("lomaster_ids");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return loindicatorModel::where(['sub_institute_id' => $sub_institute_id])
            ->whereRaw('lomaster_id IN ('.$lomaster_ids.')')
            ->get()->toArray();
    }
}
