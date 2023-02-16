<?php

namespace App\Http\Controllers\lms;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\lms\leaderboard\lb_masterModel;
use App\Models\lms\leaderboard\lb_pointsModel;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lmsLeaderboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request); 
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";      
        // $res['total_points'] = $data['total_points'];      
        // $res['modulewise_points'] = $data['modulewise_points'];      
        $res['lb_Data'] = $data;
        return is_mobile($type,'lms/show_lmsLeaderboard',$res,"view");  
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_id = $request->session()->get('user_id');
        $syear = $request->session()->get('syear');

        $data = $modulewise_points = array();        

        //Get Student Current Standard and Leader board Points
        $studData = DB::select("SELECT l.*,m.icon,se.standard_id,se.section_id
            FROM lb_points AS l
            inner join tblstudent s on l.user_id = s.id and l.sub_institute_id = s.sub_institute_id
            inner join tblstudent_enrollment se on se.student_id = s.id and se.sub_institute_id = s.sub_institute_id
            inner join lb_master m on l.module_name = m.module_name and m.standard_id = se.standard_id
            WHERE l.sub_institute_id = '".$sub_institute_id."' AND l.user_id = '".$user_id."' 
            AND l.user_profile_id = '".$user_profile_id."' AND l.syear = '".$syear."'"
            );          

        if(count($studData) > 0)
        {
            $studData = json_decode(json_encode($studData),true);            
           
            $total_points = 0;

            //Make Studen Module wise points array
            foreach($studData as $key => $val)
            {
                $total_points += $val['points'];
                $modulewise_points[$val['module_name']]['ICON'] = $val['icon'];
                $modulewise_points[$val['module_name']]['DATA'][$val['inserted_date']] = $val['points'];
                $standard_id = $val['standard_id'];
            }

            //Get Class wise Rank and Class data
            //$statement = DB::statement("SET @a=0");
            $classdata = DB::select("
                    SELECT sum(points) as total_points,l.user_id,CONCAT_WS(' ' ,s.first_name,s.middle_name,s.last_name) as student_name
                    FROM lb_points AS l
                    INNER JOIN tblstudent s on l.user_id = s.id and l.sub_institute_id = s.sub_institute_id
                    INNER JOIN tblstudent_enrollment se on se.student_id = s.id and se.sub_institute_id = s.sub_institute_id
                    WHERE l.sub_institute_id = '".$sub_institute_id."' AND se.standard_id = '".$standard_id."' and se.syear = '".$syear."'
                    GROUP BY user_id
                    ORDER BY total_points DESC
                    LIMIT 5"           
                );  
            $classdata = json_decode(json_encode($classdata),true);        

            $data['total_points'] = $total_points;
            $data['modulewise_points'] = $modulewise_points; 
            $data['student_rank'] = (array_search($user_id, array_column($classdata, 'user_id')) + 1);
            $data['classdata'] = $classdata;
        }        
        return $data;       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request,$id)
    {
    
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {          
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id)
    {
        
    }    
    
}
