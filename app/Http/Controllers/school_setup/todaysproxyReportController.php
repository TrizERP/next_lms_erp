<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\proxyModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class todaysproxyReportController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['status_code'] = 1;
        $proxydata = $this->getproxyreport($request);
        $data['proxydata'] = $proxydata;

        return is_mobile($type, 'school_setup/show_todaysproxyreport', $data, "view");
    }

    public function getproxyreport(Request $request)
    {
        $from_date = date('y-m-d');
        $to_date = date('y-m-d');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');
        return proxyModel::select('proxy_master.*', 's.name as standard_name', 'd.name as division_name',
            DB::raw('concat(u.first_name," ",u.middle_name," ",u.last_name) as teacher_name'),
            DB::raw('concat(u1.first_name," ",u1.middle_name," ",u1.last_name) as proxy_teacher_name'),
            'p.title as period_name', DB::raw('concat(sub.subject_name,"(",sub.subject_code,")") as sub_name'))
            ->join('standard as s',function($join) use($marking_period_id){
                $join->on( 's.id', '=', 'proxy_master.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division as d', 'd.id', '=', 'proxy_master.division_id')
            ->join('tbluser as u', 'u.id', '=', 'proxy_master.teacher_id')
            ->join('tbluser as u1', 'u1.id', '=', 'proxy_master.proxy_teacher_id')
            ->join('period as p', 'p.id', '=', 'proxy_master.period_id')
            ->join('subject as sub', 'sub.id', '=', 'proxy_master.subject_id')
            ->where(['proxy_master.sub_institute_id' => $sub_institute_id])
            ->whereBetween('proxy_date', [$from_date, $to_date])
            ->get();
    }

}
