<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class FeesIcici extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_icici';

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
       $check_feesIcici = DB::table('fees_icici')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();   
       if(empty($check_feesIcici)){
           $last_feesIcici = DB::table('fees_icici')->where(['sub_institute_id'=>257,'syear'=>2023])->get()->toArray();
           if(!empty($last_feesIcici)){
           foreach($last_feesIcici as $key => $value){
           $insertIcici = DB::table('fees_icici')->insert([
               'syear'=>2024,
               'merchant_id'=>$value->merchant_id,
               'pg_reference_no'=>$value->pg_reference_no,    
               'sub_merchant_id'=>$value->sub_merchant_id,             
               'enc_key'=>$value->enc_key,                                           
               'medium'=>$value->medium,
               'sub_institute_id'=>257,
               'created_at'=>now(),
               ]);
           }
       }
           $message="Inserted successfully";                
       }else{
           $message="Already Inserted";
       }

       echo ('Fees Online Mapping : '.$message)."<br>";
    }
}
