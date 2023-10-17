<?php

namespace App\Http\Controllers\bazar;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function PHPUnit\Framework\fileExists;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class bulkUploadedReportController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'bazar/report/search', $res, "view");
    }

    public function show_bazar_report(Request $request)
    {

        $type = $request->input('type');
        $report_of = $request->input('report_of');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $user_name = session()->get('user_name');

        $extra = '';

        if ($report_of == 'position_report') 
        {

            if(session()->get('user_profile_name') == 'Student')
                $extra .= " AND client_id = '". $user_name ."' ";

            $position_bazar_report = DB::table("sharebazar_position")
                ->selectRaw("sharebazar_position.*");

                if ($from_date != '' && $to_date != '') {
                    $position_bazar_report = $position_bazar_report->whereRaw("DATE_FORMAT(upload_date, '%Y-%m-%d') BETWEEN '" . $from_date . "' AND '" . $to_date . "' $extra ");
                }

                $position_bazar_report = $position_bazar_report->get()->toarray();

                // dd(DB::getQueryLog($result));
                $position_bazar_report = json_decode(json_encode($position_bazar_report), true);

                $data['position_bazar_report'] = $position_bazar_report;

                return is_mobile($type, "bazar/report/position_report_show", $data, "view");
        }

        if ($report_of == 'margin_report') 
        {

            if(session()->get('user_profile_name') == 'Student')
                $extra .= " AND Code = '". $user_name ."' ";

            $margin_bazar_report = DB::table("sharebazar_margin")
                ->selectRaw("sharebazar_margin.*");

                if ($from_date != '' && $to_date != '') 
                {
                    $margin_bazar_report = $margin_bazar_report->whereRaw("DATE_FORMAT(upload_date, '%Y-%m-%d') BETWEEN '" . $from_date . "' AND '" . $to_date . "' $extra ");
                }

                $margin_bazar_report = $margin_bazar_report->get()->toarray();

                // dd(DB::getQueryLog($result));
                $margin_bazar_report = json_decode(json_encode($margin_bazar_report), true);

                $data['margin_bazar_report'] = $margin_bazar_report;

                return is_mobile($type, "bazar/report/margin_report_show", $data, "view");
        }

        if ($report_of == 'pnl_report') 
        {
            if(session()->get('user_profile_name') == 'Student')
                $extra .= " AND code = '". $user_name ."' ";

            $pnl_bazar_report = DB::table("sharebazar_pnl")
                ->selectRaw("sharebazar_pnl.*");

                if ($from_date != '' && $to_date != '') 
                {
                    $pnl_bazar_report = $pnl_bazar_report->whereRaw("DATE_FORMAT(upload_date, '%Y-%m-%d') BETWEEN '" . $from_date . "' AND '" . $to_date . "' $extra ");
                }

                $pnl_bazar_report = $pnl_bazar_report->get()->toarray();

                // dd(DB::getQueryLog($result));
                $pnl_bazar_report = json_decode(json_encode($pnl_bazar_report), true);

                $data['pnl_bazar_report'] = $pnl_bazar_report;

                return is_mobile($type, "bazar/report/pnl_report_show", $data, "view");
        }
    }
}
