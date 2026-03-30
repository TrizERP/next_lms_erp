<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\h5p\H5pScenario;
use App\Models\h5p\H5pScenarioPoint;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class H5PScenarioController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institutue_id = session()->get('sub_institute_id');
        $user_profile_name = session()->get('user_profile_name');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institutue_id = $request->input('sub_institute_id');
            $user_profile_name = $request->input('user_profile_name');
        }

        $res['scenarioLists'] = H5pScenario::with('points')->where(['standard_id' => $request->standard_id, 'subject_id' => $request->subject_id, 'chapter_id' => $request->chapter_id])->where('sub_institute_id', $sub_institutue_id)->whereNull('deleted_at')
            ->orderBy('id', 'desc')->get();
        // return $sub_institutue_id;
        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;
        return is_mobile($type, 'lms/h5p/scenario/index', $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institutue_id = session()->get('sub_institute_id');
        if (in_array($type, ['API', 'JSON'])) {
            $sub_institutue_id = $request->input('sub_institute_id');
        }
        $res['scenarioLists'] = H5pScenario::where('sub_institute_id', $sub_institutue_id)->whereNull('deleted_at')->get();

        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;
        return is_mobile($type, 'lms/h5p/scenario/create', $res, "view");
    }


    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institutue_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institutue_id = $request->input('sub_institute_id');
            $user_id = $request->input('user_id');
        }

        // Upload file
        $file = $request->file('image');
        $filename = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $size = $file->getSize();
        $newfilename = 'scenario_' . date('Y-m-d_h-i-s') . '.' . $ext;
        Storage::disk('digitalocean')->putFileAs('public/h5p_content/', $file, $newfilename, 'public');
        $file_path = Storage::disk('digitalocean')->url('public/h5p_content/' . $newfilename);

        // Create scenario and get the inserted ID
        $scenario = H5pScenario::create([
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $file_path,
            'sub_institute_id' => $sub_institutue_id,
            'created_by' => $user_id,
            'created_at' => now(),
        ]);

        // Get the last inserted ID
        $scenario_id = $scenario->id;

        // Decode points
        $points = json_decode($request->points, true);

        // Create points with the correct scenario_id
        foreach ($points as $point) {
            H5pScenarioPoint::create([
                'scenario_id' => $scenario_id, // Use the correct ID
                'title' => $point['title'],
                'description' => $point['description'],
                'position_x' => $point['x'],
                'position_y' => $point['y'],
                'sub_institute_id' => $sub_institutue_id,
                'created_by' => $user_id,
                'created_at' => now(),
            ]);
        }

        $res['status'] = ($scenario) ? 1 : 0;
        $res['message'] = ($scenario) ? 'Scenario created successfully' : 'Scenario creation failed';

        if ($type == 'API') {
            return response()->json($res);
        }
        return redirect()->route('scenario_based.index', ['chapter_id' => $request->chapter_id, 'standard_id' => $request->standard_id, 'subject_id' => $request->subject_id])->with('data', $res);
    }

    // Edit function
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->input('sub_institute_id');
        }

        $scenario = H5pScenario::with(['points' => function ($query) {
            $query->orderBy('id');
        }])->where([
            'id' => $id
        ])->whereNull('deleted_at')->first();

        if (!$scenario) {
            if (in_array($type, ['API', 'JSON'])) {
                return response()->json(['error' => 'Scenario not found'], 404);
            }
            return redirect()->back()->with('data', ['status' => false, 'message' => 'Scenario not found']);
        }

        $data = [
            'scenario' => $scenario,
            'chapter_id' => $request->chapter_id,
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
        ];

        return is_mobile($type, 'lms/h5p/scenario/edit', $data, "view");
    }

    // Update function
    public function update(Request $request, $id)
    {
        $type = $request->input('type');
        try {
            $sub_institute_id = session()->get('sub_institute_id');
            $user_id = session()->get('user_id');
            if($type=="API"){
                $sub_institute_id = $request->get('sub_institute_id');
                $user_id = $request->get('user_id');
            }

            // Find scenario
            $scenario = H5pScenario::where([
                'id' => $id,
                'standard_id' => $request->standard_id,
                'subject_id' => $request->subject_id,
                'chapter_id' => $request->chapter_id,
                'sub_institute_id' => $sub_institute_id
            ])->whereNull('deleted_at')->first();

            if (!$scenario) {
                return redirect()->back()->with('data', ['status' => false, 'message' => 'Scenario not found']);
            }

            // Update image if provided
            if ($request->hasFile('image')) {
                $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif|max:2048']);

                // Delete old image
                if ($scenario->file_path && Storage::disk('public')->exists($scenario->file_path)) {
                    Storage::disk('public')->delete($scenario->file_path);
                }

                // Upload new image
                $imagePath = $request->file('image')->store('scenario_images', 'public');
                $scenario->file_path = $imagePath;
            }

            // Update scenario details
            $scenario->title = $request->title;
            $scenario->description = $request->description;
            $scenario->updated_by = $user_id;
            $scenario->updated_at = now();
            $scenario->save();

            // Handle points
            if ($request->has('points')) {
                $points = json_decode($request->points, true);

                // Get existing point IDs
                $existingPointIds = $scenario->points->pluck('id')->toArray();
                $updatedPointIds = [];

                foreach ($points as $point) {
                    if (isset($point['id']) && in_array($point['id'], $existingPointIds)) {
                        // Update existing point
                        H5pScenarioPoint::where('id', $point['id'])->update([
                            'title' => $point['title'],
                            'description' => $point['description'],
                            'position_x' => $point['x'],
                            'position_y' => $point['y'],
                            'updated_by' => $user_id,
                            'updated_at' => now(),
                        ]);
                        $updatedPointIds[] = $point['id'];
                    } else {
                        // Create new point
                        $newPoint = H5pScenarioPoint::create([
                            'scenario_id' => $scenario->id,
                            'title' => $point['title'],
                            'description' => $point['description'],
                            'position_x' => $point['x'],
                            'position_y' => $point['y'],
                            'sub_institute_id' => $sub_institute_id,
                            'created_by' => $user_id,
                            'created_at' => now(),
                        ]);
                        $updatedPointIds[] = $newPoint->id;
                    }
                }

                // Delete points that were removed
                $pointsToDelete = array_diff($existingPointIds, $updatedPointIds);
                if (!empty($pointsToDelete)) {
                    H5pScenarioPoint::whereIn('id', $pointsToDelete)->delete();
                }
            }

            return redirect()->route('scenario_based.index', [
                'standard_id' => $request->standard_id,
                'subject_id' => $request->subject_id,
                'chapter_id' => $request->chapter_id
            ])->with('data', ['status' => true, 'message' => 'Scenario updated successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('data', ['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Delete point function (AJAX)
    public function deletePoint(Request $request, $id)
    {
        try {
            $point = H5pScenarioPoint::find($id);
            if ($point) {
                $point->delete();
                return response()->json(['success' => true, 'message' => 'Point deleted successfully']);
            }
            return response()->json(['success' => false, 'message' => 'Point not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->type;
        $user_id = session()->get('user_id');
        if($type=="API"){
            $user_id=$request->get('user_id');
        }
        // Soft delete points
        H5pScenarioPoint::where('scenario_id', $id)->update([
            'deleted_by' => $user_id,
            'deleted_at' => now()
        ]);
        // Soft delete scenario
        $scenario = H5pScenario::find($id);
        $delete = false;
        if ($scenario) {
            $scenario->deleted_by = $user_id;
            $scenario->deleted_at = now();
            $delete = $scenario->save();
        }
        $res['status'] = $delete ? true : false;
        $res['message'] = $delete ? 'Scenario deleted successfully!' : 'Scenario not found!';
        if ($type == "API") {
            return $res;
        }
        return redirect()->route('scenario_based.index', [
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id
        ])->with('data', $res);
    }

    public function show(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->input('sub_institute_id');
        }

        $scenario = H5pScenario::with(['points' => function ($query) {
            $query->orderBy('id');
        }])->where([
            'id' => $id
        ])->whereNull('deleted_at')->first();

        if (!$scenario) {
            if (in_array($type, ['API', 'JSON'])) {
                return response()->json(['error' => 'Scenario not found'], 404);
            }
            return redirect()->back()->with('data', ['status' => false, 'message' => 'Scenario not found']);
        }

        $data = [
            'scenario' => $scenario,
            'chapter_id' => $request->chapter_id,
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
        ];

        return is_mobile($type, 'lms/h5p/scenario/show', $data, "view");
    }

  public function getH5pAIScenario(Request $request)
{
    $prompt = $request->input('prompt');
    $imageBase64 = $request->input('image');
    $title = $request->input('title');
    $standard = $request->input('standard');
    $chapter = $request->input('chapter');
    $subject = $request->input('subject');
    
    if (!$prompt && !$imageBase64) {
        return response()->json([
            'status_code' => 0,
            'message' => 'Prompt or image is required',
        ]);
    }

    try {
        // Add educational context to the prompt
        $contextPrompt = $prompt;
        
        if ($standard || $chapter || $subject) {
            $contextPrompt = "Educational Context:\n";
            if ($subject) $contextPrompt .= "- Subject: " . $subject . "\n";
            if ($standard) $contextPrompt .= "- Grade/Standard: " . $standard . "\n";
            if ($chapter) $contextPrompt .= "- Chapter: " . $chapter . "\n";
            $contextPrompt .= "\n" . $prompt;
        }
        
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
                    ['role' => 'system', 'content' => 'You are an expert educational content creator specializing in creating age-appropriate learning materials. You analyze images and create detailed, accurate educational content. You understand human anatomy, biology, fruits, plants, and various educational subjects. Always return valid JSON responses.'],
                    ['role' => 'user', 'content' => $contextPrompt],
                ],
                'max_tokens' => 4096,
                'temperature' => 0.7,
                'top_p' => 0.9,
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
            // If structure is invalid, create a fallback response
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
