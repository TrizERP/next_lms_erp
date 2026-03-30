<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class H5PIndexController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institutue_id = session()->get('sub_institutue_id');
        if (in_array($type, ['API', 'JSON'])) {
            $sub_institutue_id = $request->input('sub_institutue_id');
        }
        $res['contentLists'] = [
            [
                'id' => 1,
                'title' => 'Scenario',
                'description' => 'scenario based learning learn from image',
                'icon' => 'fa fa-image',
                'route' => 'scenario_based.index',
            ],
            [
                'id' => 2,
                'title' => 'Multiple Choice Questions',
                'description' => 'Multiple Choice Questions',
                'icon' => 'mdi mdi-help-circle-outline',
                'route' => 'h5p_mcq.index',
            ],
            [
                'id' => 3,
                'title' => 'Interactive Video',
                'description' => 'Interactive Video',
                'icon' => 'mdi mdi-help-circle-outline',
                'route' => 'h5p_interactive_video.index',
            ]
        ];
        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;
        // return $res;
        return is_mobile($type, 'lms/h5p/index', $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    public function getH5pAIOutput(Request $request)
{
    $prompt = $request->input('prompt');

    if (!$prompt) {
        return response()->json([
            'status_code' => 0,
            'message' => 'Prompt is required',
        ]);
    }

    try {
        // Use OpenRouter API with DeepSeek model
        $client = new Client();
        $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
            'verify' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'https://nextlms.in',
                'X-Title' => 'Next LMS ERP',
            ],
            'json' => [
                'model' => 'deepseek/deepseek-chat',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 4096,
                'temperature' => 1.8,
                'top_p' => 0.5,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        $generatedText = $data['choices'][0]['message']['content'];
        
        // Parse the generated text to extract JSON
        $jsonData = null;
        
        // Try to extract JSON from the response
        if (preg_match('/\{[\s\S]*\}/', $generatedText, $matches)) {
            $jsonString = $matches[0];
            $jsonData = json_decode($jsonString, true);
        }
        
        // If JSON parsing failed, try to extract using another method
        if (!$jsonData) {
            // Try to find JSON between triple backticks
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $generatedText, $matches)) {
                $jsonData = json_decode($matches[1], true);
            }
        }
        
        // If still no JSON, try to parse the entire response as JSON
        if (!$jsonData) {
            $jsonData = json_decode($generatedText, true);
        }
        
        // Validate the structure
        if ($jsonData && isset($jsonData['description']) && isset($jsonData['points'])) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Success',
                'description' => $jsonData['description'],
                'points' => $jsonData['points'],
                'raw_output' => $generatedText
            ]);
        } else {
            // If structure is invalid, return error with raw output
            return response()->json([
                'status_code' => 0,
                'message' => 'AI response format invalid. Expected JSON with description and points.',
                'raw_output' => $generatedText
            ]);
        }
        
    } catch (RequestException $e) {
        return response()->json([
            'status_code' => 0,
            'message' => 'API call failed: ' . $e->getMessage(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status_code' => 0,
            'message' => 'Something went wrong: ' . $e->getMessage(),
        ]);
    }
}
}
