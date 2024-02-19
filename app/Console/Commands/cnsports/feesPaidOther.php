<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use App\Models\fees\other_fee_map\other_fee_map;
use DB;

class feesPaidOther extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_paid_other';

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
       $check_feesPaidOther = DB::table('fees_paid_other')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();   
       $message="records not matched";
       if(empty($check_feesPaidOther)){
           $last_feesPaidOther = DB::table('fees_paid_other')->where(['sub_institute_id'=>257,'syear'=>2023])->where('month_id', 'like', '%2024')->get()->toArray();
        //    echo "<pre>";print_r($last_feesPaidOther->{1});exit;
           if(!empty($last_feesPaidOther)){
           foreach($last_feesPaidOther as $key => $value){
               $months = substr($value->month_id,1,4);              
               // echo ($months);echo "</br>";
               if($months==2024){
              
                   $updateBreakoff = DB::table('fees_paid_other')
                   ->where( [
                    'reciept_id'=>"$value->reciept_id",
                    'sub_institute_id'=>257,
                    'student_id'=>$value->student_id,
                    'month_id' => $value->month_id,
                    'is_deleted' => $value->is_deleted,                                
                   ])
                    ->update([
                        'syear' => 2024,                         
                           ]);
            //    / $record = new other_fee_map;

            //     // Assign values to the columns
            //     $record->reciept_id=$value->reciept_id;
            //     $record->syear =2024;
            //     $record->sub_institute_id=257;
            //     $record->student_id=$value->student_id;
            //     $record->month_id = $value->month_id;
            //     $record->is_deleted = $value->is_deleted;   
            //     $record->fine = $value->fine;
            //     $record->bank_name = $value->bank_name;
            //     $record->bank_branch = $value->bank_branch;
            //     $record->cheque_dd_no = $value->cheque_dd_no;
            //     $record->cheque_dd_date = $value->cheque_dd_date;
            //     $record->payment_mode = $value->payment_mode;
            //     $record->actual_amountpaid = $value->actual_amountpaid;
            //     $record->fees_discount = $value->fees_discount;
            //     $record->receivedby = $value->receivedby;                              
            //     $record->paid_fees_html = $value->paid_fees_html;                               
            //     $record->is_waved = $value->is_waved;                               
            //     $record->created_date = $v/alue->created_date;                               
            //     $record->receiptdate = $value->receiptdate;                               
            //     $record->remarks = $value->remarks;                               
            //     $record->created_by = $value->created_by;         
            //     $record->{'1'} =  $value->{1};   
            //     $record->{'2'} =  $value->{2};   
            //     $record->{'3'} =  $value->{3};   
            //     $record->{'4'} =  $value->{4};   
            //     $record->{'5'} =  $value->{5};   
            //     $record->{'6'} =  $value->{6};   
            //     $record->{'7'} =  $value->{7};   
            //     $record->{'8'} =  $value->{8};   
            //     $record->{'9'} =  $value->{9};   
            //     // Save the record to the database
            //     $record->save();//

               // echo ($fees_title->display_name.'-'.$fees_title->syear);echo "</br>";
               $message="Updated successfully";  

               }
           }
       }
           $message="Updated successfully";                
       }else{
           $message="Already Inserted";
       }

       echo ('Fees Paid Other : '.$message)."<br>";
    }
}
