<?php
namespace App\Botman;
    
use Illuminate\Http\Request;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use Illuminate\Support\Facades\Log;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;

class OnboardingConversation extends Conversation{

    public function run(){
        $this->askService();
    }

    public function askService()
    {
        $question = Question::create('Are you looking for the pending fees for a student?')
            ->callbackId('select_service')
            ->addButtons([
                Button::create('Pending Fees')->value('pending')
            ]);

        $this->ask($question, function(Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $selectedValue = $answer->getValue();
                // $this->say('you choose '.$selectedValue.' button');
                if ($selectedValue === 'pending') {
                    $this->askPendingFees();
                } else {
                    $this->reply('Alright, let me know if you need anything else.');
                }
            }
        });
    }

    // for pending 
    public function askPendingFees()
    {
        $this->ask('Please Enter GR No', function(Answer $answer) {
            $grno = $answer->getText();
            // make request to send in fees controller
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');

            $reqArr = [
                'type' => "API",
                'grno' => $grno,
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
            ];

            $request = new Request($reqArr);
            // send created request to fees_collect controller function show_student
            $feesController = new fees_collect_controller;
            $feesData = json_decode($feesController->show_student($request));

            // dd($feesData);exit;
            if (isset($feesData->stu_data) && !empty($feesData->stu_data)) {
                $details = 'Fees Details';
                foreach ($feesData->stu_data as $key => $value) {
                    $details .= '<br><br><p><b>Student Name : </b>' . $value->first_name . ' ' . $value->middle_name . ' ' . $value->last_name . '<br><b>GR No. : </b>' . $value->enrollment_no . '<br><b>pending : </b>' . $value->bkoff . '</p>';
                }
                $this->say($details);
            } else {
                $this->say('Not able to find fees from this GR No.');
            }
        });
    }
}
?>