<?php

namespace App\Http\Controllers\frontdesk;

use App\Http\Controllers\Controller;
use App\Models\frontdesk\PettyCashMasterModel;
use App\Models\frontdesk\PettyCashModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class PettyCashReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $title_arr = PettyCashMasterModel::select('*')
            ->where(['sub_institute_id' => $sub_institute_id])
            ->get();
        $res['Title_Arr'] = $title_arr;

        return is_mobile($type, 'frontdesk/show_pettycashreport', $res, "view");
    }

    public function getpettycashreport(Request $request)
    {
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $title_id = $request->get('title_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $pettycashdata = PettyCashModel::from("petty_cash as p")
            ->select('p.*', 'pm.title as title_name', db::raw('date_format(p.created_on,"%Y-%m-%d") as bill_date'),
                DB::raw('concat(u.first_name," ",u.middle_name," ",u.last_name) as user_name'))
            ->join('petty_cash_master as pm', 'pm.id', '=', 'p.title_id')
            ->join('tbluser as u', 'u.id', '=', 'p.user_id')
            ->where(['p.sub_institute_id' => $sub_institute_id, 'p.title_id' => $title_id])
            ->whereBetween('p.created_on', array($from_date, $to_date))
            ->get();

        $type = $request->input('type');
        $data['pettycashdata'] = $pettycashdata;
        $data['from_date'] = $from_date;
        $data['to_date'] = $to_date;
        $data['title_id'] = $title_id;
        $title_arr = PettyCashMasterModel::select('*')
            ->where(['sub_institute_id' => $sub_institute_id])
            ->get();
        $data['Title_Arr'] = $title_arr;

        return is_mobile($type, 'frontdesk/show_pettycashreport', $data, "view");
    }
}
