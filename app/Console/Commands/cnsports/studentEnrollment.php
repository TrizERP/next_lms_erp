<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;
// 
class studentEnrollment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:student_enrollment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $current_students = DB::table('tblstudent_enrollment')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($current_students)){
            $last_students = DB::table('tblstudent_enrollment')->where(['sub_institute_id'=>257,'syear'=>2023])->get()->toArray();
            if(!empty($last_students))
            foreach($last_students as $key => $value){
                $current_house = DB::table('house_master')->where(['sub_institute_id'=>257,'syear'=>2023])->where('id',$value->house_id)->first();
                $next_house =  DB::table('house_master')->where(['sub_institute_id'=>257,'syear'=>2024])->where('house_name',$current_house->house_name)->first();
                $insertFeesMapYear = DB::table('tblstudent_enrollment')->insert([
                    'syear'=>2024,
                    'student_id'=>$value->student_id,
                    'grade_id'=>$value->grade_id,
                    'standard_id'=>$value->standard_id,
                    'section_id'=>$value->section_id,
                    'student_quota'=>$value->student_quota,
                    'start_date'=>$value->start_date,      
                    'end_date'=>$value->end_date,      
                    'enrollment_code'=>$value->enrollment_code, 
                    'term_id'=>$value->term_id,
                    'remarks'=>$value->remarks,
                    'house_id'=>$next_house->id,
                    'sub_institute_id'=>257,
                    'created_on'=>now()
                    ]);
                
                // echo $current_house->house_name."<br>";
            }
            
            $student_message="Inserted successfully";                
        }else{
            $student_message="Already Inserted";
        }

        echo ('Student Enrollment : '.$student_message)."<br>";
        // return Command::SUCCESS;
    }
}
