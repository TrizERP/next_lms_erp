<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use App\Models\admission\admissionEnquiryModel;
use App\Models\admission\admissionFollowUpModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class admissionFollowUpController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $enquiry_id = $request->input('enquiry_id');
        $module = $request->input('module');

        $data = admissionEnquiryModel::where(['id' => $enquiry_id])->get()->toArray();

        $follow_up_data = admissionFollowUpModel::where(['enquiry_id' => $enquiry_id])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data['0'];
        $res['enquiry_id'] = $enquiry_id;
        $res['module'] = $module;
        if (count($follow_up_data) > 0) {
            $res['followUpData'] = $follow_up_data;
        }

        return is_mobile($type, 'admission/follow_up/show_admission_follow_up', $res, 'view');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {       
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $user_id = $request->session()->get("user_id");
        $syear = $request->session()->get("syear");
        $data = $request->except(['_method', '_token', 'submit']);

        $data['created_on'] = date('Y-m-d H:i:s');
        $data['created_ip'] = Request::getClientIp();
        $data['sub_institute_id'] = $sub_institute_id;

        admissionFollowUpModel::insert($data);

        $res['status_code'] = "1";
        $res['message'] = "Follow Up Added successfully";

        return is_mobile($type, "admission_enquiry.index", $res);
    }

}
