<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\ONetDataCategory;
use App\Models\ONetDataOccupation;
use App\Models\ONetDataSubCategory;
use App\Models\ONetDataTable;
use App\Models\ONetOccupationDetail;
use App\Models\ONetOccupationDetailAbilitiesSummery;
use App\Models\ONetOccupationDetailEducationSummery;
use App\Models\ONetOccupationDetailInterestSummery;
use App\Models\ONetOccupationDetailJobZoneSummery;
use App\Models\ONetOccupationDetailKnowledgeSummery;
use App\Models\ONetOccupationDetailList;
use App\Models\ONetOccupationDetailListSummary;
use App\Models\ONetOccupationDetailSkillSummery;
use App\Models\ONetOccupationDetailTechSkillSummery;
use App\Models\ONetOccupationDetailWorkActivitySummery;
use App\Models\ONetOccupationDetailWorkStyleSummery;
use App\Models\ONetOccupationDetailWorkValueSummery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use function App\Helpers\is_mobile;

class ONetOnlineDataController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['data'] = ONetDataCategory::all();
        return is_mobile($type, 'lms/o-net-data/index', $res, "view");
    }

    public function career_counselling(Request $request)
    {
        $type = $request->input('type');
        return is_mobile($type, '/lms/counselling/career_counselling', null, "view");
    }

    public function career_counselling_education(Request $request)
    {
        $type = $request->input('type');
        return is_mobile($type, '/lms/counselling/career_counselling_education', null, "view");
    }

    public function career_counselling_ky(Request $request)
    {
        $type = $request->input('type');
        return is_mobile($type, '/lms/counselling/career_counselling_ky', null, "view");
    }
    public function career_report(Request $request)
    {
        $type = $request->input('type');
        return is_mobile($type, '/lms/counselling/career_report', null, "view");
    }

    public function showCategoryWiseData(Request $request)
    {

        if ($request['category-name'] == 'All Occupation') {
           return  $this->ONetDataTable($request);
        } else if ($request->id) {
            $type = $request->input('type');
            $ONetDataCategory = ONetDataCategory::with('sub_categories')->where('id', $request->id)->get();

            $res['data'] = $ONetDataCategory->map(function ($category){
                 $category['sub_categories']->map(function ($sub_category) use ($category){
                    $is_childs = collect($category['sub_categories'])->where('parent_id',$sub_category['id'])->count();
                    $is_sub_childs = collect($category['sub_categories'])->where('sub_parent_id',$sub_category['id'])->count();
                    $is_parent_sub_child = collect($category['sub_categories'])->where('child_id',$sub_category['id'])->count();
                    $sub_category['is_childs'] = $is_childs;
                    $sub_category['is_sub_childs'] = $is_sub_childs;
                    $sub_category['is_parent_sub_child'] = $is_parent_sub_child;
                    return $sub_category;
                });
                 return $category;
            });


            //$res['category'] = $request['category-name'];
            return is_mobile($type, 'lms/o-net-data/show_occupation', $res, "view");
        }
    }

    public function ONetDataTable(Request $request)
    {
        $type = $request->input('type');
        if ($request->id  && $request['category-name'] != '') {
            $res['data'] = ONetDataTable::where('o_net_sub_category_id', 0)->get();
            $res['sub_category_name'] = '';
            $res['category_name'] = "All Occupation";

        } else if ($request->id) {
            $sub_category_name = ONetDataSubCategory::where('id', $request->id)->first();
            $category_name = ONetDataCategory::where('id', $sub_category_name->o_net_data_category_id)->value('category');
            $res['data'] = ONetDataTable::where('o_net_sub_category_id', $request->id)->get();
            $res['sub_category_name'] = $sub_category_name->sub_category_name;
            $res['category_name'] = $category_name;
        }
        return is_mobile($type, 'lms/o-net-data/show_list_table', $res, "view");
    }

    public function ONetDataTableListDetails(Request $request)
    {
        if ($request->code) {
            $type = $request->input('type');
            $ONetOccupationDetailList = [
                ['title' => $request->occupation,'description' =>'','resource_title' => 'Tasks'],
                ['resource_title' => 'Technology Skills'],
                ['resource_title' => 'Knowledge'],
                ['resource_title' => 'Skills'],
                ['resource_title' => 'Abilities'],
                ['resource_title' => 'Work Activities'],
                ['resource_title' => 'Job Zone'],
                ['resource_title' => 'Education'],
                ['resource_title' => 'Interests'],
                ['resource_title' => 'Work Styles'],
                ['resource_title' => 'Work Values']
                ];


            $res['data'] = collect($ONetOccupationDetailList)->map(function ($res) use ($request) {
                if ($res['resource_title'] == 'Tasks') {
                    $summary = ONetOccupationDetailListSummary::select('name')
                        ->where('related', 'like','%'.$request->code.'%')
                        ->get()
                        ->pluck('name'); // Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res['resource_title'] == 'Technology Skills') {
                    $summary = ONetOccupationDetailTechSkillSummery::select('name', 'example')
                        ->where('related', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $summary = collect($summary)->map(function ($res) {
                        $res['example'] = json_decode($res['example'], true);
                        return $res;
                    });
                    $res['summary'] = $summary;
                }
                if ($res['resource_title'] == 'Knowledge') {
                    $summary = ONetOccupationDetailKnowledgeSummery::select('name', 'description')
                        ->where('related', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res['resource_title'] == 'Skills') {
                    $summary = ONetOccupationDetailSkillSummery::select('name', 'description')
                        ->where('related', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res['resource_title'] == 'Abilities') {
                    $summary = ONetOccupationDetailAbilitiesSummery::select('name', 'description')
                        ->where('related', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res['resource_title'] == 'Work Activities') {
                    $summary = ONetOccupationDetailWorkActivitySummery::select('name', 'description')
                        ->where('related', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res['resource_title'] == 'Work Styles') {
                    $summary = ONetOccupationDetailWorkStyleSummery::select('name', 'description')
                        ->where('related', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }

                if ($res['resource_title'] == 'Work Values') {
                    $summary = ONetOccupationDetailWorkValueSummery::select('name', 'description')
                        ->where('related', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res['resource_title'] == 'Job Zone') {
                    $summary = ONetOccupationDetailJobZoneSummery::
                        where('code', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res['resource_title'] == 'Education') {
                    $summary = ONetOccupationDetailEducationSummery::
                        where('code', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res['resource_title'] == 'Interests') {
                    $summary = ONetOccupationDetailInterestSummery::
                        where('related', 'like','%'.$request->code.'%')
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                return $res;
            });


            /*$ONetOccupationDetail = ONetOccupationDetail::where('code', $request->code)->first();
            if ($ONetOccupationDetail->id) {
                $ONetOccupationDetailList = ONetOccupationDetailList::where('o_net_occupation_detail_id', $ONetOccupationDetail->id)
                    ->get();
                $res['data'] = $ONetOccupationDetailList->map(function ($res) {
                    if ($res->resource_title == 'Tasks') {
                        $summary = ONetOccupationDetailListSummary::select('name')
                            ->where('o_net_occupation_detail_list_id', $res->id)
                            ->get()
                            ->pluck('name'); // Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }
                    if ($res->resource_title == 'Technology Skills') {
                        $summary = ONetOccupationDetailTechSkillSummery::select('name', 'example')
                            ->where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $summary = collect($summary)->map(function ($res) {
                            $res['example'] = json_decode($res['example'], true);
                            return $res;
                        });
                        $res['summary'] = $summary;
                    }
                    if ($res->resource_title == 'Knowledge') {
                        $summary = ONetOccupationDetailKnowledgeSummery::select('name', 'description')
                            ->where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }
                    if ($res->resource_title == 'Skills') {
                        $summary = ONetOccupationDetailSkillSummery::select('name', 'description')
                            ->where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }
                    if ($res->resource_title == 'Abilities') {
                        $summary = ONetOccupationDetailAbilitiesSummery::select('name', 'description')
                            ->where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }
                    if ($res->resource_title == 'Work Activities') {
                        $summary = ONetOccupationDetailWorkActivitySummery::select('name', 'description')
                            ->where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }
                    if ($res->resource_title == 'Work Styles') {
                        $summary = ONetOccupationDetailWorkStyleSummery::select('name', 'description')
                            ->where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }

                    if ($res->resource_title == 'Work Values') {
                        $summary = ONetOccupationDetailWorkValueSummery::select('name', 'description')
                            ->where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }
                    if ($res->resource_title == 'Job Zone') {
                        $summary = ONetOccupationDetailJobZoneSummery::
                        where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }
                    if ($res->resource_title == 'Education') {
                        $summary = ONetOccupationDetailEducationSummery::
                        where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }
                    if ($res->resource_title == 'Interests') {
                        $summary = ONetOccupationDetailInterestSummery::
                        where('o_net_occupation_detail_list_id', $res->id)
                            ->get();// Use pluck() to get an array of 'name' values directly
                        $res['summary'] = $summary;
                    }
                    return $res;
                });
            }*/
            $res['category'] = $request['category-name'];
            return is_mobile($type, 'lms/o-net-data/show_occupation_detail_list', $res, "view");
        }
    }

    public function showCategoryWiseOccupationData(Request $request)
    {
        if ($request->id) {
            $type = $request->input('type');
            $res['data'] = ONetOccupationDetail::where('o_net_data_occupation_id', $request->id)->get();
            $res['category'] = $request['category-name'];
            return is_mobile($type, 'lms/o-net-data/show_occupation_detail', $res, "view");
        }
    }

    public function showCategoryWiseOccupationDataList(Request $request)
    {
        if ($request->id) {
            $type = $request->input('type');
            $data = ONetOccupationDetailList::where('o_net_occupation_detail_id', $request->id)
                ->get();


            $res['data'] = $data->map(function ($res) {
                if ($res->resource_title == 'Tasks') {
                    $summary = ONetOccupationDetailListSummary::select('name')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get()
                        ->pluck('name'); // Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Technology Skills') {
                    $summary = ONetOccupationDetailTechSkillSummery::select('name', 'example')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $summary = collect($summary)->map(function ($res) {
                        $res['example'] = json_decode($res['example'], true);
                        return $res;
                    });
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Knowledge') {
                    $summary = ONetOccupationDetailKnowledgeSummery::select('name', 'description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Skills') {
                    $summary = ONetOccupationDetailSkillSummery::select('name', 'description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Abilities') {
                    $summary = ONetOccupationDetailAbilitiesSummery::select('name', 'description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Work Activities') {
                    $summary = ONetOccupationDetailWorkActivitySummery::select('name', 'description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Work Styles') {
                    $summary = ONetOccupationDetailWorkStyleSummery::select('name', 'description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }

                if ($res->resource_title == 'Work Values') {
                    $summary = ONetOccupationDetailWorkValueSummery::select('name', 'description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Job Zone') {
                    $summary = ONetOccupationDetailJobZoneSummery::
                    where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Education') {
                    $summary = ONetOccupationDetailEducationSummery::
                    where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Interests') {
                    $summary = ONetOccupationDetailInterestSummery::
                    where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                return $res;
            });


            $res['category'] = $request['category-name'];
            return is_mobile($type, 'lms/o-net-data/show_occupation_detail_list', $res, "view");
        }
    }

    public function showCategoryWiseOccupationDataListSummary(Request $request)
    {
        if ($request->id && $request['resource-title']) {
            $type = $request->input('type');
            $res['data'] = ONetOccupationDetailListSummary::where('o_net_occupation_detail_list_id', $request->id)->get();
            $res['category'] = $request['category-name'];
            return is_mobile($type, 'lms/o-net-data/show_occupation_detail_list_summary', $res, "view");
        }
    }
}
