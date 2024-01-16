<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;
class FeesBreakOff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_breakoff';

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
        $check_FeesBreakoff = DB::table('fees_breackoff')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_FeesBreakoff)){
            $last_breakoff = DB::table('fees_breackoff')->where(['sub_institute_id'=>257,'syear'=>2023])->get()->toArray();
            if(!empty($last_breakoff)){
                foreach($last_breakoff as $key => $value){
                    $months = substr($value->month_id,1,4);              
                    // echo ($months);echo "</br>";
                    if($months==2024){
                        $get_feesTitleId = DB::table('fees_title')->where(['sub_institute_id'=>257,'syear'=>2023])->where('id',$value->fee_type_id)->first();

                        $fees_title = DB::table('fees_title')->where(['sub_institute_id'=>257,'syear'=>2024])->where('display_name',$get_feesTitleId->display_name)->first();

                        // $updateBreakoff = DB::table('fees_breackoff')->where('month_id',$value->month_id)->where(['sub_institute_id'=>257,'syear'=>2023])->where(['grade_id'=>$value->grade_id,'standard_id'=>$value->standard_id,'quota'=>$value->quota,'amount'=>$value->amount,'fee_type_id'=>$value->fee_type_id])->update([
                        //     'syear'=>2024,
                        //     'admission_year'=>$value->admission_year,
                        //     'fee_type_id'=>$fees_title->id,
                        //     'quota'=>$value->quota,
                        //     'grade_id'=>$value->grade_id,
                        //     'standard_id'=>$value->standard_id,
                        //     'section_id'=>$value->section_id,
                        //     'month_id'=>$value->month_id,
                        //     'amount'=>$value->amount,                            
                        //     'sub_institute_id'=>257,                            
                        //     'updated_at'=>now(),
                        //     ]);
                        $updateBreakoff = DB::table('fees_breackoff')
                            ->updateOrInsert(
                                [
                                    'month_id' => $value->month_id,
                                    'sub_institute_id' => 257,
                                    'syear' => 2024,
                                    'grade_id'=>$value->grade_id,'standard_id'=>$value->standard_id,'quota'=>$value->quota,'amount'=>$value->amount,'fee_type_id'=>$value->fee_type_id
                                ],
                                [
                                    'admission_year' => $value->admission_year,
                                    'fee_type_id' => $fees_title->id,
                                    'quota' => $value->quota,
                                    'grade_id' => $value->grade_id,
                                    'standard_id' => $value->standard_id,
                                    'section_id' => $value->section_id,
                                    'amount' => $value->amount,
                                    'updated_at' => now(),
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

        echo ('Fees Breakoff : '.$message);
    }
}
