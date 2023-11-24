<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\ONetDataCategory;
use App\Models\ONetDataOccupation;
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
use function App\Helpers\is_mobile;

class ONetOnlineDataController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['data'] = ONetDataCategory::all();
        return is_mobile($type, 'lms/o-net-data/index', $res, "view");
    }

    public function showCategoryWiseData(Request $request)
    {
        if ($request->id) {
            $type = $request->input('type');
            $res['data'] = ONetDataOccupation::where('o_net_data_category_id', $request->id)->get();
            $res['category'] = $request['category-name'];
            return is_mobile($type, 'lms/o-net-data/show_occupation', $res, "view");
        }
    }

    public function showCategoryWiseOccupationData(Request $request) {
        if ($request->id) {
            $type = $request->input('type');
            $res['data'] = ONetOccupationDetail::where('o_net_data_occupation_id', $request->id)->get();
            $res['category'] = $request['category-name'];
            return is_mobile($type, 'lms/o-net-data/show_occupation_detail', $res, "view");
        }
    }

    public function showCategoryWiseOccupationDataList(Request $request) {
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
                    $summary = ONetOccupationDetailTechSkillSummery::select('name','example')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $summary = collect($summary)->map(function ($res) {
                        $res['example'] = json_decode($res['example'],true);
                        return $res;
                    });
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Knowledge') {
                    $summary = ONetOccupationDetailKnowledgeSummery::select('name','description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Skills') {
                    $summary = ONetOccupationDetailSkillSummery::select('name','description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Abilities') {
                    $summary = ONetOccupationDetailAbilitiesSummery::select('name','description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Work Activities') {
                    $summary = ONetOccupationDetailWorkActivitySummery::select('name','description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }
                if ($res->resource_title == 'Work Styles') {
                    $summary = ONetOccupationDetailWorkStyleSummery::select('name','description')
                        ->where('o_net_occupation_detail_list_id', $res->id)
                        ->get();// Use pluck() to get an array of 'name' values directly
                    $res['summary'] = $summary;
                }

                if ($res->resource_title == 'Work Values') {
                    $summary = ONetOccupationDetailWorkValueSummery::select('name','description')
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

    public function showCategoryWiseOccupationDataListSummary(Request $request) {
        if ($request->id && $request['resource-title']) {
            $type = $request->input('type');
            $res['data'] = ONetOccupationDetailListSummary::where('o_net_occupation_detail_list_id', $request->id)->get();
            $res['category'] = $request['category-name'];
            return is_mobile($type, 'lms/o-net-data/show_occupation_detail_list_summary', $res, "view");
        }
    }
}
