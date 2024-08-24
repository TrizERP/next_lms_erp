<?php

namespace App\Console\Commands\cnsports;

use Illuminate\Console\Command;
use DB;

class academicYearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'next:academic_year';

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
        // INSERT INTO academic_year (term_id,syear,sub_institute_id,title,short_name,sort_order,start_date,end_date,post_start_date,post_end_date,does_grades,does_exams,created_at) VALUES (1,2024,257,'Sports Academy','T-1',1,'2024-01-01','2025-12-31','2024-01-01','2025-12-31','Y','Y',NOW());
        
        $check_academic = DB::table('academic_year')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_academic)){
            $insertAcademicYear = DB::table('academic_year')->insert([
                'term_id'=>1,
                'syear'=>2024,
                'sub_institute_id'=>257,
                'title'=>'Sports Academy',
                'short_name'=>"T-1",
                'sort_order'=>1,
                'start_date'=>'2024-01-01',
                'end_date'=>'2025-12-31',
                'post_start_date'=>'2024-01-01',
                'post_end_date'=>'2025-12-31',
                'does_grades'=>'Y',
                'does_exams'=>'Y',
                'created_at'=>now()
                ]);
            $message="Inserted successfully";                
        }else{
            $message="Already Inserted";
        }

        $check_fees = DB::table('fees_map_years')->where(['sub_institute_id'=>257,'syear'=>2024])->get()->toArray();
        if(empty($check_fees)){
            $insertFeesMapYear = DB::table('fees_map_years')->insert([
                'from_month'=>4,
                'to_month'=>12,
                'syear'=>2024,
                'sub_institute_id'=>257,
                'created_at'=>now()
                ]);
            $fees_message="Inserted successfully";                
        }else{
            $fees_message="Already Inserted";
        }

        echo ('Academic Year : '.$message)."<br>";
        echo ('Fees Map Year : '.$fees_message);
        // return Command::SUCCESS;
    }
}
