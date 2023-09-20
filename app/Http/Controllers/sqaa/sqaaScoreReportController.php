<?php

namespace App\Http\Controllers\sqaa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\sqaa\sqaa_master;
use App\Models\sqaa\sqaa_mark;
use App\Models\sqaa\sqaa_document;
use Illuminate\Support\Facades\Storage;
use DB;

class sqaaScoreReportController extends Controller
{
    //
    public function index(Request $request)
    {
        $type="";
        $level_2=array();
        $level_3 = array();        
        $level_4 = array();
        $sub_institute_id = session()->get('sub_institute_id');
        
        $res['level_1'] =sqaa_master::where('level',1)->get()->toArray();
        $res['level_2'] =sqaa_master::where('level',2)->get()->toArray();
        $res['level_3'] =sqaa_master::where('level',3)->get()->toArray();
        $res['level_4'] =sqaa_mark::join('sqaa_master','sqaa_marks.menu_id','=','sqaa_master.id')->where('sqaa_master.level',4)->get()->toArray();            

         $i = 0;
        foreach ($res['level_2'] as $key => $value) {
            $level_2[$value['parent_id']][$i] = $res['level_2'][$key];
            $i++;
        }

        $i = 0;
        foreach ($res['level_3'] as $key => $value) {
            $level_3[$value['parent_id']][$i] = $res['level_3'][$key];
            $i++;
        }
        foreach ($res['level_4'] as $key => $value) {
            $mark = $value['mark'];
            $level_4[$value['parent_id']] = $mark;
            $i++;            
        }
        
        $res['level_2'] =$level_2;
        $res['level_3'] =$level_3;
        $res['level_4'] =$level_4;        
        // echo "<pre>";print_r($res['level_3']['8']);exit;
        
           return is_mobile($type, "sqaa/scoreReport", $res, "view");
    }
}
