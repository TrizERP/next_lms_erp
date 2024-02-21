<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class feesPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_payment';

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
        $check_payment = DB::table('fees_payment')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_payment)){
            $last_payment =  DB::table('fees_payment')
            ->join('fees_collect', function ($join) {
                $join->on('fees_collect.cheque_no', '=', 'fees_payment.icici_order_id')
                    ->where('fees_collect.syear', '=', 2024)
                    ->where('fees_collect.term_id', 'like', '%2024')
                    ->where('fees_collect.student_id', '=', DB::raw('fees_payment.student_id'));
            })->select('fees_payment.*')
            ->where('fees_payment.sub_institute_id', '=', 257)
            ->get()->toArray();
            // echo "<pre>";print_r($last_payment);exit;
            if(!empty($last_payment)){
            foreach($last_payment as $key => $value){
            $insertHouse = DB::table('fees_payment')
            ->where([
                'icici_order_id' => $value->icici_order_id,                
            ])
            ->update([
               'syear'=>2024,
                'Updated_at' => now(),
                ]);
            }
        }
            $message="Update successfully";                
        }else{
            $message="Already Inserted";
        }

        echo ('Fees Payment : '.$message)."<br>";
    }
}
