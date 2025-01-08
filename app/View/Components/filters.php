<?php

namespace App\View\Components;

use App\Models\fees_new\fees\fees_collect\FeesCollect;
use App\Models\ReportDynamic;
use Illuminate\View\Component;

class Filters extends Component
{
    public $institutes;
    public $fields;

    public function __construct()
    {
        $this->institutes = FeesCollect::whereNotNull('sub_institute_id')  
    ->where('sub_institute_id', '!=', '') 
    ->select('sub_institute_id')
    ->distinct()
    ->pluck('sub_institute_id');

    $reportDynamics = ReportDynamic::where('report_name', 'NOTIFICATION')->get();

    $fieldsArray = [];
    foreach ($reportDynamics as $reportDynamic) {
        if (isset($reportDynamic->fields)) {
            $decodedFields = json_decode($reportDynamic->fields, true); 
            if (isset($decodedFields['fields'])) {
                $fieldsArray = array_merge($fieldsArray, $decodedFields['fields']);
            }
        }
    }
    
    $this->fields = array_unique($fieldsArray);
    }    

    public function render()
    {
        return view('components.filters');
    }
}
