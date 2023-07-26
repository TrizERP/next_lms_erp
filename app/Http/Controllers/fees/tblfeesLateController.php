<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use App\Models\fees\tblfeesLateModel;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\standardModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class tblfeesLateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = tblfeesLateModel::selectRaw('fees_late_master.*')
            ->selectRaw("CONCAT_WS(' ',tbluser.first_name,tbluser.last_name) as user")
            ->selectRaw("standard.name as standard")
            ->join('tbluser', 'fees_late_master.created_by', '=', 'tbluser.id')
            ->join('standard', 'fees_late_master.standard_id', '=', 'standard.id')
            ->where(['fees_late_master.sub_institute_id' => $sub_institute_id, 'fees_late_master.syear' => $syear])
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, "fees/show_fees_late", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $term_list = academic_yearModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        view()->share('standard_list', $data);
        view()->share('term_list', $term_list);

        return view('fees/add_fees_late');
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
        $type = $request->input('type');
        $standard_ids = $request['standard_id'];
        foreach ($standard_ids as $key => $value) {
            $request->request->set('standard_id', $value);
            $data = $this->saveData($request);
        }

        $res['status_code'] = "1";
        $res['message'] = "Fees Late Start Date Added successfully";

        return is_mobile($type, "fees_late_master.index", $res);
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
            if ($key != '_method' && $key != '_token' && $key != 'submit') {
                if ($key === 'late_date') {
                    $formattedDate = date('Y-m-d', strtotime($value));
                    $finalArray[$key] = $formattedDate;
                } else {
                    if (is_array($value)) {
                        $value = implode(",", $value);
                    }
                    $finalArray[$key] = $value;
                }
            }
        }

        tblfeesLateModel::insert($finalArray);

        return DB::getPdo()->lastInsertId();
    }

    public function updateData(Request $request)
    {
        $newRequest = $request->all();
        $id = $newRequest['id'];
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'id') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        return tblfeesLateModel::where(['id' => $id])->update($finalArray);

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
     * @return Application|Factory|View
     */
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $term_list = academic_yearModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        view()->share('standard_list', $data);
        view()->share('term_list', $term_list);
        $editData = tblfeesLateModel::find($id)->toArray();

        return view('fees/edit_fees_late', ['data' => $editData]);
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
        $type = $request->input('type');

        $request->request->add(['id' => $id]); //add request

        $this->updateData($request);

        $res['status_code'] = "1";
        $res['message'] = "Fees Late Start Date Updated successfully";

        return is_mobile($type, "fees_late_master.index", $res);
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
        tblfeesLateModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Fees Late Start Date deleted successfully";

        return is_mobile($type, "fees_late_master.index", $res);
    }
}
