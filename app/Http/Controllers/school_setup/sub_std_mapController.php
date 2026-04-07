<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\lms\lmsContentCategoryModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\school_setup\subjectModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\neo4jCreateNode;
use function App\Helpers\neo4jCreateRelationship;
use App\Services\Neo4jService;



class sub_std_mapController extends Controller
{

    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;

        return is_mobile($type, 'school_setup/show_sub_std_map', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $marking_period_id=session()->get('term_id');
        $data = sub_std_mapModel::select('sub_std_map.*', 'subject.subject_name', 'subject.subject_code',
            'standard.name')
            ->join('standard', function($query) use($marking_period_id){
                $query->on('standard.id', '=', 'sub_std_map.standard_id');
                // $query->when($marking_period_id,function($query) use ($marking_period_id){
                //     $query->where('standard.marking_period_id',$marking_period_id);
                // });    
            })
            ->join('subject', function($query) use($marking_period_id){
                $query->on('subject.id', '=', 'sub_std_map.subject_id');
                // $query->when($marking_period_id,function($query) use ($marking_period_id){
                //     $query->where('subject.marking_period_id',$marking_period_id);
                // });    
            })
            ->where(['sub_std_map.sub_institute_id' => $sub_institute_id])
            ->orderby('sub_std_map.standard_id')
            ->get();

        return $data;
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->input('type');
        $marking_period_id=session()->get('term_id');
        
        $std_data = standardModel::where(['sub_institute_id' => $sub_institute_id])->select('id', 'name',
            'short_name')
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // })
            ->get();
        $sub_data = subjectModel::where(['sub_institute_id' => $sub_institute_id])->select('id', 'subject_name',
            'subject_code')
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // })
            ->get();
        $data['content_category'] = lmsContentCategoryModel::where('status', '1')
            ->where(function ($query) use ($sub_institute_id) {
                $query->where('sub_institute_id', '=', $sub_institute_id)
                    ->orWhere('sub_institute_id', '=', '0');
            })
            ->get()->toArray();
        $data['std_data'] = $std_data;
        $data['sub_data'] = $sub_data;
        $data['optional_type'] = [4];
        return is_mobile($type, 'school_setup/add_sub_std', $data, "view");
    }


public function store(Request $request)
{
    $sub_institute_id = $request->session()->get('sub_institute_id');
    $syear = $request->session()->get('syear');
    $user_id = $request->session()->get('user_id');
    $standard_id = $request->get('standard_id');

    if (!is_array($standard_id)) {
        $standard_id = [$standard_id];
    }

    $file_folder = $ext = $size = $newfilename = "";

    // ================= FILE UPLOAD =================
    if ($request->hasFile('display_image')) {
        $img = $request->file('display_image');
        $ext = $img->getClientOriginalExtension();
        $newfilename = 'SubStdMap_' . date('Y-m-d_h-i-s') . '.' . $ext;
        $file_folder = '/SubStdMapping';

        Storage::disk('digitalocean')
            ->putFileAs('public/SubStdMapping/', $img, $newfilename, 'public');
    }

    DB::beginTransaction();

    try {

        foreach ($standard_id as $stdval) {

            // ================= SQL =================
            $record = sub_std_mapModel::updateOrCreate(
                [
                    'standard_id'      => $stdval,
                    'subject_id'       => $request->get('subject_id'),
                    'sub_institute_id' => $sub_institute_id
                ],
                [
                    'standard_id'      => $stdval,
                    'subject_id'       => $request->get('subject_id'),
                    'display_name'     => $request->get('display_name'),
                    'allow_grades'     => $request->get('allow_grades') ?? "",
                    'elective_subject' => $request->get('elective_subject') ?? "No",
                    'allow_content'    => $request->get('allow_content') ?? "",
                    'subject_category' => $request->get('subject_category'),
                    'display_image'    => $newfilename,
                    'sub_institute_id' => $sub_institute_id,
                    'created_by'       => $user_id
                ]
            );

            // ======================================================
            // ✅ NEO4J (CORRECT)
            // ======================================================

            try {

                // 🔹 STANDARD NODE
                neo4jCreateNode(
                    'Standard',
                    ['stId' => (int)$stdval],
                    [
                        'standard_id' => (int)$stdval,
                        'name' => 'Standard ' . $stdval,
                        'displayLabel' => 'Standard:' . $stdval,
                        'sub_institute_id' => (int)$sub_institute_id
                    ]
                );

                // 🔹 SUBJECT NODE
                neo4jCreateNode(
                    'Subject',
                    ['subId' => (int)$request->get('subject_id')],
                    [
                        'subject_id' => (int)$request->get('subject_id'),
                        'subject_name' => $request->get('display_name') ?? 'Subject',
                        'standard_id' => (int)$stdval,
                        'displayLabel' => 'Subject:' . ($request->get('display_name') ?? 'Subject'),
                        'sub_institute_id' => (int)$sub_institute_id
                    ]
                );

                // 🔹 RELATION: Standard → HAS_SUBJECT → Subject
                neo4jCreateRelationship(
                    'Standard',
                    ['stId' => (int)$stdval],
                    'HAS_SUBJECT',
                    'Subject',
                    ['subId' => (int)$request->get('subject_id')]
                );

                \Log::info('✅ Neo4j Standard-Subject Created', [
                    'stId' => $stdval,
                    'subId' => $request->get('subject_id')
                ]);

            } catch (\Exception $e) {
                \Log::error('❌ Neo4j Error: ' . $e->getMessage());
            }
        }

        DB::commit();

    } catch (\Exception $e) {
        DB::rollBack();

        \Log::error('❌ SQL Error: ' . $e->getMessage());

        return back()->with([
            'status_code' => 0,
            'message' => 'Error saving data'
        ]);
    }

    return redirect()->back()->with([
        'status_code' => 1,
        'message' => 'Standard-Subject Mapping Saved Successfully'
    ]);
}
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear'); // added on 15-03-2025
        $user_id = $request->session()->get('user_id'); // added on 15-03-2025
        $type = $request->input('type');
        $mapped_data = sub_std_mapModel::find($id)->toArray();
        $std_data = standardModel::where(['sub_institute_id' => $sub_institute_id])->select('id', 'name',
            'short_name')->get();
        $sub_data = subjectModel::where(['sub_institute_id' => $sub_institute_id])->select('id', 'subject_name',
            'subject_code')->get();
        $data['content_category'] = lmsContentCategoryModel::where('status', '1')->get()->toArray();
        // 15-03-2025 get optional subject 4 
        $dataArr = [
            'syear'=>$syear,
            'subject_id'=>$mapped_data['subject_id'],
            'standard_id'=>$mapped_data['standard_id'],
            'optional_type'=>4,
            'sub_institute_id'=>$sub_institute_id
        ];
        $getOptionalSubejct = DB::table('subject_optional_type')->where($dataArr)->first();

