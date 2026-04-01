<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\lms\h5p\H5pInteractiveVideo;
use App\Models\lms\h5p\H5pVideoInteraction;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\is_mobile;

class H5PInteractiveVideoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->sub_institute_id;
        }

        $res['videolists'] = H5pInteractiveVideo::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $request->standard_id, 'subject_id' => $request->subject_id, 'chapter_id' => $request->chapter_id])->latest()->get();

        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;
        $res['chapter_id'] = $request->chapter_id;
        // return $res;
        return is_mobile($type, 'lms/h5p/interactiveVideo/index', $res, 'view');
    }

    public function create(Request $request)
    {
        $type = $request->type;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;
        $res['chapter_id'] = $request->chapter_id;
        return is_mobile($type, 'lms/h5p/interactiveVideo/create', $res, 'view');
    }

    public function store(Request $request)
    {
        // return $request;
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $syear = session()->get('syear');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_id = $request->user_id;
            
        // Validate the request
        $request->validate([
            'sub_institute_id' => 'required',
            'syear' => 'required',
            'user_id' => 'required',
            'title' => 'required|string|max:255',
            'video_path' => 'required|file|mimes:mp4,mov,avi,mkv,webm|max:1024000',
            'chapter_id' => 'required',
            'subject_id' => 'required',
            'standard_id' => 'required',
            'interactions' => 'required|array|min:1',
            'interactions.*.time' => 'required|numeric',
            'interactions.*.interaction_type' => 'required|in:multiple_choice,true_false,text_input',
            'interactions.*.question' => 'required|string|max:500',
        ]);

        }

        // Handle video upload
        $video_path = null;
        if ($request->hasFile('video_path')) {
            $file = $request->file('video_path');
            $ext = $file->getClientOriginalExtension();
            $newfilename = 'interactiveVideo_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $ext;

            try {
                // Try to store on DigitalOcean
                $stored = Storage::disk('digitalocean')->putFileAs('public/h5p_content/', $file, $newfilename, 'public');
                if ($stored) {
                    $video_path = Storage::disk('digitalocean')->url('public/h5p_content/' . $newfilename);
                } else {
                    throw new \Exception('DigitalOcean upload failed');
                }
            } catch (\Exception $e) {
                // Fallback to local storage
                $destinationPath = public_path('/h5p_content/');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                $file->move($destinationPath, $newfilename);
                $video_path = asset('/h5p_content/' . $newfilename);
            }
        }
        // Create video record
        $video = H5pInteractiveVideo::create([
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
            'syear' => $syear,
            'sub_institute_id' => $sub_institute_id,
            'title' => $request->title,
            'video_path' => $video_path,
            'created_by' => $user_id,
            'created_at' => now(),
        ]);

        // Save interactions
        if ($request->interactions && $video) {
            foreach ($request->interactions as $interaction) {
                // Prepare data based on interaction type
                $options = null;
                $correct_answer = null;

                if ($interaction['interaction_type'] == 'multiple_choice') {
                    // Build options array dynamically: {1:"Option 1",2:"Option 2",...}
                    $options = [];
                    if (isset($interaction['options']) && is_array($interaction['options'])) {
                        foreach ($interaction['options'] as $key => $value) {
                            $options[$key + 1] = $value;
                        }
                    }
                    $correct_answer = $interaction['correct_answer'] ?? null;
                } elseif ($interaction['interaction_type'] == 'true_false') {
                    // Build options array: {1:"true",2:"false"}
                    $options = [1 => "true", 2 => "false"];
                    $correct_answer = $interaction['correct_answer'] ?? null;
                } else {
                    // Text input: optional correct answer
                    $correct_answer = $interaction['correct_answer'] ?? null;
                }

                H5pVideoInteraction::create([
                    'video_id' => $video->id,
                    'time' => $interaction['time'], // Already converted to seconds in blade
                    'interaction_type' => $interaction['interaction_type'],
                    'question' => $interaction['question'],
                    'options' => $options ? json_encode($options) : null,
                    'correct_answer' => $correct_answer,
                    'created_by' => $user_id,
                    'created_at' => now(),
                ]);
            }
        }

        $res['status'] = $video ? 1 : 0;
        $res['message'] = $video ? 'Video created successfully' : 'Video creation failed';

        if ($type == 'API') {
            return response()->json($res);
        }

        return redirect()->route('h5p_interactive_video.index', [
            'chapter_id' => $request->chapter_id,
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id
        ])->with('data', $res);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->type;
        $data['video'] = $video = H5pInteractiveVideo::with('interactions')->findOrFail($id);
        $data['chapter_id'] = $data['video']->chapter_id;
        $data['subject_id'] = $data['video']->subject_id;
        $data['standard_id'] = $data['video']->standard_id;
        // return $data;
        if(empty($data['video'])){
            $res['status'] = $video ? 1 : 0;
            $res['message'] = $video ? 'Video not found' : 'Video not found';
            return redirect()->back()->with($res);
        }
        return is_mobile($type, 'lms/h5p/interactiveVideo/edit', $data, 'view');
    }

    public function update(Request $request, $id)
    {
        // return $request;
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $syear = session()->get('syear');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_id = $request->user_id;
              // Validate the request
        $request->validate([
            'sub_institute_id' => 'required',
            'user_id' => 'required',
            'syear' => 'required',
            'title' => 'required|string|max:255',
            'video_path' => 'required|string', // For edit, it's a path/URL, not file upload
            'chapter_id' => 'required',
            'subject_id' => 'required',
            'standard_id' => 'required',
            'interactions' => 'required|array|min:1',
            'interactions.*.time' => 'required|numeric',
            'interactions.*.interaction_type' => 'required|in:multiple_choice,true_false,text_input',
            'interactions.*.question' => 'required|string|max:500',
        ]);
        }

        $video = H5pInteractiveVideo::findOrFail($id);

        // Handle video file upload if new file is provided
       $updateData = [
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
            'syear' => $syear,
            'sub_institute_id' => $sub_institute_id,
            'title' => $request->title,
            'updated_by' => $user_id,
            'updated_at' => now(),
       ];
        if ($request->hasFile('video_path')) {
            $file = $request->file('video_path');
            $ext = $file->getClientOriginalExtension();
            $newfilename = 'interactiveVideo_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $ext;

            try {
                // Try to store on DigitalOcean
                $stored = Storage::disk('digitalocean')->putFileAs('public/h5p_content/', $file, $newfilename, 'public');
                if ($stored) {
                    $video_path = Storage::disk('digitalocean')->url('public/h5p_content/' . $newfilename);
                } else {
                    throw new \Exception('DigitalOcean upload failed');
                }
            } catch (\Exception $e) {
                // Fallback to local storage
                $destinationPath = public_path('h5p/video');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                $file->move($destinationPath, $newfilename);
                $video_path = asset('/h5p_content/' . $newfilename);
            }
            $updateData['video_path'] = $video_path;
        }

        // Update video record
        $video->update($updateData);

        // Delete old interactions
        $delete = H5pVideoInteraction::where('video_id', $id)->delete();
        // return $request;
        // Save new interactions
        if ($request->interactions) {
            foreach ($request->interactions as $interaction) {
                // Prepare data based on interaction type
                $options = null;
                $correct_answer = null;

                if ($interaction['interaction_type'] == 'multiple_choice') {
                    // Build options array dynamically: {1:"Option 1",2:"Option 2",...}
                    $options = [];
                    if (isset($interaction['options']) && is_array($interaction['options'])) {
                        foreach ($interaction['options'] as $key => $value) {
                            $options[$key + 1] = $value;
                        }
                    }
                    $correct_answer = $interaction['correct_answer'] ?? null;
                } elseif ($interaction['interaction_type'] == 'true_false') {
                    // Build options array: {1:"true",2:"false"}
                    $options = [1 => "true", 2 => "false"];
                    $correct_answer = $interaction['correct_answer'] ?? null;
                } else {
                    // Text input: optional correct answer
                    $correct_answer = $interaction['correct_answer'] ?? null;
                }

                H5pVideoInteraction::create([
                    'video_id' => $video->id,
                    'time' => $interaction['time'],
                    'interaction_type' => $interaction['interaction_type'],
                    'question' => $interaction['question'],
                    'options' => $options ? json_encode($options) : null,
                    'correct_answer' => $correct_answer,
                    'updated_by' => $user_id,
                    'updated_at' => now(),
                ]);
            }
        }

        $res['status'] = $video ? 1 : 0;
        $res['message'] = $video ? 'Video updated successfully' : 'Video update failed';

        if ($type == 'API') {
            return response()->json($res);
        }

        return redirect()->route('h5p_interactive_video.index', [
            'chapter_id' => $request->chapter_id,
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id
        ])->with('data', $res);
    }

    public function show(Request $request, $id)
    {
        $type = $request->type;
        $res['videos'] = H5pInteractiveVideo::with('interactions')->findOrFail($id);
        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;
        // return $res['videos'];
        return is_mobile($type, 'lms/h5p/interactiveVideo/show', $res, 'view');
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $syear = session()->get('syear');
        $uid = $request->uid ?? null; // Get uid from request

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_id = $request->user_id;
            $uid = $request->uid; // Get uid for API/JSON requests
        }

        $video = H5pInteractiveVideo::findOrFail($id);

        // Optional: Check if user has permission to delete (if uid is provided)
        if ($uid && $video->created_by != $uid && $video->updated_by != $uid) {
            $res['status'] = 0;
            $res['message'] = 'You do not have permission to delete this video';
            if ($type == 'API') {
                return response()->json($res);
            }
            return redirect()->back()->with('error', 'You do not have permission to delete this video');
        }

        // Delete associated interactions first (due to foreign key constraint)
        H5pVideoInteraction::where('video_id', $id)->delete();

        // Delete the video record
        $deleted = $video->delete();

        $res['status'] = $deleted ? 1 : 0;
        $res['message'] = $deleted ? 'Video deleted successfully' : 'Video deletion failed';

        if ($type == 'API') {
            return response()->json($res);
        }

        // Redirect back with parameters if available
        $params = [];
        if ($request->chapter_id) $params['chapter_id'] = $request->chapter_id;
        if ($request->standard_id) $params['standard_id'] = $request->standard_id;
        if ($request->subject_id) $params['subject_id'] = $request->subject_id;

        if (!empty($params)) {
            return redirect()->route('h5p_interactive_video.index', $params)->with('data', $res);
        }

        return redirect()->route('h5p_interactive_video.index')->with('data', $res);
    }
}
