<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class FeesTitle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:fees_title';

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
        $check_FeesTitle = DB::table('fees_title')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_FeesTitle)){
            $last_FeesTitle = DB::table('fees_title')->where(['sub_institute_id'=>257,'syear'=>2023])->get()->toArray();
            if(!empty($last_FeesTitle)){
            foreach($last_FeesTitle as $key => $value){
            $insert_FeesTitle = DB::table('fees_title')->insert([
                'fees_title_id'=>$value->fees_title_id,     
                'fees_title'=>$value->fees_title,                
                'display_name'=>$value->display_name,                
                'sort_order'=>$value->sort_order,                
                'cumulative_name'=>$value->cumulative_name,                
                'append_name'=>$value->append_name,  
                'mandatory'=>$value->mandatory,
                'syear'=>2024,
                'sub_institute_id'=>257,
                'other_fee_id'=>$value->other_fee_id,                
                'rollover_id'=>$value->rollover_id,                
                'created_at'=>now(),                                
                ]);
            }
        }
            $message="Inserted successfully";                
        }else{
            $message="Already Inserted";
        }

        echo ('Fees Title : '.$message)."<br>";
    }
}