        $data['std_data'] = $std_data;
        $data['sub_data'] = $sub_data;
        $data['mapped_data'] = $mapped_data;
        $data['optional_type'] = [4];
        $data['subject_optional_mapped'] = $getOptionalSubejct;

        return is_mobile($type, "school_setup/add_sub_std", $data, "view");
    }

    public function update(Request $request, $id)
    {
        ValidateInsertData('sub_std_map', 'update');

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear'); // added on 15-03-2025
        $user_id = $request->session()->get('user_id'); // added on 15-03-2025
        $standard_id = $request->get('standard_id');
        $finalStdId = $standard_id[0];
        // sub_std_mapModel::where(["id" => $id])->delete();

        if ($request->hasFile('display_image')) {
            $img = $request->file('display_image');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'SubStdMap_'.date('Y-m-d_h-i-s').'.'.$ext;
            $file_folder = '/SubStdMapping';
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            // $img->storeAs('public/SubStdMapping/', $newfilename); 20-05-24
            Storage::disk('digitalocean')->putFileAs('public/SubStdMapping/', $img, $newfilename, 'public');

            $data = [
                'standard_id'      => $finalStdId,
                'subject_id'       => $request->get('subject_id'),
                'display_name'     => $request->get('display_name'),
                'allow_grades'     => $request->get('allow_grades') != "" ? $request->get('allow_grades') : "",
                'allow_content'    => $request->get('allow_content') != "" ? $request->get('allow_content') : "",
                'subject_category' => $request->get('subject_category'),
                'elective_subject' => $request->get('elective_subject') != "" ? $request->get('elective_subject') : "No",
                'allow_content'    => $request->get('allow_content') != "" ? $request->get('allow_content') : "",
                'display_image'    => $file_folder.'/'.$newfilename,
                'sub_institute_id' => $sub_institute_id,
                'add_content'      => $request->get('add_content'),
                'sort_order'       => $request->get('sort_order'),
                'status'           => "1",
                'load'             => $request->get('load'),
                'optional_type'    => ($request->get('optional_type') !=null && $request->get('elective_subject') != "") ? $request->get('optional_type') : null,
            ];
        } else {
            $data = [
                'standard_id'      => $finalStdId,
                'subject_id'       => $request->get('subject_id'),
                'display_name'     => $request->get('display_name'),
                'allow_grades'     => $request->get('allow_grades') != "" ? $request->get('allow_grades') : "",
                'allow_content'    => $request->get('allow_content') != "" ? $request->get('allow_content') : "",
                'subject_category' => $request->get('subject_category'),
                'elective_subject' => $request->get('elective_subject') != "" ? $request->get('elective_subject') : "No",
                'allow_content'    => $request->get('allow_content') != "" ? $request->get('allow_content') : "",
                'sub_institute_id' => $sub_institute_id,
                'sort_order'       => $request->get('sort_order'),
                'add_content'      => $request->get('add_content'),
                'status'           => "1",
                'load'             => $request->get('load'),
                'optional_type'    => ($request->get('optional_type') !=null && $request->get('elective_subject') != "") ? $request->get('optional_type') : null,
            ];
        }

        sub_std_mapModel::where(["id" => $id])->update($data);
        // 15-03-2025 hills optional subject syear wise 
        // if($request->optional_type!=''){

            $dataArr = [
                'syear'=>$syear,
                'subject_id'=>$request->subject_id,
                'standard_id'=>$finalStdId,
                'sub_institute_id'=>$sub_institute_id
            ];
            // check subject and standard already exists in table or not
            $checkExists = DB::table('subject_optional_type')->where($dataArr)->first();

            if(!empty($checkExists) && isset($checkExists->optional_type)){
                $updatedData['optional_type'] = ($request->get('optional_type') !=null && $request->get('elective_subject') != "") ? $request->get('optional_type') : 0;
                $updatedData['updated_by'] = $user_id;
                $updatedData['updated_at'] = now();
                DB::table('subject_optional_type')->where($dataArr)->update($updatedData);
            }
            else if($request->get('optional_type') !=''){
                $insertData = [
                    'syear'=>$syear,
                    'subject_id'=>$request->subject_id,
                    'standard_id'=>$finalStdId,
                    'optional_type'=>$request->optional_type,
                    'sub_institute_id'=>$sub_institute_id,
                    'created_by'=>$user_id,
                    'created_at'=>now()
                ];
                DB::table('subject_optional_type')->insert($insertData);
            }
        // }
        // 15-03-2025 end 
        $res = [
            "status_code" => 1,
            "message"     => "Subject-Standard Mapping Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "sub_std_map.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        sub_std_mapModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Subject-Standard Mapping Deleted Successfully";

        return is_mobile($type, "sub_std_map.index", $res);
    }

    function ajax_subStdMappingDependencies(Request $request)
    {
        $id = $request->input("id");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");

        $mapped_data = sub_std_mapModel::find($id)->toArray();
        $subject_id = $mapped_data['subject_id'];
        $standard_id = $mapped_data['standard_id'];

        $data = chapterModel::select(DB::raw('count(*) as total'))
            ->where([
                'sub_institute_id' => $sub_institute_id,
                'syear'            => $syear,
                'subject_id'       => $subject_id,
                'standard_id'      => $standard_id,
            ])
            ->get()->toArray();

        return $data[0]['total'];
    }
}

