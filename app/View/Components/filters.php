<?php

namespace App\View\Components;

use App\Models\FeesCollect;
use App\Models\ReportDynamic;
use Illuminate\View\Component;

class Filters extends Component
{
    public $institutes;
    public $reportTypes;
    public $fields;
    public $selectedReportType;
    public $xFields;
    public $yFields;

    public function __construct($reportType = null)
    {
        // Fetch distinct institutes
        $this->institutes = FeesCollect::whereNotNull('sub_institute_id')
            ->where('sub_institute_id', '!=', '')
            ->select('sub_institute_id')
            ->distinct()
            ->pluck('sub_institute_id');

        // Fetch distinct report types
        $this->reportTypes = ReportDynamic::select('report_name')->distinct()->pluck('report_name');

        // Store selected report type
        $this->selectedReportType = $reportType;

        // Get fields for the selected report type
        $this->fields = $reportType ? $this->getFieldsByReportType($reportType) : [];

    }

    private function getFieldsByReportType($reportType)
    {
        $reportDynamics = ReportDynamic::where('report_name', $reportType)->get();
        
        $xFieldsArray = [];
        $yFieldsArray = [];

        foreach ($reportDynamics as $reportDynamic) {
            if (!empty($reportDynamic->x_fields)) {
                $decodedXFields = json_decode($reportDynamic->x_fields, true);
                if (isset($decodedXFields['fields']) && is_array($decodedXFields['fields'])) {
                    $xFieldsArray = array_merge($xFieldsArray, $decodedXFields['fields']);
                }
            }

            if (!empty($reportDynamic->y_fields)) {
                $decodedYFields = json_decode($reportDynamic->y_fields, true);
                if (isset($decodedYFields['fields']) && is_array($decodedYFields['fields'])) {
                    $yFieldsArray = array_merge($yFieldsArray, $decodedYFields['fields']);
                }
            }
        }

        // Store x_fields and y_fields as class properties
        $this->xFields = array_unique($xFieldsArray);
        $this->yFields = array_unique($yFieldsArray);
    }

    public function render()
    {
        return view('components.filters', [
            'institutes' => $this->institutes,
            'x_fields' => $this->xFields,
            'y_fields' => $this->yFields,
        ]);
    }
}
