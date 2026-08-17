<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use App\Services\PAL\H5P\H5PIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function App\Helpers\is_mobile;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class H5PIndexController extends Controller
{
    /**
     * H5P content hub.
     *
     * The card list used to be a literal array of four modules in this method.
     * It is now projected from the PAL V4 H5P Model registry
     * (`pal_vocabulary.h5p_types`): every type whose `implementation.status` is
     * `native` becomes a card, carrying its route, icon, copy and sort order,
     * and — when a chapter is in scope — the real number of nodes and child
     * parts that chapter holds, the pedagogies the type serves, and its
     * measured engagement.
     *
     * Registering a fifth H5P type is therefore a registry row, not an edit
     * here. `contentLists` keeps its original key and shape so the existing
     * Blade view and the Next.js hub keep working unchanged.
     */
    public function index(Request $request, H5PIntelligenceService $model)
    {
        $type = $request->input('type');

        // Historic quirk: the request field is misspelled `sub_institutue_id`
        // in this module. Both spellings are accepted so existing callers keep
        // working, with the session as the fallback for the Blade path.
        $subInstituteId = in_array($type, ['API', 'JSON'], true)
            ? ($request->input('sub_institute_id') ?? $request->input('sub_institutue_id'))
            : (session()->get('sub_institute_id') ?? session()->get('sub_institutue_id'));

        $context = [
            'chapter_id' => $request->filled('chapter_id') ? (int) $request->input('chapter_id') : null,
            'subject_id' => $request->filled('subject_id') ? (int) $request->input('subject_id') : null,
            'standard_id' => $request->filled('standard_id') ? (int) $request->input('standard_id') : null,
            'sub_institute_id' => $subInstituteId !== null && $subInstituteId !== '' ? (int) $subInstituteId : null,
        ];

        try {
            $modules = $model->hubModules($context);
        } catch (\Throwable $e) {
            // The hub is a navigation surface — it must open even if the model
            // layer cannot answer. Fall back to the registry's card fields with
            // no counts rather than showing the teacher an error page.
            Log::warning('H5P hub model unavailable, serving registry defaults: ' . $e->getMessage());
            $modules = $this->fallbackModules();
        }

        $res['contentLists'] = array_map(fn (array $module) => [
            'id' => $module['id'],
            'title' => $module['title'],
            'description' => $module['description'],
            'icon' => $module['icon'],
            'route' => $module['route'],
            // PAL V4 additions. The Blade view ignores unknown keys; the SPA
            // hub uses them to render counts, pedagogy chips and engagement.
            'h5p_type' => $module['h5p_type'],
            'node_count' => $module['node_count'] ?? 0,
            'child_count' => $module['child_count'] ?? 0,
            'child_label' => $module['child_label'] ?? null,
            'available' => $module['available'] ?? true,
            'unavailable_reason' => $module['unavailable_reason'] ?? null,
            'pedagogies' => $module['pedagogies'] ?? ['primary' => [], 'secondary' => []],
            'bloom_range' => $module['bloom_range'] ?? [],
            'fluency_trackable' => $module['fluency_trackable'] ?? 'no',
            'xapi_events' => $module['xapi_events'] ?? [],
            'engagement' => $module['engagement'] ?? null,
        ], $modules);

        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;

        return is_mobile($type, 'lms/h5p/index', $res, "view");
    }

    /**
     * Cards straight from the registry with no counts — used only when the
     * model layer throws (e.g. the registry migration has not run on this
     * database yet).
     */
    private function fallbackModules(): array
    {
        $registry = app(\App\Services\PAL\H5P\H5PModelRegistry::class);
        $modules = [];
        $position = 0;

        foreach ($registry->nativeTypes() as $code => $type) {
            $implementation = $type['metadata']['implementation'] ?? [];
            $modules[] = [
                'id' => ++$position,
                'h5p_type' => $code,
                'title' => $implementation['module_title'] ?? $type['label'],
                'description' => $implementation['module_description'] ?? $type['description'],
                'icon' => $implementation['icon'] ?? 'mdi mdi-shape',
                'route' => $implementation['route'] ?? null,
                'available' => true,
            ];
        }

        return $modules;
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
