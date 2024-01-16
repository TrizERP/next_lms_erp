<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class FeesConfigMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_config';

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
        $check_config = DB::table('fees_config_master')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_config)){
            $last_config = DB::table('fees_config_master')->where(['sub_institute_id'=>257,'syear'=>2023])->get()->toArray();
            if(!empty($last_config)){
            foreach($last_config as $key => $value){
            $insertHouse = DB::table('fees_config_master')->insert([
                'syear'=>2024,
                'sub_institute_id'=>257,
                'late_fees_amount'=>$value->late_fees_amount,
                'send_sms'=>$value->send_sms,
                'send_email'=>$value->send_email,
                'fees_receipt_template'=>$value->fees_receipt_template,
                'fees_bank_challan_template'=>$value->fees_bank_challan_template,
                'fees_receipt_note'=>$value->fees_receipt_note,
                'institute_name'=>$value->institute_name,
                'pan_no'=>$value->pan_no,
                'account_to_be_credited'=>$value->account_to_be_credited,
                'cms_client_code'=>$value->cms_client_code,
                'auto_head_counting'=>$value->auto_head_counting,
                'nach_account_type'=>$value->nach_account_type,
                'nach_registration_charge'=>$value->nach_registration_charge,
                'nach_transaction_charge'=>$value->nach_transaction_charge,
                'nach_failed_charge'=>$value->nach_failed_charge,
                'bank_logo'=>$value->bank_logo,
                'created_by'=>$value->created_by,
                'created_on'=>now(),
                'show_month'=>$value->show_month,          
                ]);
            }
        }
            $message="Inserted successfully";                
        }else{
            $message="Already Inserted";
        }

        echo ('Fees Config Master : '.$message)."<br>";
        // return Command::SUCCESS;
        // return Command::SUCCESS;
    }
}
