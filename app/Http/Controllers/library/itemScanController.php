<?php

namespace App\Http\Controllers\library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\library\itemScanDetail;
use App\Models\library\itemStatus;
use GenTux\Jwt\GetsJwtToken;
use Validator;
use DB;

class itemScanController extends Controller
{
    use GetsJwtToken;

    //
    public function index(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }

        $res['searchedItem'] = $request->item_code;
        
        // return "hello";
        return is_mobile($type, "library/bookVarification/scanBook", $res, "view");        
    }

    public function store(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');
        $item_code = $request->item_code;

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $syear = $request->get('syear');
                $user_id = $request->get('user_id');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear' => 'required|numeric',
                    'user_id' => 'required|numeric',
                    'item_code' => 'required',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }
        $data = [
            'sub_institute_id'=>$sub_institute_id,
            'syear'=>$syear,
            'item_code'=>$item_code,
        ];
        $checkItemCode = DB::table('library_items')->where('item_code',$item_code)->where('sub_institute_id',$sub_institute_id)->get()->toArray();

        if(!empty($checkItemCode)){
            $checkData = itemScanDetail::where($data)->whereNull('deleted_at')->first();
        
            if(empty($checkData)){
                $data['created_by'] = $user_id;
                $data['scan_status'] = "Yes";
                $data['created_at']=now();
                itemScanDetail::insert($data);
            }else{
                itemScanDetail::where('id',$checkData->id)->update(["updated_at"=>now()]);
            }

            $res['status'] = "1";
            $res['message'] = "Book Scan Successfully";
            $scanData = itemScanDetail::join('library_items as li',function($join){
                                    $join->on('li.item_code','=','item_scan_details.item_code')->on('item_scan_details.sub_institute_id','=','li.sub_institute_id');
                                })
                                ->join('library_books as lb',function($join){
                                    $join->on('li.book_id','=','lb.id')->on('item_scan_details.sub_institute_id','=','lb.sub_institute_id');
                                })
                                ->selectRaw('item_scan_details.*,lb.title as book_title,lb.material_resource_type as collection_type')
                                ->where(['item_scan_details.sub_institute_id'=>$sub_institute_id,'item_scan_details.item_code'=>$item_code])
                                ->whereNull('item_scan_details.deleted_at')
                                ->get()
                                ->toArray(); 
        }else{
            $res['status'] = "0";
            $res['message'] = "No Books Found From this Item Code";
        }
       
        $res['searchedItem'] = $item_code;
        $res['bookData'] = isset($scanData) ? $scanData : [];

        return is_mobile($type, "library/bookVarification/scanBook", $res, "view");        
    }

    public function remarksIndex(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $syear = $request->get('syear');
                $syear = $request->get('syear');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }

        $res['searchedItem'] = $request->item_code;
        $res['statusTypes'] = itemStatus::where('sub_institute_id',$sub_institute_id)->whereNull('deleted_at')->orderBy('no_loan')->get()->toArray();

        $res['bookData'] = itemScanDetail::join('library_items as li',function($join){
            $join->on('li.item_code','=','item_scan_details.item_code')->on('item_scan_details.sub_institute_id','=','li.sub_institute_id');
        })
        ->join('library_books as lb',function($join){
            $join->on('li.book_id','=','lb.id')->on('item_scan_details.sub_institute_id','=','lb.sub_institute_id');
        })
        ->selectRaw('item_scan_details.*,lb.title as book_title,lb.material_resource_type as collection_type')
        ->where(['item_scan_details.sub_institute_id'=>$sub_institute_id,'item_scan_details.syear'=>$syear])
        ->when($request->item_code!='',function($q) use($request){
            $q->where('item_scan_details.item_code',$request->item_code);
        })
        ->whereNull('item_scan_details.deleted_at')
        ->get()
        ->toArray(); 
        // return "hello";
        return is_mobile($type, "library/bookVarification/scanBookRemark", $res, "view");    
    }

    public function remarksStore(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $syear = $request->get('syear');
                $syear = $request->get('syear');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }
        // return $request;exit;
        $checked = $request->checked;
        $item_statusArr = $request->item_status;
        $item_remarksArr = $request->remarks;
        $i=0;
        foreach ($checked as $key => $value) {
            $item_status = isset($item_statusArr[$key]) ? $item_statusArr[$key] : 0;
            $item_remarks = isset($item_remarksArr[$key]) ? $item_remarksArr[$key] : 0;

            $update = itemScanDetail::where('id',$key)->update([
                'remarks'=>$item_remarks,
                'item_status_id'=>$item_status,
                'updated_at'=>now()
            ]);
            $i++;
        }

        if($i>0){
            $res['status'] = "1";
            $res['message'] = "Book Verification Updated";
        }else{
            $res['status'] = "0";
            $res['message'] = "Book Verification Failed";
        }
        return is_mobile($type, "scan_books_remarks.index", $res);    
    }

    public function verifiedReport(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $syear = $request->get('syear');
                $syear = $request->get('syear');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }

        $res['searchedItem'] = $request->item_code;
        $res['searchedYear'] = $request->year;
        $res['all_year'] = session()->get('academicYears');

        $res['bookData'] = itemScanDetail::join('library_items as li',function($join){
            $join->on('li.item_code','=','item_scan_details.item_code')->on('item_scan_details.sub_institute_id','=','li.sub_institute_id');
        })
        ->join('library_books as lb',function($join){
            $join->on('li.book_id','=','lb.id')->on('item_scan_details.sub_institute_id','=','lb.sub_institute_id');
        })
        ->selectRaw('item_scan_details.*,lb.title as book_title,lb.material_resource_type as collection_type')
        ->where(['item_scan_details.sub_institute_id'=>$sub_institute_id])
        ->when($request->item_code!='',function($q) use($request){
            $q->where('item_scan_details.item_code',$request->item_code);
        })
        ->when($request->year!='',function($q) use($request){
            $q->where('item_scan_details.syear',$request->year);
        })
        ->whereNull('item_scan_details.deleted_at')
        ->get()
        ->toArray(); 
        // echo "<pre>";print_r(session()->all());exit;
        return is_mobile($type, "library/bookVarification/varifedReports", $res, "view");    
    }

    public function verifyPendingReport(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $syear = $request->get('syear');
                $syear = $request->get('syear');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }
        $res['searchedItem'] = $request->item_code;
        $res['searchedYear'] = $request->year;
        $res['all_year'] = session()->get('academicYears');

        $res['bookData'] = DB::table('library_items as li')
        ->join('library_books as lb', function ($join) {
            $join->on('li.book_id', '=', 'lb.id')
                 ->on('li.sub_institute_id', '=', 'lb.sub_institute_id');
        })
        ->leftJoin('item_scan_details as isd', 'li.item_code', '=', 'isd.item_code')
        ->selectRaw('isd.item_code as not_found,isd.syear,li.item_code,lb.title as book_title,lb.material_resource_type as collection_type')
        ->where('li.sub_institute_id', $sub_institute_id)
        ->when($request->item_code != '', function ($q) use ($request) {
            $q->where('li.item_code', $request->item_code);
        })
        ->when($request->year!='',function($q) use($request){
            $q->where('isd.syear',$request->year);
        })
        ->whereNull('isd.item_code')
        ->get();
    

        return is_mobile($type, "library/bookVarification/varifyPending", $res, "view");    
    }
}
