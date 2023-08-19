<?php

namespace App\Http\Controllers\lms\flashcard;

use App\Http\Controllers\Controller;
use App\Models\lms\answermasterModel;
use App\Models\lms\contentModel;
use App\Models\lms\flashcardModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class flashcardController extends Controller
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
        $res['data'] = $data['flashcard_data'];
        $res['breadcrum_data'] = $data['breadcrum_data'];

        return is_mobile($type, 'lms/flashcard/show_flashcard', $res, "view");
    }

    public function getData($request)
    {
        if($request->has('preload_lms')){
            $sub_institute_id = 1;
            $year = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
            $syear =$year[0]->syear;
        }else{
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');
        }
        $data['flashcard_data'] = array();
        $marking_period_id = session()->get('term_id');

        $where_condition['lms_question_master.sub_institute_id'] = $sub_institute_id;

        $data['flashcard_data'] = flashcardModel::select('lms_flashcard.*', 's.name as standard_name', 'c.chapter_name',
            'sub.subject_name')
            ->join('standard as s',function($join) use($marking_period_id){
                $join->on('s.id', 'lms_flashcard.standard_id');
            })
            ->join('subject as sub', 'sub.id', 'lms_flashcard.subject_id')
            ->join('chapter_master as c', 'c.id', 'lms_flashcard.chapter_id')
            ->where([
                'lms_flashcard.sub_institute_id' => $sub_institute_id, 'lms_flashcard.syear' => $syear,
                'lms_flashcard.content_id'       => $request->get('content_id'),
            ])
            ->get();
        $data['content_data'] = contentModel::find($request->content_id)->toArray();
        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $data['content_data']['chapter_id'] ?? '', $data['content_data']['topic_id']);

        return $data;
    }

    public function getBreadcrum($sub_institute_id, $chapter_id, $topic_id ='')
    {
     
        $where = '';
        $topic = '';
        $topic_id = '';

        if ($topic_id != '') {
            $topic = 't.id as topic_id,';
        }

        $breadcrum_data = DB::table('chapter_master as c')
            ->join('sub_std_map as s', function ($join) {
                $join->whereRaw('s.subject_id = c.subject_id AND s.standard_id = c.standard_id');
            })->join('standard as st', function ($join) {
                $join->whereRaw('st.id = c.standard_id');
            })->Join('topic_master as t', function ($join) {
                $join->whereRaw('t.chapter_id = c.id');
            })->selectRaw('c.subject_id,s.display_name AS subject_name,c.standard_id,st.name AS standard_name,
                c.id AS chapter_id, c.chapter_name, '.$topic.' t.name as topic_name')
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.id', $chapter_id);

        if ($topic_id != '') {
            $breadcrum_data = $breadcrum_data->where('t.id', $topic_id);
            $topic = 't.id as topic_id,';
        }

        $breadcrum_data = $breadcrum_data->get()->toArray();
        // dd($breadcrum_data);
        if(isset($breadcrum_data[0]) && $breadcrum_data != " "){
        return $breadcrum_data[0];
        }
        else{
            return back();
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        if($request->has('preload_lms')){
            $sub_institute_id = 1;
        }else{
        $sub_institute_id = $request->session()->get('sub_institute_id');
        }
        $syear = $request->session()->get('syear');

        $content_data = contentModel::where('id', $request->get('content_id'))->get()->toArray();

        $data['grade_id'] = $content_data[0]['grade_id'];
        $data['standard_id'] = $content_data[0]['standard_id'];
        $data['subject_id'] = $content_data[0]['subject_id'];
        $data['content_id'] = $content_data[0]['id'];
        $data['chapter_id'] = $content_data[0]['chapter_id'];
        $data['topic_id'] = $content_data[0]['topic_id'];

        $data['content_data'] = contentModel::find($request->content_id)->toArray();
        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id,$data['content_data']['chapter_id'],$data['content_data']['topic_id']);
        // $dara['breadcrum_data'] = $content_data[0]['topic_id'];
        // echo $data['chapter_id'];exit; 
        return is_mobile($type, 'lms/flashcard/add_flashcard', $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        // return $request;exit;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $status = $request->get('status');
        $status_val = $status ?? '';

        $flashcard_array = array(
            'standard_id'      => $request->get('standard_id'),
            'subject_id'       => $request->get('subject_id'),
            'chapter_id'       => $request->get('chapter_id'),
            'topic_id'         => $request->get('topic_id'),
            'content_id'       => $request->get('content_id'),
            'title'            => $request->get('title'),
            'front_text'       => $request->get('front_text'),
            'back_text'        => $request->get('back_text'),
            'status'           => $status_val,
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        );
        $question_id = flashcardModel::insertGetId($flashcard_array);


        $res = [
            "status_code" => 1,
            "message"     => "Flash Card Added Successfully",
        ];
        $type = $request->input('type');

        return redirect()->route('lms_flashcard.index', ['content_id' => $request->get('content_id')]);
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
        $syear = $request->session()->get('syear');

        $data['flashcard_data'] = flashcardModel::find($id)->toArray();

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $data['flashcard_data']['chapter_id'] ?? '', $data['flashcard_data']['topic_id']);

        return is_mobile($type, "lms/flashcard/add_flashcard", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $status = $request->get('status');
        $status_val = $status ?? '';

        $flashcard_array = [
            'standard_id'      => $request->get('standard_id'),
            'subject_id'       => $request->get('subject_id'),
            'chapter_id'       => $request->get('chapter_id'),
            'topic_id'         => $request->get('topic_id'),
            'content_id'       => $request->get('content_id'),
            'title'            => $request->get('title'),
            'front_text'       => $request->get('front_text'),
            'back_text'        => $request->get('back_text'),
            'status'           => $status_val,
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ];

        flashcardModel::where(["id" => $id])->update($flashcard_array);

        $res = [
            "status_code" => 1,
            "message"     => "Flash Card Updated Successfully",
        ];
        $type = $request->input('type');

        return redirect()->route('lms_flashcard.index', ['content_id' => $request->get('content_id')]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return RedirectResponse
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $flashcarddata = flashcardModel::where(["id" => $id])->get()->toArray();
        $content_id = $flashcarddata[0]['content_id'];

        flashcardModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Flash Card Deleted Successfully";

        return redirect()->route('lms_flashcard.index', ['content_id' => $content_id]);
    }

    function ajaxdestroyanswer_master(Request $request)
    {
        $id = $request->input('id');
        answermasterModel::where(["id" => $id])->delete();
    }

    public function ajax_ChapterwiseLOmaster(Request $request)
    {
        $chapter_id = $request->input("chapter_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $lomasterData = questionmasterModel::where([
            'sub_institute_id' => $sub_institute_id, 'chapter_id' => $chapter_id,
        ])
            ->get()->toArray();

        return $lomasterData;
    }

}
