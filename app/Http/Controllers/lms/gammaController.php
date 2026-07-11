<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\gammaModel;
use App\Services\GammaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class gammaController extends Controller
{
    protected $gammaService;

    public function __construct(GammaService $gammaService)
    {
        $this->gammaService = $gammaService;
    }

    public function index(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data['gamma_ppt'] = gammaModel::where([
            'gamma_ppt.sub_institute_id' => $sub_institute_id,
            'gamma_ppt.syear' => $syear,
        ])
        ->join('standard', 'standard.id', '=', 'gamma_ppt.standard_id')
        ->join('subject', 'subject.id', '=', 'gamma_ppt.subject_id')
        ->leftjoin('chapter_master', 'chapter_master.id', '=', 'gamma_ppt.chapter_id')
        ->select('gamma_ppt.*', 'standard.name as standard_name', 'subject.subject_name', 'chapter_master.chapter_name')
        ->orderBy('gamma_ppt.id', 'desc')
        ->get();

        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['gamma_ppt'];

        return view('lms/gamma_ppt/show_gamma_ppt', compact('data'));
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->input('type');
        $data = [];

        $data['standard_id'] = $request->get('standard_id');
        $data['subject_id'] = $request->get('subject_id');
        $data['chapter_id'] = $request->get('chapter_id');
        $data['topic_id'] = $request->get('topic_id');

        return is_mobile($type, 'lms/gamma_ppt/add_gamma_ppt', $data, "view");
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        $request->validate([
            'prompt' => 'required|string|max:400000',
            'format' => 'nullable|in:presentation,document,social',
            'export_format' => 'nullable|in:pdf,pptx',
        ]);

        try {
            $inputText = $request->prompt;
            $format = $request->format ?? 'presentation';
            $exportAs = $request->export_format ?? 'pdf';
            $numCards = $request->slide_count ?? 10;
            $themeId = env('GAMMA_THEME_ID');

            $generationResponse = $this->gammaService->generatePresentation([
                'inputText' => $inputText,
                'format' => $format,
                'exportAs' => $exportAs,
                'numCards' => $numCards,
                'themeId' => $themeId,
                'additionalInstructions' => $request->additional_instructions ?? '',
            ]);

            $generationId = $generationResponse['id'] ?? null;

            if (!$generationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No generation ID received from Gamma API'
                ], 500);
            }

            $pollResult = $this->gammaService->pollStatus($generationId);
            $status = $pollResult['status'];
            $responseData = $pollResult['data'];

            if ($status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => $status === 'failed' ? 'Generation failed' : 'Generation timeout',
                    'generation_id' => $generationId,
                    'status' => $status
                ], 408);
            }

            $extracted = $this->gammaService->extractResult($responseData);

            $gamma = new gammaModel();
            $gamma->standard_id = $request->get('standard_id');
            $gamma->subject_id = $request->get('subject_id');
            $gamma->chapter_id = $request->get('chapter_id');
            $gamma->topic_id = $request->get('topic_id');
            $gamma->title = $request->get('title', 'Gamma Generated Presentation');
            $gamma->description = $request->get('description');
            $gamma->prompt = $request->prompt;
            $gamma->format = $format;
            $gamma->export_format = $exportAs;
            $gamma->generation_id = $generationId;
            $gamma->gamma_url = $extracted['url'];
            $gamma->file_url = $extracted['pdf_url'] ?: $extracted['url'];
            $gamma->file_type = $exportAs;
            $gamma->status = $status;
            $gamma->credits_used = $extracted['credits_used'];
            $gamma->credits_remaining = $extracted['credits_remaining'];
            $gamma->sub_institute_id = $sub_institute_id;
            $gamma->syear = $syear;
            $gamma->created_by = $user_id;
            $gamma->created_at = date('Y-m-d H:i:s');
            $gamma->save();

            $res = [
                "status_code" => 1,
                "message" => "Gamma PPT Generated Successfully",
                "data" => [
                    'id' => $gamma->id,
                    'gamma_url' => $gamma->gamma_url,
                    'file_url' => $gamma->file_url,
                    'file_type' => $gamma->file_type,
                    'generation_id' => $generationId,
                ]
            ];

            return redirect()->route('lms_gamma_ppt.index', ['perm' => $sub_institute_id]);

        } catch (\Exception $e) {
            Log::error('Gamma PPT Generation Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['gamma_ppt'] = gammaModel::where([
            'id' => $id,
            'sub_institute_id' => $sub_institute_id,
        ])->first();

        if (!$data['gamma_ppt']) {
            return redirect()->route('lms_gamma_ppt.index');
        }

        return view('lms/gamma_ppt/show_gamma_ppt_detail', compact('data'));
    }

    public function destroy(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $gamma = gammaModel::where([
            'id' => $id,
            'sub_institute_id' => $sub_institute_id,
        ])->first();

        if ($gamma) {
            $gamma->delete();
        }

        $res['status_code'] = "1";
        $res['message'] = "Gamma PPT Deleted Successfully";

        return redirect()->route('lms_gamma_ppt.index');
    }
}
