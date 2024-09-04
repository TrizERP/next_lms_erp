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
        /* Origginal Query - Hide by rajesh
        $questionMasters = DB::table('lms_question_master as lq')->leftJoin('lms_question_mapping as lqm','lq.id','=','lqm.questionmaster_id')->selectRaw('lq.id as id,lq.question_title,lq.standard_id')->whereRaw('lqm.id IS NULL')->groupBy('lq.question_title')->get();
        */

        $questionMasters = DB::table('lms_question_master as lq')
		    ->select('lq.id as id', 'lq.question_title', 'lq.standard_id')
		    ->leftJoin('lms_question_mapping as lqm', function ($join) {
		        $join->on('lq.id', '=', 'lqm.questionmaster_id')
		             ->where('lqm.mapping_type_id', '=', 82);
		    })
		    ->where('lq.question_type_id', 1)
		    ->whereNull('lqm.id')
		    ->where('sub_institute_id', 195)
            //->limit(10)
		    ->get();

        $controller = new AJAXController();
        $contentMappingType= [];
        $i=1;
        if(!empty($questionMasters)){
        foreach ($questionMasters as $questionMaster) {
            // echo $questionMaster->id.",";
            $std_name = DB::table('standard')->where('id', $questionMaster->standard_id)->first();

            if (!$std_name) {
                continue; // Skip if standard not found
            }

            $question = $questionMaster->question_title;
            $standard = $std_name->name;
            //$type_depth = 9;
            $type_bloom = 82;
            $type_learning = 'learn';

            // Create a new instance of Request
            $request = new \Illuminate\Http\Request();

            // Set your variables as input data in the request
            $request->merge([
                'question' => $question,
                'standard' => $standard,
                //'type_depth' => $type_depth,
                'type_bloom' => $type_bloom,
                'type_learning' => $type_learning,
            ]);

            // Use the request in your controller
            $responses = $controller->chat($request);
//echo "<pre>";
//print_r($responses);
            if (is_string($responses)) {

                $response = json_decode($responses, true);

//$medium = ['Easy','Medium','Hard']; //Depth of Knowledge
$medium = ['Creating','Evaluate','Analyse','Apply','Understand','Remember','Identify']; //Blooms Taxonomy
$value = isset($response['question_bloom']) ? $response['question_bloom'] : ($response['answer'] ?? null);

                if(isset($value)){//question_depth

                    // Using array_filter to find matches
                    $matches = array_filter($medium, function($item) use ($value) {
                        return stripos($item, $value) !== false;
                    });

                    // If you just need the first match
                    $bloom_value = reset($matches);

                    if(in_array($bloom_value, $medium)){
                        $questionBloomValueId = DB::table('lms_mapping_type')->where('name', $bloom_value)->where('globally', 1)->value('id');
                    }/*elseif(in_array($response['reason_depth'], $medium)){
                        $questionDepthValueId = DB::table('lms_mapping_type')->where('name', $response['reason_depth'])->where('globally', 1)->value('id');
                    }*/elseif(in_array($response['reason_bloom'], $medium)){
                        $questionBloomValueId = DB::table('lms_mapping_type')->where('name', $response['reason_bloom'])->where('globally', 1)->value('id');
                    }
                }
                /*if( isset($response[0]['question_depth'])){
                $questionBloomValueId = DB::table('lms_mapping_type')->where('name', $response[0]['question_depth'])->value('id');
                }*/
                if( isset($response['question_learning'])){                
                    DB::table('lms_question_master')->where('id', $questionMaster->id)->update([
                        'learning_outcome' => $response['question_learning'],
                    ]);
                }
                if(isset($questionBloomValueId)){// && isset($questionDepthValueId)
                    $contentMappingType = [];
                    $contentMappingType = [
                        /*[
                            'questionmaster_id' => $questionMaster->id,
                            'mapping_type_id' => 9,
                            'mapping_value_id' => $questionDepthValueId ?? rand(10,12),
                            'reasons' => $response['reason_depth'] ?? $questionDepthValueId,
                        ],*/
                        [
                            'questionmaster_id' => $questionMaster->id,
                            'mapping_type_id' => 82,
                            'mapping_value_id' => $questionBloomValueId ?? rand(83,88),
                            'reasons' => $response['reason_bloom'] ?? $questionBloomValueId,
                        ],
                    ];
                }
                // lmsQuestionMappingModel::where(["questionmaster_id" => $questionMaster->id])->delete();
               if(!empty($contentMappingType)){
                lmsQuestionMappingModel::insert($contentMappingType);
                //echo "<pre>";print_r($contentMappingType);
                echo ".";
                $i++;
               }
            }
        }
    }else{
        echo "All Question Mapped Successfully !!";
    }

    }
}
