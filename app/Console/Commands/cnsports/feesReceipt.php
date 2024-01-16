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
            $last_receipt =  DB::table('fees_receipt')
            ->join('fees_collect', function ($join) {
                $join->on('fees_collect.sub_institute_id', '=', 'fees_receipt.sub_institute_id');
            })->select('fees_receipt.*')
            ->where('fees_receipt.syear', '=', 2023)
            ->where('fees_collect.sub_institute_id', '=', 257)
            ->where('fees_collect.term_id', 'like', '%2024')
            ->whereRaw('fees_collect.id IN (fees_receipt.FEES_ID)')
            ->get()->toArray();
            // echo "<pre>";print_r($last_receipt);exit;
            if(!empty($last_receipt)){
            foreach($last_receipt as $key => $value){
            $insertHouse = DB::table('fees_receipt')->insert([
                'FEES_ID'=>$value->FEES_ID,
                'OTHER_FEES_ID'=>$value->OTHER_FEES_ID,
                'RECEIPT_ID_1'=>$value->RECEIPT_ID_1,
                'RECEIPT_ID_2'=>$value->RECEIPT_ID_2,
                'RECEIPT_ID_3'=>$value->RECEIPT_ID_3,
                'RECEIPT_ID_4'=>$value->RECEIPT_ID_4,
                'RECEIPT_ID_5'=>$value->RECEIPT_ID_5,
                'RECEIPT_ID_6'=>$value->RECEIPT_ID_6,
                'RECEIPT_ID_7'=>$value->RECEIPT_ID_7,
                'RECEIPT_ID_8'=>$value->RECEIPT_ID_8,
                'RECEIPT_ID_9'=>$value->RECEIPT_ID_9,
                'RECEIPT_ID_10'=>$value->RECEIPT_ID_10,
                'SYEAR'=>$value->SYEAR,
                'SUB_INSTITUTE_ID'=>$value->SUB_INSTITUTE_ID,
                'STANDARD'=>$value->STANDARD,     
                'CREATED_ON'=>$value->CREATED_ON,                                           
                ]);
            }
        }
            $message="Inserted successfully";                
        }else{
            $message="Already Inserted";
        }

        echo ('Fees Reciept : '.$message)."<br>";
    }
}
