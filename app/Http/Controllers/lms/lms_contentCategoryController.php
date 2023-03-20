<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\lmsContentCategoryModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lms_contentCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['cc_data'];

        return is_mobile($type, 'lms/show_contentCategory', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['cc_data'] = lmsContentCategoryModel::select('*')
            ->get()->toArray();

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = [];

        return is_mobile($type, 'lms/add_contentCategory', $data, "view");
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

        //Check if Subject Already Exist or not
        $exist = $this->check_exist($request->get('category_name'), $sub_institute_id);
        if ($exist == 0) {
            $content = [
                'category_name'    => $request->get('category_name'),
                'status'           => '1',
                'sub_institute_id' => $sub_institute_id,
            ];

            lmsContentCategoryModel::insert($content);

            $res = [
                "status_code" => 1,
                "message"     => "Content Category Added Successfully",
            ];
        } else {
            $res = [
                "status_code" => 0,
                "message"     => "Content Category Already Exist",
            ];
        }

        $type = $request->input('type');

        return is_mobile($type, "lms_content_category.index", $res, "redirect");
    }

    public function check_exist($category_name, $sub_institute_id, $id = null)
    {
        $data = DB::table('lms_content_category')->selectRaw('count(*) as tot')
            ->where(function ($q) use ($sub_institute_id) {
                $q->where('sub_institute_id', $sub_institute_id)->orWhere('sub_institute_id', '=', '0');
            })->where('category_name', $category_name);
        if ($id != "") {
            $data = $data->where('id', '!=', $id);
        }
        $data = $data->get()->toArray();

        return $data[0]->tot;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Request $request, $id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['cc_data'] = lmsContentCategoryModel::find($id)->toArray();

        return is_mobile($type, "lms/add_contentCategory", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        //Check if Subject Already Exist or not
        $exist = $this->check_exist($request->get('category_name'), $sub_institute_id, $id);
        if ($exist == 0) {
            $content = [
                'category_name'    => $request->get('category_name'),
                'status'           => '1',
                'sub_institute_id' => $sub_institute_id,
            ];

            lmsContentCategoryModel::where(["id" => $id])->update($content);

            $res = [
                "status_code" => 1,
                "message"     => "Content Category Updated Successfully",
            ];
        } else {
            $res = [
                "status_code" => 0,
                "message"     => "Content Category Already Exist",
            ];
        }
        $type = $request->input('type');

        return is_mobile($type, "lms_content_category.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        lmsContentCategoryModel::where(["id" => $id])->delete();

        $res['status_code'] = "1";
        $res['message'] = "Content Category Deleted Successfully";

        return is_mobile($type, "lms_content_category.index", $res);
    }

}
