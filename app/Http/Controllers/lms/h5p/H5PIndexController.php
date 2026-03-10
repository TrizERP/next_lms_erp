<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

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
        if(in_array($type,['API','JSON'])){
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
                'title' => 'Quiz',
                'description' => 'AI Quiz for student',
                'icon' => 'mdi mdi-help-circle-outline',
                'route' => 'scenario_based.index',
            ],
            [
                'id' => 3,
                'title' => 'Video',
                'description' => 'AI Quiz for student',
                'icon' => 'mdi mdi-help-circle-outline',
                'route' => 'scenario_based.index',
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
}
