<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\lms\lmsmappingtypeModel;
use App\Models\lms\lomasterModel;
use App\Models\lms\topicModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lmsmappingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if($request->has('preload_lms')){
            $data = $this->getDataPre($request);
        }else{
            $data = $this->getData($request);
        }
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['final_data'];
        $res['chapter_topic_data'] = $data['chapter_topic_data'];

        return is_mobile($type, 'lms/show_lmsmapping', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $final_data = $res['chapter_topic_data'] = $data = array();

        $extra = "";

        if ($request->has("chapter_id")) {
            $chapter_id = $request->get("chapter_id");
            $chapter_data = chapterModel::select('chapter_name as chapter_topic_name', 'id as chapter_topic_id',
                DB::raw('"chapter" as action'))
                ->where(['chapter_master.sub_institute_id' => $sub_institute_id, 'chapter_master.id' => $chapter_id])
                ->get()->toArray();

            $extra .= " AND chapter_id = '".$chapter_id."'";

            $res['chapter_topic_data'] = $chapter_data[0] ?? [];
        }

        if ($request->has("topic_id")) {
            $topic_id = $request->get("topic_id");
            $topic_data = topicModel::select('name as chapter_topic_name', 'id as chapter_topic_id',
                DB::raw('"topic" as action'))
                ->where(['topic_master.sub_institute_id' => $sub_institute_id, 'topic_master.id' => $topic_id])
                ->get()->toArray();

            $extra .= " AND topic_id = '".$topic_id."'";

            $res['chapter_topic_data'] = $topic_data[0];
        }

        if (! $request->has("chapter_id") && ! $request->has("topic_id")) {
            $extra .= " AND globally = '1'";
        }


        $data = Db::select('SELECT * FROM lms_mapping_type AS a WHERE a.parent_id=0 '.$extra.'
            UNION 
            SELECT * FROM lms_mapping_type AS b WHERE b.parent_id != 0 '.$extra);

        $data = json_decode(json_encode($data), true);

        foreach ($data as $key => $val) {
            if ($val['parent_id'] == 0) {
                $final_data[$val['id']] = $val;
            } else {
                $final_data[$val['parent_id']]['CHILD_ARR'][] = $val;
            }
        }

        $res['final_data'] = $final_data;

        return $res;
    }
    public function getDataPre($request)
    {
        $sub_institute_id = 1;
        $final_data = $res['chapter_topic_data'] = $data = array();

        $extra = "";

        if ($request->has("chapter_id")) {
            $chapter_id = $request->get("chapter_id");
            $chapter_data = chapterModel::select('chapter_name as chapter_topic_name', 'id as chapter_topic_id',
                DB::raw('"chapter" as action'))
                ->where(['chapter_master.sub_institute_id' => $sub_institute_id, 'chapter_master.id' => $chapter_id])
                ->get()->toArray();

            $extra .= " AND chapter_id = '".$chapter_id."'";

            $res['chapter_topic_data'] = $chapter_data[0] ?? [];
        }

        if ($request->has("topic_id")) {
            $topic_id = $request->get("topic_id");
            $topic_data = topicModel::select('name as chapter_topic_name', 'id as chapter_topic_id',
                DB::raw('"topic" as action'))
                ->where(['topic_master.sub_institute_id' => $sub_institute_id, 'topic_master.id' => $topic_id])
                ->get()->toArray();

            $extra .= " AND topic_id = '".$topic_id."'";

            $res['chapter_topic_data'] = $topic_data[0];
        }

        if (! $request->has("chapter_id") && ! $request->has("topic_id")) {
            $extra .= " AND globally = '1'";
        }


        $data = Db::select('SELECT * FROM lms_mapping_type AS a WHERE a.parent_id=0 '.$extra.'
            UNION 
            SELECT * FROM lms_mapping_type AS b WHERE b.parent_id != 0 '.$extra);

        $data = json_decode(json_encode($data), true);

        foreach ($data as $key => $val) {
            if ($val['parent_id'] == 0) {
                $final_data[$val['id']] = $val;
            } else {
                $final_data[$val['parent_id']]['CHILD_ARR'][] = $val;
            }
        }

        $res['final_data'] = $final_data;

        return $res;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = 1;

        $data = array();
        if ($request->has('chapter_id')) {
            //$data['chapter_id'] = $request->get('chapter_id');
            $chapter_data = chapterModel::select('chapter_name as chapter_topic_name', 'id as chapter_topic_id',
                DB::raw('"chapter" as action'))
                ->where([
                    'chapter_master.sub_institute_id' => $sub_institute_id,
                    'chapter_master.id'               => $request->get('chapter_id'),
                ])
                ->get()->toArray();
            $data['chapter_topic_data'] = $chapter_data[0]??[];
        }

        if ($request->has('topic_id')) {
            //$data['topic_id'] = $request->get('topic_id');
            $topic_data = topicModel::select('name as chapter_topic_name', 'id as chapter_topic_id',
                DB::raw('"topic" as action'))
                ->where([
                    'topic_master.sub_institute_id' => $sub_institute_id,
                    'topic_master.id'               => $request->get('topic_id'),
                ])
                ->get()->toArray();

            $data['chapter_topic_data'] = $topic_data[0] ?? [];
        }

        return is_mobile($type, 'lms/add_lmsmapping', $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse|Response
     */
    public function store(Request $request)
    {
        //dd($request);
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $globally = 1;
        $chapter_id = $topic_id = "";

        //START INSERT Chapterwise LMS mapping
        if ($request->has('hid_chapter_id')) {
            $chapter_id = $request->get('hid_chapter_id');
            $existing_where['chapter_id'] = $chapter_id;
            $globally = 0;
        }
        //END INSERT Chapterwise LMS mapping

        //START INSERT Topicwise LMS mapping
        if ($request->has('hid_topic_id')) {
            $topic_id = $request->get('hid_topic_id');
            $tdata = topicModel::where("id", $topic_id)->get()->toArray();
            $chapter_id = $tdata[0]['chapter_id'];
            $existing_where['topic_id'] = $topic_id;
            $globally = 0;
        }
        //END INSERT Topicwise LMS mapping

        //START Check for Existing record
        $existing_id = "";
        $existing_where['name'] = $request->get('mapping_type');

        $existing_data = lmsmappingtypeModel::where($existing_where)->get()->toArray();

        if (count($existing_data) == 0)//Add New Master Entry $existing_id == ""
        {
            $content = array(
                'name'       => $request->get('mapping_type'),
                'status'     => 1,
                'globally'   => $globally,
                'chapter_id' => $chapter_id,
                'topic_id'   => $topic_id,
            );

            lmsmappingtypeModel::insert($content);
            $last_id = DB::getPDO()->lastInsertId();
        } else // Add in existing Master
        {
            $existing_id = $existing_data[0]['id'];
            $last_id = $existing_id;
        }
        //END Check for Existing record        

        $mapping_value = $request->get('mapping_value');
        foreach ($mapping_value as $key => $val) {
            if ($val != "") {
                $lmsmappingvalue = array(
                    'name'       => $val,
                    'parent_id'  => $last_id,
                    'globally'   => $globally,
                    'chapter_id' => $chapter_id,
                    'topic_id'   => $topic_id,
                    'status'     => 1,
                );
                lmsmappingtypeModel::insert($lmsmappingvalue);
            }
        }

        $res = array(
            "status_code" => 1,
            "message"     => "LMS Mapping Added Successfully",
        );
        $type = $request->input('type');

        if ($chapter_id != "" && $topic_id == "") {
            return redirect()->route('lmsmapping.index', ['chapter_id' => $chapter_id]);
        } elseif ($topic_id != "") {
            return redirect()->route('lmsmapping.index', ['topic_id' => $topic_id]);
        } else {
            return is_mobile($type, "lmsmapping.index", $res, "redirect");
        }
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

        $data['lmsmapping_data'] = lmsmappingtypeModel::find($id)->toArray();

        return is_mobile($type, "lms/edit_lmsmapping", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse|Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = array(
            'name' => $request->get('mapping_name'),
        );

        lmsmappingtypeModel::where(["id" => $id])->update($data);
        $res = array(
            "status_code" => 1,
            "message"     => "LMS Mapping Updated Successfully",
        );
        $type = $request->input('type');

        if ($request->get('hid_chapter_id') != "" && $request->get('hid_chapter_id') != "0") {
            return redirect()->route('lmsmapping.index', ['chapter_id' => $request->get('hid_chapter_id')]);
        } elseif ($request->get('hid_topic_id') != "" && $request->get('hid_topic_id') != "0") {
            return redirect()->route('lmsmapping.index', ['topic_id' => $request->get('hid_topic_id')]);
        } else {
            return is_mobile($type, "lmsmapping.index", $res, "redirect");
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse|Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $data = lmsmappingtypeModel::where(["id" => $id])->get()->toArray();
        $chapter_id = $data[0]['chapter_id'];
        $topic_id = $data[0]['topic_id'];
        lmsmappingtypeModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "LMS Mapping Deleted Successfully";


        if ($chapter_id != "" && $chapter_id != "0" && $topic_id == "") {
            return redirect()->route('lmsmapping.index', ['chapter_id' => $chapter_id]);
        } elseif ($topic_id != "" && $topic_id != "0") {
            return redirect()->route('lmsmapping.index', ['topic_id' => $topic_id]);
        } else {
            return is_mobile($type, "lmsmapping.index", $res, "redirect");
        }
    }

    public function ajax_ChapterwiseLOmaster(Request $request)
    {
        $chapter_id = $request->input("chapter_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return lomasterModel::where(['sub_institute_id' => $sub_institute_id, 'chapter_id' => $chapter_id])
            ->get()->toArray();
    }

    public function ajax_AddLMS_MappingFromContent(Request $request)
    {
        $lms_id = $request->get("new_mapping_type");
        $topic_id = $request->get("hid_topic_id");
        $new_value = $request->get("new_mapping_value");

        $lms_data = lmsmappingtypeModel::find($lms_id)->toArray();

        $lmsmappingvalue = [
            'name'       => $new_value,
            'parent_id'  => $lms_data['id'],
            'globally'   => $lms_data['globally'],
            'chapter_id' => $lms_data['chapter_id'],
            'topic_id'   => $lms_data['topic_id'],
            'status'     => 1,
        ];

        $insert = lmsmappingtypeModel::insert($lmsmappingvalue);
        if ($insert == 1) {
            return "1";
        }
    }

}
