<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class FeesCollect extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_collect';

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
        // return Command::SUCCESS;
        $check_feesCollect = DB::table('fees_collect')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();   
        if(empty($check_feesCollect)){
            $last_feesCollect = DB::table('fees_collect')->where(['sub_institute_id'=>257,'syear'=>2023])->where('term_id', 'like', '%2024')->get()->toArray();
            // echo "<pre>";print_r($last_feesCollect);exit;
            if(!empty($last_feesCollect)){
            foreach($last_feesCollect as $key => $value){
                $months = substr($value->term_id,1,4);              
                // echo ($months);echo "</br>";
                if($months==2024){
               
                    $updateBreakoff = DB::table('fees_collect')
                    ->where( [
                        'student_id'=>$value->student_id,
                        'standard_id'=>$value->standard_id,
                        'term_id' => $value->term_id,
                        'sub_institute_id' => 257,
                        'receipt_no'=>$value->receipt_no,
                        'is_deleted' => $value->is_deleted,                                
                    ])
                    ->update([
                            'syear' => 2024,
                            ]);

                // echo ($fees_title->display_name.'-'.$fees_title->syear);echo "</br>";
                $message="Updated successfully";  

                }
            }
        }
            $message="Updated successfully";                
        }else{
            $message="Already Inserted";
        }
 
        echo ('Fees Cancel : '.$message)."<br>";
    }
}
