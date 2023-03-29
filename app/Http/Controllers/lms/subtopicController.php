<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\topicModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class subtopicController extends Controller
{
    public function index(Request $request)
    {
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $res['chapter_id'] = $request->input('chapter_id');
        $res['topic_id'] = $request->input('topic_id');
        $res['topicname'] = $request->input('topicname');

        return is_mobile($type, 'lms/add_subtopic', $res, "view");
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        $subtopic_name = $request->get('subtopic_name');
        $subtopic_desc = $request->get('subtopic_desc');
        $subtopic_sort_order = $request->get('subtopic_sort_order');
        $subtopic_show_hide = $request->get('subtopic_show_hide');

        foreach ($subtopic_name as $key => $val) {
            $show_hide_val = $subtopic_show_hide[$key] ?? '';
            $sort_order_val = $subtopic_sort_order[$key] ?? '';
            $topic_desc_val = $subtopic_desc[$key] ?? '';

            $subtopic = [
                'chapter_id'       => $request->get('ST_hidchapter_id'),
                'main_topic_id'    => $request->get('ST_hidtopic_id'),
                'name'             => $val,
                'description'      => $topic_desc_val,
                'topic_sort_order' => $sort_order_val,
                'topic_show_hide'  => $show_hide_val,
                'created_by'       => $user_id,
                'sub_institute_id' => $sub_institute_id,
                'syear'            => $syear,
            ];
            topicModel::insert($subtopic);
        }

        $res = [
            "status_code" => 1,
            "message"     => "Sub Topic Added Successfully",
        ];
        $type = $request->input('type');

        return redirect()->route('topic_master.index', ['id' => $request->get('ST_hidchapter_id')]);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['subtopic_data'] = topicModel::find($id)->toArray();

        return is_mobile($type, "lms/edit_subtopic", $data, "view");
    }

    public function update(Request $request, $id)
    {
        //ValidateInsertData('subject','update');        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        $show_hide = $request->get("subtopic_show_hide");
        $show_hide_val = $show_hide ?? '';

        $data = [
            'name'             => $request->get('subtopic_name'),
            'description'      => $request->get('subtopic_desc'),
            'topic_sort_order' => $request->get('subtopic_sort_order'),
            'topic_show_hide'  => $show_hide_val,
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ];

        topicModel::where(["id" => $id])->update($data);
        $res = [
            "status_code" => 1,
            "message"     => "Sub Topic Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "chapter_master.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        topicModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Sub Topic Deleted Successfully";

        return is_mobile($type, "chapter_master.index", $res);
    }

}
