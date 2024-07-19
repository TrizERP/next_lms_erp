<?php

namespace App\Http\Controllers\lms\counselling;

use App\Http\Controllers\Controller;
use App\Models\lms\counselling\counsellingCourseModel;
use App\Models\lms\counselling\counsellingOnlineExamModel;
use App\Models\lms\counselling\OnetContentModelReference;
use App\Models\lms\counselling\OnetCareerCluster;
use App\Models\lms\counselling\OnetOccupationData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class lmsCounsellingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        /*echo("<pre>");
        print_r($data);
        echo("</pre>");
        die;*/
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['counselling_course'] = $data['courses'];
        $res['user_data'] = $data['final_user_data'];

        return is_mobile($type, 'lms/counselling/show_lmsCounselling', $res, "view");
    }

    public function getData($request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        $data['courses'] = counsellingCourseModel::select("counselling_course.*",
            DB::raw('count(q.`id`) as total_ques'))
            ->leftjoin('counselling_question_master as q', 'q.counselling_course_id', 'counselling_course.id')
            ->where(['counselling_course.sub_institute_id' => $sub_institute_id])
            ->groupby('counselling_course.id')
            ->orderby('counselling_course.sort_order')
            ->get()
            ->toArray();

        $data['final_user_data'] = [];
        $data['user_data'] = counsellingOnlineExamModel::select("counselling_online_exam.*",
            DB::raw('SUM(q.points) as total_points,count(q.id) as total_ques,DATE_FORMAT(created_at,"%Y-%m-%d") AS exam_date'))
            ->leftjoin('counselling_question_master as q', 'q.counselling_course_id',
                'counselling_online_exam.course_id')
            ->where([
                'counselling_online_exam.sub_institute_id' => $sub_institute_id,
                'counselling_online_exam.user_id' => $user_id,
            ])
            ->groupby('counselling_online_exam.id')
            ->get()
            ->toArray();

        foreach ($data['user_data'] as $key => $val) {
            $data['final_user_data'][$val['course_id']][] = $val;
        }

        return $data;
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create(Request $request)
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function edit(Request $request, $id)
    {

    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    public function update(Request $request, $id)
    {

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return void
     */
    public function destroy(Request $request, $id)
    {

    }

    public function lmsIndustryListing(Request $request)
    {
        $type = $request->input('type');

        try {
            $username = 'trizinnovation';
            $password = '4225aej';

            $credentials = base64_encode($username . ':' . $password);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/browse/');

            if ($response->successful()) {
                $data = $response->json();
                return view('lms/counselling/industry_listing', compact('data'));
                //return is_mobile($type, 'lms/counselling/demo_career_exam', ['data' => $data], "view");
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
        }
    }

    public function careersInIndustry(Request $request, $id)
    {
        $type = $request->input('type');
        $allCareers = [];

        try {
            $username = 'trizinnovation';
            $password = '4225aej';

            $credentials = base64_encode($username . ':' . $password);

            $nextPage = 'https://services.onetcenter.org/ws/mnm/browse/' . $id;

            while (!is_null($nextPage)) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . $credentials,
                    'Accept' => 'application/json',
                ])->get($nextPage);

                if ($response->successful()) {
                    $data = $response->json();

                    // Add the careers from this page to the array
                    $allCareers = array_merge($allCareers, $data['career']);

                    // Check if there's a "next" link in the response
                    $nextLink = collect($data['link'])->firstWhere('rel', 'next');
                    $nextPage = $nextLink ? $nextLink['href'] : null;
                } else {
                    $statusCode = $response->status();
                    $errorMessage = $response->body();
                    break; // Exit the loop in case of an error
                }
            }
            return view('lms/counselling/career_in_industry', compact('allCareers'));
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
        }
    }

    public function careerReport(Request $request, $id)
    {
        $type = $request->input('type');

        try {
            $username = 'trizinnovation';
            $password = '4225aej';

            $credentials = base64_encode($username . ':' . $password);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/careers/' . $id);

            if ($response->successful()) {
                $data = $response->json();

                return view('lms/counselling/career_report', compact('data', 'id'));
                //return is_mobile($type, 'lms/counselling/demo_career_exam', ['data' => $data], "view");
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
        }
    }

    public function resources(Request $request, $id, $title)
    {
        $type = $request->input('type');

        try {
            $username = 'trizinnovation';
            $password = '4225aej';

            $credentials = base64_encode($username . ':' . $password);

            $url = 'https://services.onetcenter.org/ws/mnm/careers/' . urlencode($id) . '/' . strtolower($title);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                $data = $response->json();
                //    dd($data);
                return view('lms/counselling/career_report_resource', compact('data', 'id', 'title'));
                //return is_mobile($type, 'lms/counselling/demo_career_exam', ['data' => $data], "view");
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
        }
    }
    public function careerExplore()
    {
        // Fetch data from the database
        $elements = OnetContentModelReference::whereNotNull('level')
            ->orderBy('element_id')
            ->get();

        // Function to build the nested structure
        function buildTree($elements, $parent_id = '', $level = 1)
        {
            $branch = [];
            foreach ($elements as $element) {
                if (substr($element->element_id, 0, strlen($parent_id)) === $parent_id && $element->level == $level) {
                    $children = buildTree($elements, $element->element_id . '.', $level + 1);
                    $elementData = [
                        'level' => $element->level,
                        'element_id' => $element->element_id,
                        'element_name' => $element->element_name,
                    ];
                    if (!empty($children)) {
                        $elementData['children'] = $children;
                    }
                    $branch[] = $elementData;
                }
            }
            return $branch;
        }

        // Build the tree structure starting with the top-level elements (level 1)
        $result = buildTree($elements);

        // Return the final JSON response
        return response()->json($result);
    }
    public function careerExploreResult(Request $request)
    {
        // Get query parameters
        $abilities = $request->input('abilities');
        $interests = $request->input('interests');
        $knowledge = $request->input('knowledge');
        $skills = $request->input('skills');
        $work_styles = $request->input('work_styles');
        $work_values = $request->input('work_values');
        $job_zones = $request->input('job_zones');

        // Build the initial query
        $query = DB::table('onet_occupation_data as od')
            ->select('od.onetsoc_code', 'od.title', 'od.description');

        // Add conditional joins
        if ($abilities) {
            $abilitiesArray = explode(',', $abilities);
            $query->join('onet_abilities as a', function ($join) use ($abilitiesArray) {
                $join->on('a.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('a.scale_id', '=', 'LV')
                    ->where(function ($query) use ($abilitiesArray) {
                        foreach ($abilitiesArray as $ability) {
                            $query->orWhere('a.element_id', 'LIKE', "$ability%");
                        }
                    });
            });
        }

        if ($interests) {
            $interestsArray = explode(',', $interests);
            $query->join('onet_interests as i', function ($join) use ($interestsArray) {
                $join->on('i.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('i.scale_id', '=', 'OI')
                    ->where(function ($query) use ($interestsArray) {
                        foreach ($interestsArray as $interest) {
                            $query->orWhere('i.element_id', 'LIKE', "$interest%");
                        }
                    });
            });
        }

        if ($knowledge) {
            $knowledgeArray = explode(',', $knowledge);
            $query->join('onet_knowledge as k', function ($join) use ($knowledgeArray) {
                $join->on('k.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('k.scale_id', '=', 'LV')
                    ->where(function ($query) use ($knowledgeArray) {
                        foreach ($knowledgeArray as $know) {
                            $query->orWhere('k.element_id', 'LIKE', "$know%");
                        }
                    });
            });
        }

        if ($skills) {
            $skillsArray = explode(',', $skills);
            $query->join('onet_skills as s', function ($join) use ($skillsArray) {
                $join->on('s.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('s.scale_id', '=', 'LV')
                    ->where(function ($query) use ($skillsArray) {
                        foreach ($skillsArray as $skill) {
                            $query->orWhere('s.element_id', 'LIKE', "$skill%");
                        }
                    });
            });
        }

        if ($work_styles) {
            $workStylesArray = explode(',', $work_styles);
            $query->join('onet_work_styles as ws', function ($join) use ($workStylesArray) {
                $join->on('ws.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('ws.scale_id', '=', 'IM')
                    ->where(function ($query) use ($workStylesArray) {
                        foreach ($workStylesArray as $workStyle) {
                            $query->orWhere('ws.element_id', 'LIKE', "$workStyle%");
                        }
                    });
            });
        }

        if ($work_values) {
            $workValuesArray = explode(',', $work_values);
            $query->join('onet_work_values as wv', function ($join) use ($workValuesArray) {
                $join->on('wv.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('wv.scale_id', '=', 'EX')
                    ->where(function ($query) use ($workValuesArray) {
                        foreach ($workValuesArray as $workValue) {
                            $query->orWhere('wv.element_id', 'LIKE', "$workValue%");
                        }
                    });
            });
        }

        if ($job_zones) {
            $jobZonesArray = explode(',', $job_zones);
            $query->join('onet_job_zones as jz', function ($join) use ($jobZonesArray) {
                $join->on('jz.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where(function ($query) use ($jobZonesArray) {
                        foreach ($jobZonesArray as $jobZone) {
                            $query->orWhere('jz.job_zone', 'LIKE', "$jobZone%");
                        }
                    });
            });
        }

        // Group by onetsoc_code and get the results
        $results = $query->groupBy('od.onetsoc_code')->get();

        // Return JSON response
        return response()->json($results);
    }
    public function careerCluster()
    {
        // Fetch data from the database
        $careers = OnetCareerCluster::all();

        // Group by career_cluster and career_pathway
        $groupedCareers = [];
        foreach ($careers as $career) {
            if (!isset($groupedCareers[$career->career_cluster])) {
                $groupedCareers[$career->career_cluster] = [
                    'career_id' => $career->career_id,
                    'career_cluster' => $career->career_cluster,
                    'image' => $career->image,
                    'children' => []
                ];
            }

            $pathwayExists = false;
            foreach ($groupedCareers[$career->career_cluster]['children'] as &$pathway) {
                if ($pathway['career_pathway'] === $career->career_pathway) {
                    $pathway['children'][] = [
                        'onetsoc_code' => $career->onetsoc_code,
                        'title' => $career->title,
                        'description' => $career->description
                    ];
                    $pathwayExists = true;
                    break;
                }
            }

            if (!$pathwayExists) {
                $groupedCareers[$career->career_cluster]['children'][] = [
                    'career_pathway' => $career->career_pathway,
                    'image' => $career->image,
                    'children' => [
                        [
                            
                            'onetsoc_code' => $career->onetsoc_code,
                            'title' => $career->title,
                            'description' => $career->description
                        ]
                    ]
                ];
            }
        }

        // Return the final JSON response
        return response()->json(array_values($groupedCareers));
    }
    public function allOccupation(Request $request)
    {
        // Retrieve the 'title' parameter from the request
        $title = $request->input('title', 'all'); // Default to 'all' if the parameter is not provided

        // Start the query
        $query = OnetOccupationData::query();

        // If 'title' parameter is provided and not 'all', add a where clause
        if ($title !== 'all') {
            $query->where('title', 'LIKE', "%{$title}%");
        }

        // Order the results by 'title'
        $results = $query->orderBy('title')->get();

        // Return the JSON response
        return response()->json($results);
    }
}
