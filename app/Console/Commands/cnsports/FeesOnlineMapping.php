<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class FeesOnlineMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_online_map';

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
        $check_feesOnlineMap = DB::table('fees_online_maping')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();   
        if(empty($check_feesOnlineMap)){
            $last_feesOnlineMap = DB::table('fees_online_maping')->where(['sub_institute_id'=>257,'syear'=>2023])->get()->toArray();
            if(!empty($last_feesOnlineMap)){
            foreach($last_feesOnlineMap as $key => $value){
            $insertOnlineMap = DB::table('fees_online_maping')->insert([
                'syear'=>2024,
                'bank_name'=>$value->bank_name,
                'fees_type'=>$value->fees_type,                
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
