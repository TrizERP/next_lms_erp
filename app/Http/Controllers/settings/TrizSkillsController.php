<?php

namespace App\Http\Controllers\settings;

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

class TrizSkillsController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['data'] = ONetDataCategory::all();
        return is_mobile($type, 'lms/o-net-data/index', $res, "view");
    }

    public function triz_skills(Request $request)
    {
        $type = $request->input('type');
        return is_mobile($type, '/lms/triz_skills', null, "view");
    }
}
