<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class FeesReceiptBookMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_receipt_book';

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
        $check_FeesReceiptBook = DB::table('fees_receipt_book_master')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_FeesReceiptBook)){
            $last_FeesReceiptBook = DB::table('fees_receipt_book_master')->where(['sub_institute_id'=>257,'syear'=>2023])->get()->toArray();
            if(!empty($last_FeesReceiptBook)){
                foreach($last_FeesReceiptBook as $key => $value){

                    $get_feesTitleId = DB::table('fees_title')->where(['sub_institute_id'=>257,'syear'=>2023])->where('id',$value->fees_head_id)->first();

                    $fees_title = DB::table('fees_title')->where(['sub_institute_id'=>257,'syear'=>2024])->where('display_name',$get_feesTitleId->display_name)->first();

                    $updateBreakoff = DB::table('fees_receipt_book_master')
                            ->updateOrInsert(
                                [
                                    'syear' => 2024,
                                    'grade_id'=>$value->grade_id,
                                    'standard_id'=>$value->standard_id,
                                    'fees_head_id'=>$value->fees_head_id,
                                    'sub_institute_id' => 257                                    
                                ],
                                [
                                    'syear' => 2024,
                                    'receipt_id' => $value->receipt_id,
                                    'receipt_line_1' => $value->receipt_line_1,
                                    'receipt_line_2' => $value->receipt_line_2,
                                    'receipt_line_3' => $value->receipt_line_3,
                                    'receipt_line_4' => $value->receipt_line_4,
                                    'receipt_prefix' => $value->receipt_prefix,
                                    'receipt_postfix' => $value->receipt_postfix,
                                    'receipt_logo' => $value->receipt_logo,
                                    'account_number' => $value->account_number,
                                    'sort_order' => $value->sort_order,
                                    'last_receipt_number' => $value->last_receipt_number,
                                    'grade_id' => $value->grade_id,
                                    'standard_id' => $value->standard_id,
                                    'fees_head_id' => $fees_title->id,                                    
                                    'status' => $value->status,
                                    'pan' => $value->pan,
                                    'bank_logo' => $value->bank_logo,
                                    'branch' => $value->branch,                                    
                                    'sub_institute_id' => 257,
                                    'created_on' => now(),
                                ]
                            );

                    // echo ($fees_title->display_name.'-'.$fees_title->syear);echo "</br>";
                    $message="Inserted successfully";  

                    }
                    
                }
            // $message="Updated Failed";  
        }else{
            $message="Already Inserted";
        }

        echo ('Fees Receipt Book Master : '.$message);
    }
}
