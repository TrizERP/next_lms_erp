<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;
class houseMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:house_master';

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
        $check_house = DB::table('house_master')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_house)){
            $last_houses = DB::table('house_master')->where(['sub_institute_id'=>257,'syear'=>2023])->get()->toArray();
            if(!empty($last_houses)){
            foreach($last_houses as $key => $value){
            $insertHouse = DB::table('house_master')->insert([
                'sub_institute_id'=>257,
                'syear'=>2024,
                'house_name'=>$value->house_name,
                'created_at'=>now(),
                'created_by'=>$value->created_by,
                'created_ip'=>$value->created_ip,
                'sort_order'=>$value->sort_order,
                ]);
            }
        }
            $message="Inserted successfully";                
        }else{
            $message="Already Inserted";
        }

        echo ('House Master : '.$message)."<br>";
        // return Command::SUCCESS;
    }
}
