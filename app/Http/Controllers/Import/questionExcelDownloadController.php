<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation\Type;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation\Rule;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Support\Collection;


class questionExcelDownloadController extends Controller  implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;
    private $data;
    private $headers;
    //

    public function index(Request $request){
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $data = DB::table('lms_question_master as lqm')
        ->join('standard as s', 'lqm.standard_id', '=', 's.id')
        ->join('subject as sub', 'sub.id', '=', 'lqm.subject_id')
        ->join('answer_master as am', 'am.question_id', '=', 'lqm.id')
        ->selectRaw('
            s.name as Standard,
            sub.subject_name as Subject,
            lqm.question_title as Title,
            SUBSTRING_INDEX(GROUP_CONCAT(CONCAT_WS(" ", @row_number := @row_number + 1, am.answer)), ",", 1) as Options1,
            SUBSTRING_INDEX(SUBSTRING_INDEX(GROUP_CONCAT(CONCAT_WS(" ", @row_number := @row_number + 1, am.answer)), ",", 2), ",", -1) as Options2,
            CASE
            WHEN COUNT(am.id) >= 3 THEN SUBSTRING_INDEX(SUBSTRING_INDEX(GROUP_CONCAT(CONCAT_WS(" ", @row_number := @row_number + 1, am.answer)), ",", 3), ",", -1)
            ELSE NULL
        END as Options3,
        CASE
            WHEN COUNT(am.id) >= 4 THEN SUBSTRING_INDEX(SUBSTRING_INDEX(GROUP_CONCAT(CONCAT_WS(" ", @row_number := @row_number + 1, am.answer)), ",", 4), ",", -1)
            ELSE NULL
        END as Options4,
        MAX(CASE WHEN am.correct_answer = 1 THEN am.answer ELSE NULL END) as Answer
        ')
        ->where('lqm.question_type_id', 1)
        ->whereNotNull('lqm.question_title')
        ->where('lqm.status', 1)
        ->where('s.name', '!=', 'DEMO')
        ->where('lqm.sub_institute_id', session()->get('sub_institute_id'))
        ->groupBy('lqm.question_title')->get();
       $dataCollection = collect($data);
    
        // Format the Options column
        $formattedData = $dataCollection->map(function ($item) {
             if (strpos($item->Title, '<img') !== false) {
                preg_match('/src="([^"]+)"/', $item->Title, $matches);

                $item->Title .= " Image ".$matches[1];
                $item->Title = strip_tags(html_entity_decode($item->Title));
            } 
            else{
                $item->Title = strip_tags(html_entity_decode($item->Title));
            }
            $item->Answer = strip_tags(html_entity_decode($item->Answer));
            $item->Options1 = strip_tags(html_entity_decode($item->Options1));
            $item->Options2 = strip_tags(html_entity_decode($item->Options2));
            $item->Options3 = strip_tags(html_entity_decode($item->Options3));
            $item->Options4 = strip_tags(html_entity_decode($item->Options4));
        
            return $item;
        });

        $formattedDataArray = $formattedData->toArray();

        $headers = [
            'Standard',
            'Subject',
            'Title',
            'Answer',                        
            'Options1',
            'Options2',
            'Options3',            
            'Options4',
        ];

        $this->data = $formattedDataArray;
        $this->headers = $headers;
        return Excel::download($this, 'Question-Download.xlsx');        
    }
    
    public function registerEvents() : array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->applyDataValidation($event);
            },
        ];
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function headings() : array
    {
        return $this->headers;
    }

    public function map($row) : array
    {
        return [
            $row->Standard,
            $row->Subject,
            $row->Title,
            $row->Answer,            
            $row->Options1,
            $row->Options2,
            $row->Options3,
            $row->Options4,            
        ];
    }
    

}
