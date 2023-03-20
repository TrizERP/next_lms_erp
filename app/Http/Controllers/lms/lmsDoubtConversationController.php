<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\doubtConversationModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;


class lmsDoubtConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index(Request $request)
    {
    }

    public function getData($request)
    {
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
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_id = $request->session()->get('user_id');
        $syear = $request->session()->get('syear');

        $content = [
            'doubt_id'         => $request->get('doubt_id'),
            'message'          => $request->get('message'),
            'user_id'          => $user_id,
            'user_profile_id'  => $user_profile_id,
            'syear'            => $syear,
            'sub_institute_id' => $sub_institute_id,
        ];

        doubtConversationModel::insert($content);

        $res = [
            "status_code" => 1,
            "message"     => "Doubts Added Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "lmsSocialCollabrotive.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show(Request $request, $id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit(Request $request, $id)
    {
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy(Request $request, $id)
    {
    }

}
