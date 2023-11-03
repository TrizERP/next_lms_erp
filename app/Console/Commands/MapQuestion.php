<?php

namespace App\Console\Commands;

use App\Http\Controllers\AJAXController;
use App\Http\Controllers\lms\contentController;
use App\Models\lms\lmsQuestionMappingModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class mapQuestion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'map:question';

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
        $questionMasters = DB::table('lms_question_master as lq')->leftJoin('lms_question_mapping as lqm','lq.id','=','lqm.questionmaster_id')->selectRaw('lq.id as id,lq.question_title,lq.standard_id')->whereRaw('lqm.id IS NULL')->groupBy('lq.question_title')->get();
        $controller = new AJAXController();
        $contentMappingType=[];
        if(!empty($questionMasters)){
        foreach ($questionMasters as $questionMaster) {
            // echo $questionMaster->id.",";
            $std_name = DB::table('standard')->where('id', $questionMaster->standard_id)->first();

            if (!$std_name) {
                continue; // Skip if standard not found
            }

            $question = $questionMaster->question_title;
            $standard = $std_name->name;
            $type_depth = 9;
            $type_bloom = 82;
            $type_learning = 'learn';

            // Create a new instance of Request
            $request = new \Illuminate\Http\Request();

            // Set your variables as input data in the request
            $request->merge([
                'question' => $question,
                'standard' => $standard,
                'type_depth' => $type_depth,
                'type_bloom' => $type_bloom,
                'type_learning' => $type_learning,
            ]);

            // Use the request in your controller
            $response = $controller->chat($request);

            if ($response) {
                $response = json_decode($response, true);
                if(isset($response[0]['question_depth'])){

                $questionDepthValueId = DB::table('lms_mapping_type')->where('name', $response[0]['question_depth'])->value('id');
                }
                if( isset($response[0]['question_bloom'])){
                $questionBloomValueId = DB::table('lms_mapping_type')->where('name', $response[0]['question_bloom'])->value('id');
                }
                if( isset($response[0]['question_learning'])){                
                DB::table('lms_question_master')->where('id', $questionMaster->id)->update([
                    'learning_outcome' => $response[0]['question_learning'],
                ]);
                }
                if(isset($questionDepthValueId) && isset($questionBloomValueId)){
                $contentMappingType = [
                    [
                        'questionmaster_id' => $questionMaster->id,
                        'mapping_type_id' => 9,
                        'mapping_value_id' => $questionDepthValueId,
                        'reasons' => $response[0]['reason_depth'] ?? '',
                    ],
                    [
                        'questionmaster_id' => $questionMaster->id,
                        'mapping_type_id' => 82,
                        'mapping_value_id' => $questionBloomValueId,
                        'reasons' => $response[0]['reason_bloom'] ?? '',
                    ],
                ];
            }
                // lmsQuestionMappingModel::where(["questionmaster_id" => $questionMaster->id])->delete();
               if(!empty($contentMappingType)){
                lmsQuestionMappingModel::insert($contentMappingType);
                echo "<pre>";print_r($contentMappingType);
               }
            }
        }
    }else{
        echo "All Question Mapped Successfully !!";
    }

    }
}
