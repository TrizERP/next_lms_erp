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
            $insertHouse = DB::table('fees_payment')->insert([
                'student_id' => $value->student_id,
                'syear' => 2024,
                'amount' => $value->amount,
                'fine' => $value->fine,
                'icici_order_id' => $value->icici_order_id,
                'icici_plain_request' =>$value->icici_plain_request,
                'icici_encrypt_request'=>$value->icici_encrypt_request,
                'icici_payment_status' => $value->icici_payment_status,
                'icici_bank_res' => $value->icici_payment_status,
                'icici_payment_date' => $value->icici_payment_date,
                'razorpay_payment_status' => $value->razorpay_payment_status,
                'razorpay_bank_res' => $value->razorpay_bank_res,
                'razorpay_order_id' => $value->razorpay_order_id,
                'razorpay_dashboard_ps' => $value->razorpay_dashboard_ps,
                'razorpay_payment_date' => $value->razorpay_payment_date,
                'payphi_order_id' => $value->payphi_order_id,
                'payphi_request' => $value->payphi_request,
                'payphi_response' => $value->payphi_response,
                'payphi_payment_status' => $value->payphi_payment_status,
                'payphi_payment_date' => $value->payphi_payment_date,
                'sub_institute_id' => 257,
                'created_at' => now(),
                ]);
            }
        }
            $message="Inserted successfully";                
        }else{
            $message="Already Inserted";
        }

        echo ('Fees Payment : '.$message)."<br>";
    }
}
