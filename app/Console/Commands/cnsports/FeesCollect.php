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
                        ->updateOrInsert(
                            [
                                'student_id'=>$value->student_id,
                                'standard_id'=>$value->standard_id,
                                'term_id' => $value->term_id,
                                'syear' => 2024,
                                'sub_institute_id' => 257,
                                'receipt_no'=>$value->receipt_no,
                                'is_deleted' => $value->is_deleted,                                
                            ],
                            [
                                'fees_html' => $value->fees_html,
                                'created_by' => $value->created_by,
                                'created_ip_address' => $value->created_ip_address,
                                'payment_mode' => $value->payment_mode,
                                'bank_branch' => $value->bank_branch,
                                'receiptdate' => $value->receiptdate,
                                'cheque_no' => $value->cheque_no,
                                'bank_name' => $value->bank_name,
                                'cheque_date' => $value->cheque_date,
                                'cheque_bank_name' => $value->cheque_bank_name,
                                'remarks' => $value->remarks,
                                'created_date' => $value->created_date,
                                'amount' => $value->amount,
                                'fine' => $value->fine,
                                'fees_discount' => $value->fees_discount,   
                                'is_waved' => $value->is_waved,                                
                                'tution_fee' => $value->tution_fee,                                
                                'admission_fee' => $value->admission_fee,                                
                                'activity_fee' => $value->activity_fee,                                
                                'term_fee' => $value->term_fee,                                
                                'deposit' => $value->deposit,                                
                                'co_curriculam_fees' => $value->co_curriculam_fees,
                                'computer_fees' => $value->computer_fees,                                
                                'smart_class' => $value->smart_class,                                
                                'security_charges' => $value->security_charges,
                                'photograph' => $value->photograph,
                                'cal_misc' => $value->cal_misc,
                                'title_1' => $value->title_1,
                                'title_2' => $value->title_2,
                                'title_3' => $value->title_3,
                                'title_4' => $value->title_4,
                                'title_5' => $value->title_5,
                                'title_6' => $value->title_6,
                                'title_7' => $value->title_7,
                                'title_8' => $value->title_8,
                                'title_9' => $value->title_9,
                                'title_10' => $value->title_10,
                                'title_11' => $value->title_11,
                                'title_12' => $value->title_12,
                                'conciliation' => $value->conciliation,   
                            ]
                        );

                // echo ($fees_title->display_name.'-'.$fees_title->syear);echo "</br>";
                $message="Inserted successfully";  

                }
            }
        }
            $message="Inserted successfully";                
        }else{
            $message="Already Inserted";
        }
 
        echo ('Fees Cancel : '.$message)."<br>";
    }
}
