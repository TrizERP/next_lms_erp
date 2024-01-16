<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class FeesBreakoffOther extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_breakoff_other';

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
        $check_FeesBreakoff = DB::table('fees_breakoff_other')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_FeesBreakoff)){
            $last_breakoff = DB::table('fees_breakoff_other')->where(['sub_institute_id'=>257,'syear'=>2023])->get()->toArray();
            if(!empty($last_breakoff)){
                foreach($last_breakoff as $key => $value){
                    $months = substr($value->month_id,1,4);              
                    // echo ($months);echo "</br>";
                    if($months==2024){
                        $get_feesTitleId = DB::table('fees_title')->where(['sub_institute_id'=>257,'syear'=>2023])->where('fees_title',$value->fee_type_id)->first();

                        $fees_title = DB::table('fees_title')->where(['sub_institute_id'=>257,'syear'=>2024])->where('display_name',$get_feesTitleId->display_name)->first();

                        $updateBreakoff = DB::table('fees_breakoff_other')
                            ->updateOrInsert(
                                [
                                    'student_id'=>$value->student_id,
                                    'syear' => 2024,
                                    'fee_type_id'=>$value->fee_type_id,
                                    'month_id' => $value->month_id,
                                    'sub_institute_id' => 257,
                                ],
                                [
                                    'amount' => $value->amount,
                                    'created_at' => now(),
                                ]
                            );

                    // echo ($fees_title->display_name.'-'.$fees_title->syear);echo "</br>";
                    $message="Inserted successfully";  

                    }
                    
                }
            }
            // $message="Updated Failed";  
        }else{
            $message="Already Inserted";
        }

        echo ('Fees Breakoff Other : '.$message);
    }

}
