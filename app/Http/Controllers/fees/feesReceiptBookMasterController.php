<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use App\Models\fees\fees_title\fees_title;
use App\Models\fees\feesReceiptBookMasterModel;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\standardModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;

class feesReceiptBookMasterController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');
        $data = feesReceiptBookMasterModel::selectRaw('fees_receipt_book_master.*')
            ->selectRaw("group_concat(distinct academic_section.short_name) as grade")
            ->selectRaw("group_concat(distinct standard.name) as standard")
            ->selectRaw("CASE WHEN fees_receipt_book_master.status = 1 THEN 'Active' ELSE 'Inactive' END as status")
            ->selectRaw("group_concat(distinct fees_title.display_name ORDER BY fees_title.sort_order) as fees_head")
            ->join('academic_section', 'fees_receipt_book_master.grade_id', '=', 'academic_section.id')
            ->join('standard', function($join) use($marking_period_id) {
                $join->on('fees_receipt_book_master.standard_id', '=', 'standard.id');
                // ->when($marking_period_id,function($join)use($marking_period_id){
                //     $join->where('standard.marking_period_id',$marking_period_id);
                // });
            })
            ->join('fees_title', 'fees_receipt_book_master.fees_head_id', '=', 'fees_title.id')
            ->where([
                'fees_receipt_book_master.sub_institute_id' => $sub_institute_id,
                'fees_receipt_book_master.syear'            => $syear,
            ])
            ->groupBy('fees_receipt_book_master.receipt_id')
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, "fees/show_fees_receipt_book", $res, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $feeHeadList = $this->feeHeadList($request);
        $receiptId = feesReceiptBookMasterModel::selectRaw("MAX(CAST(receipt_id AS UNSIGNED))+1 AS newcode")->where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        if (! isset($receiptId[0]['newcode'])) {
            $receiptId[0]['newcode'] = 1;
        }

        view()->share('receipt_id', $receiptId[0]['newcode']);

        view()->share('feeHeadList', $feeHeadList);

        return view("fees/add_fees_receipt_book");
    }

    public function standardList(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return standardModel::where(['sub_institute_id' => $sub_institute_id])
            ->pluck('grade_id', 'id')->toArray();
    }

    public function gradeList(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])
            ->pluck('title', 'id')->toArray();
    }

    public function feeHeadList(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

//        $feeHeadList = fees_title::where(['syear' => $syear,'sub_institute_id' => $sub_institute_id,'other_fee_id' => '0'])->get()->toArray();
        return fees_title::where([
            'syear' => $syear, 'sub_institute_id' => $sub_institute_id,
        ])->orderBy('sort_order')->get()->toArray();
    }

    public function store(Request $request)
    {
        // dd($request);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $standard_ids = $request['standard'];
        $grade_ids = $request['grade'];
        $fees_head_id = $request['fees_head_id'];
        $receipt_id = $request['receipt_id'];
        $submit = $request['submit'];

        if ($submit == 'Update') {
            $chkExistHead = DB::table('fees_receipt_book_master')
                ->whereIn('fees_head_id', $fees_head_id)
                ->whereIn('grade_id', $grade_ids)
                ->whereIn('standard_id', $standard_ids)
                ->where('receipt_id', '!=', $receipt_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)->get()->toArray();
        } else {
            $chkExistHead = DB::table('fees_receipt_book_master')
                ->whereIn('fees_head_id', $fees_head_id)
                ->whereIn('grade_id', $grade_ids)
                ->whereIn('standard_id', $standard_ids)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)->get()->toArray();

        }

        if (count($chkExistHead) == 0) {
            if ($submit == 'Update') {
                feesReceiptBookMasterModel::where([
                    "receipt_id" => $receipt_id, 'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
                ])->delete();
            }
            $standardList = $this->standardList($request);
            $request->request->remove('grade');
            $request->request->remove('standard');

            $file_name = "";
            if ($request->hasFile('fees_receipt_logo')) {
                $file = $request->file('fees_receipt_logo');
                $originalname = $file->getClientOriginalName();
                $name = $request->get('fees_receipt_logo').date('YmdHis');
                $ext = File::extension($originalname);
                $file_name = "receipt_logo_".$name.'.'.$ext;
                $path = $file->storeAs('public/fees/', $file_name);
            }
            $bank_logo_name = "";
            if ($request->hasFile('fees_bank_logo')) {
                $file = $request->file('fees_bank_logo');
                $originalname = $file->getClientOriginalName();
                $name = $request->get('fees_bank_logo').date('YmdHis');
                $ext = File::extension($originalname);
                $bank_logo_name = "bank_logo_".$name.'.'.$ext;
                $path = $file->storeAs('public/fees/', $bank_logo_name);
            }

            if ($file_name != '') {
                $request->request->add(['receipt_logo' => $file_name]); //add request
            }
            if ($bank_logo_name != '') {
                $request->request->add(['bank_logo' => $bank_logo_name]); //add request
            }
            $request->request->add(['status' => "1"]); //add request

            foreach ($standard_ids as $key => $value) {
                foreach ($fees_head_id as $k => $id) {
                    $gradeList = $this->gradeList($request);
                    $newGradeId = $standardList[$value];
                    $request->request->set('standard_id', $value);
                    $request->request->set('grade_id', $newGradeId);
                    $request->request->set('fees_head_id', $id);
                    // echo ('<pre>');print_r($request);exit;
                    $data = $this->saveData($request);
                }
            }

            $res['status_code'] = "1";
            $res['message'] = "Fees Receipt Book Added successfully";

            return is_mobile($type, "fees_receipt_book_master.index", $res);
        } else {
            $res['status_code'] = "0";
            $res['message'] = "Some fees head has already mapped with another receipt book for same academic year.";

            return is_mobile($type, "fees_receipt_book_master.index", $res);
        }


    }

    public function saveData(Request $request)
    {
        $newRequest = $request->all();
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['syear'] = $syear;
        $finalArray['created_by'] = $user_id;

        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'fees_receipt_logo' && $key != 'fees_bank_logo') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        feesReceiptBookMasterModel::insert($finalArray);

        return DB::getPdo()->lastInsertId();

    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        feesReceiptBookMasterModel::where([
            "receipt_id" => $id, 'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Fees Receipt Book Added successfully";

        return is_mobile($type, "fees_receipt_book_master.index", $res);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $editData = feesReceiptBookMasterModel::selectRaw('fees_receipt_book_master.*')
            ->selectRaw('GROUP_CONCAT(distinct standard_id) as standard_id')
            ->selectRaw('GROUP_CONCAT(distinct grade_id) as grade_id')
            ->selectRaw('GROUP_CONCAT(distinct fees_head_id) as fees_head_id')
            ->where(['receipt_id' => $id, 'sub_institute_id' => $sub_institute_id, 'syear' => $syear])
            ->groupBy('receipt_id')
            ->get()->toArray();

        $editData = $editData[0];

        $feeHeadList = $this->feeHeadList($request);
        view()->share('feeHeadList', $feeHeadList);

        return view('fees/edit_fees_receipt_book', ['data' => $editData]);
    }
}
