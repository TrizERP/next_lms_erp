<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class bulk_chapter_uploadController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'lms/bulk_chapter_upload', $res, "view");
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $chapter_name = $request->get('chapter_name');
        $chapter_desc = $request->get('chapter_desc');
        $availability = $request->get('availability');
        $show_hide = $request->get('show_hide');

        foreach ($chapter_name as $key => $val) {
            $show_hide_val = $show_hide[$key] ?? '';
            $availability_val = $availability[$key] ?? '';
            $chapter_desc_val = $chapter_desc[$key] ?? '';

            $ch = [
                'grade_id'         => $request->get('grade'),
                'standard_id'      => $request->get('standard'),
                'subject_id'       => $request->get('subject'),
                'chapter_name'     => $val,
                'availability'     => $availability_val,
                'show_hide'        => $show_hide_val,
                'chapter_desc'     => $chapter_desc_val,
                'created_by'       => $user_id,
                'sub_institute_id' => $sub_institute_id,
                'syear'            => $syear,
            ];

            chapterModel::insert($ch);
        }

        $res = [
            "status_code" => 1,
            "message"     => "Chapters Added Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "chapter_master.index", $res, "redirect");
    }

    public function StandardwiseSubject(Request $request)
    {
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
            ->orderBy('display_name')->get()->toArray();
    }


    function ajax_chapterDependencies(Request $request)
    {
        $chapter_id = $request->input("chapter_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $data = chapterModel::where([
            'chapter_master.sub_institute_id' => $sub_institute_id,
            'chapter_master.id'               => $chapter_id,
        ])
            ->leftjoin('topic_master as tm', 'tm.chapter_id', '=', 'chapter_master.id')
            ->leftjoin('lo_master as lm', 'lm.chapter_id', '=', 'chapter_master.id')
            ->leftjoin('lo_indicator as li', 'li.chapter_id', '=', 'chapter_master.id')
            ->havingRaw('tm.id is not null or lm.id is not null or li.id is not null')
            ->get()->toArray();

        return count($data);
    }

}
