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

class ExcelDownloadController extends Controller implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;
    private $data;
    private $headers;
    private $exam_name;

    public function index(Request $request)
    {
        $type = $request->input('type');
        $res = '';
        return is_mobile($type, 'import/excel_download/show', $res, 'view');
    }

  // new
    public function create(Request $request)
    {
        // return $request;exit;
      // Previous code...
        $type = $request->input('type');
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $subject = $request->input('subject');
        $exams = $request->input('exam_id');

        $data = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                $join->on("se.student_id", "=", "s.id");
            })->join('academic_section as ac', function ($join) {
                $join->whereRaw('ac.id = se.grade_id AND ac.sub_institute_id = se.sub_institute_id');
            })->join('standard as st', function ($join) use ($marking_period_id) {
                $join->whereRaw('st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id');
            })->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id');
            })->selectRaw("s.enrollment_no,se.standard_id,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,
          e.title as exam_id")
          ->leftJoin('result_create_exam as e',function($join) use ($exams){
              $join->whereRaw('se.standard_id = e.standard_id AND se.sub_institute_id = e.sub_institute_id AND se.syear = e.syear')->where('e.id',$exams);
          })
            ->where(['s.sub_institute_id' => $sub_institute_id, 'se.syear' => $syear])
            ->where('se.grade_id', $grade)
            ->where('se.standard_id', $standard)
            ->where('se.section_id', $division)
            ->groupByRaw('s.id')->get()->toArray();

                $standard_name = DB::table('standard')->where(['id'=>$standard,'sub_institute_id'=>$sub_institute_id])->get();
        // $exam_name = DB::table('result_create_exam')->whereIn('id', $exams)->select('title as paper_name', 'points as total_marks')->get();
        $headers = [
            'student_id',
            'standard_id',
            'student_name',
            'exam_id',
            'points',            
        ];

    
        $this->data = $data;
        $this->headers = $headers;

      // Export the data to Excel
        return Excel::download($this, 'Result_Marks-'.$standard_name[0]->name .'.xlsx');

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
            $row->enrollment_no,
            $row->standard_id,
            $row->student_name,
            $row->exam_id,
        ];
    }


}