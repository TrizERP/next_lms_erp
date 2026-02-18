<?php
namespace App\Http\Controllers;
   
use BotMan\BotMan\BotMan;
use Illuminate\Http\Request;
use BotMan\BotMan\Messages\Incoming\Answer;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use Illuminate\Support\Facades\Log;

class BotManController extends Controller
{
    /**
     * Place your BotMan logic here.
     */
    public function handle()
    {
        $botman = app('botman');
   
        $botman->hears('{message}', function($botman, $message) {
   
            if ($message == 'hi') {
                $this->askName($botman);
            }
            // to ask pending fees 
           else if ($message == 'pending fees') {
                $this->askPendingFees($botman);
            }

            else{
                $botman->reply("Start a conversation by saying hi.");
            }
   
        });
   
        $botman->listen();
    }
   
    /**
     * Place your BotMan logic here.
     */
    public function askName($botman)
    {
        $botman->ask('Hello! What is your Name?', function(Answer $answer) {
   
            $name = $answer->getText();
   
            $this->say('Nice to meet you '.$name.'. for fees details please type "pending fees"');
        });
    }

    // for pending fees 
    public function askPendingFees($botman)
    {
        $botman->ask('Please Enter Gr No', function(Answer $answer) {
   
            $grno = $answer->getText();
            // make request to send in fees controller
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');

            $reqArr=[
                'type'=>"API",
                'grno'=>$grno,
                'sub_institute_id'=>$sub_institute_id,
                'syear'=>$syear,
            ];

            $request = new Request($reqArr);
            // send created request to fees_collect controller function show_student
            $feesController = new fees_collect_controller;
            $feesData = json_decode($feesController->show_student($request));
        
            // dd($feesData);exit;
            if(isset($feesData->stu_data) && !empty($feesData->stu_data)){
                $details = 'Fees Details';
                foreach ($feesData->stu_data as $key => $value) {
                    $details .= '<br><br><p><b>Student Name : <b>'.$value->first_name . ' ' . $value->middle_name . ' ' . $value->last_name.'<br><b>GR No. : <b>'.$value->enrollment_no.'<br><b>Pending Fees : <b>'.$value->bkoff.'</p>';
                }
                $this->say($details);
            }else{
                $this->say('Not able to find fees from this GR No.');
            }

        });
    }
}