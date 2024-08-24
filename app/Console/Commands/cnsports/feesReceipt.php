<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class feesReceipt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_receipt';

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
        $check_receipt = DB::table('fees_receipt')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_receipt)){
            // db::enableQueryLog();
            $last_receipt =  DB::table('fees_receipt')
            ->join('fees_collect', function ($join) {
                $join->on('fees_collect.sub_institute_id', '=', 'fees_receipt.sub_institute_id');
            })->select('fees_receipt.*')
            ->where('fees_receipt.syear', '=', 2023)
            ->where('fees_collect.sub_institute_id', '=', 257)
            ->where('fees_collect.term_id', 'like', '%2024')
            ->whereRaw('fees_collect.id IN (fees_receipt.FEES_ID)')
            ->get()->toArray();
            // dd(db::getQueryLog($last_receipt));
            // echo "<pre>";print_r($last_receipt);exit;
            if(!empty($last_receipt)){
            foreach($last_receipt as $key => $value){
            $insertHouse = DB::table('fees_receipt')
            ->where(['FEES_ID'=>$value->FEES_ID])->Update([
               'syear'=>2024                                        
                ]);
            }
        }
            $message="Updated successfully";                
        }else{
            $message="Already Inserted";
        }

        echo ('Fees Reciept : '.$message)."<br>";
    }
}
