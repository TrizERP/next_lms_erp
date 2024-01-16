<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class FeesCancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_cancel';

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
       $check_feesCancel = DB::table('fees_cancel')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();   
       if(empty($check_feesCancel)){
           $last_feesCancel = DB::table('fees_cancel')->where(['sub_institute_id'=>257,'syear'=>2023])->where('term_id', 'like', '%2024')->get()->toArray();
           if(!empty($last_feesCancel)){
           foreach($last_feesCancel as $key => $value){
            //   echo $value->term_id;
                $updateOrInsert = DB::table('fees_cancel')
                        ->updateOrInsert([
                                'reciept_id' => $value->reciept_id,                            
                                'syear' => 2024,
                                'sub_institute_id' => 257,
                                'student_id'=>$value->student_id,
                                'standard_id'=>$value->standard_id,
                                'term_id' => $value->term_id
                            ],
                            [
                                'amountpaid' => $value->amountpaid,
                                'received_date' => $value->received_date,   
                                'cancel_date' => $value->cancel_date,                                
                                'cancel_type' => $value->cancel_type,                                
                                'cancel_remark' => $value->cancel_remark,                                
                                'cancelled_by' => $value->cancelled_by,
                                'ip_address' => $value->ip_address,                                
                            ]);
           }
       }
           $message="Inserted successfully";                
       }else{
           $message="Already Inserted";
       }

       echo ('Fees Cancel : '.$message)."<br>";
    }
}
