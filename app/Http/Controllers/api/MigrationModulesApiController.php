<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Stateless APIs replacing the requested Blade/session ERP modules. */
class MigrationModulesApiController extends Controller
{
    use GetsJwtToken;

    private function guard(Request $request)
    {
        try { if (! $this->jwtToken()->validate()) return response()->json(['status_code'=>2,'message'=>'Token Auth Failed','data'=>[]], 401); }
        catch (\Throwable $e) { return response()->json(['status_code'=>2,'message'=>'Token Auth Failed','data'=>[]], 401); }
        $v = Validator::make($request->all(), ['sub_institute_id'=>'required|integer','user_id'=>'required|integer','syear'=>'required']);
        if ($v->fails()) return response()->json(['status_code'=>0,'message'=>$v->errors()->first(),'errors'=>$v->errors(),'data'=>[]], 422);
        return null;
    }
    private function ok($data = [], string $message = 'Success') { return response()->json(['status_code'=>1,'message'=>$message,'data'=>$data]); }
    private function fail(string $message, int $status = 422) { return response()->json(['status_code'=>0,'message'=>$message,'data'=>[]], $status); }
    private function tenant(Request $r): int { return $r->integer('sub_institute_id'); }

    public function index(Request $request, string $module)
    {
        if ($r=$this->guard($request)) return $r; $tenant=$this->tenant($request);
        return match ($module) {
            'learning-outcomes' => $this->ok(['records'=>DB::table('learning_outcome_indicator')->orderByDesc('ID')->get()]),
            'indicator-mappings' => $this->ok(['records'=>DB::table('learning_outcome_question_master as q')->leftJoin('learning_outcome_indicator as i','i.ID','=','q.INDICATORE_ID')->select('q.*','i.INDICATOR')->where('q.SYEAR',$request->input('syear'))->orderByDesc('q.ID')->get()]),
            'biomatrix' => $this->ok(['records'=>DB::table('biomatrix')->where('sub_institute_id',$tenant)->get()]),
            'subject-electives' => $this->ok(['records'=>DB::table('subject_elective as e')->join('standard as s','s.id','=','e.standard_id')->join('division as d','d.id','=','e.division_id')->leftJoin('sub_std_map as m',function($j){$j->on('m.subject_id','=','e.subject_id')->on('m.standard_id','=','e.standard_id');})->select('e.*','s.name as standard_name','d.name as division_name','m.display_name as subject_name')->where('e.sub_institute_id',$tenant)->where('e.syear',$request->input('syear'))->get()]),
            'bazar-reports' => $this->bazarReport($request),
            'dynamic-reports' => $this->ok(['records'=>DB::table('report_module_data')->where(fn($q)=>$q->where('sub_institute_id',$tenant)->orWhere('privacy',1))->get(),'modules'=>DB::table('report_module')->orderBy('id')->get()]),
            'broken-links' => $this->brokenLinks(),
            'students-marks' => $this->studentMarks($request),
            // No nomenclature master exists in this legacy database. Return a
            // clear empty state rather than querying a guessed table name.
            'nomenclature' => $this->ok(['records'=>[], 'provisioned'=>false, 'notice'=>'Nomenclature master is not configured for this ERP database.']),
            default => $this->fail('Unknown migration module.',404),
        };
    }

    public function store(Request $request, string $module)
    {
        if ($r=$this->guard($request)) return $r; $tenant=$this->tenant($request);
        if ($module==='learning-outcomes') { $v=Validator::make($request->all(),['medium'=>'required|max:50','standard'=>'required|max:50','subject'=>'required|max:50','indicator'=>'required']); if($v->fails()) return $this->fail($v->errors()->first()); DB::table('learning_outcome_indicator')->insert(['MEDIUM'=>$request->medium,'STANDARD'=>$request->standard,'SUBJECT'=>$request->subject,'INDICATOR'=>$request->indicator,'CREATED_BY'=>$request->user_id,'CREATED_AT'=>now(),'UPDATED_AT'=>now()]); return $this->ok([], 'Learning outcome saved.'); }
        if ($module==='indicator-mappings') { $v=Validator::make($request->all(),['date'=>'required|date','medium'=>'required','standard'=>'required','subject'=>'required','questions'=>'required|array|min:1','questions.*.title'=>'required','questions.*.indicator_id'=>'required|integer','questions.*.out_of'=>'required|numeric']); if($v->fails()) return $this->fail($v->errors()->first()); $rows=[]; foreach($request->questions as $q) $rows[]=['DATE'=>$request->date,'MEDIUM'=>$request->medium,'STANDARD'=>$request->standard,'SUBJECT'=>$request->subject,'QUESTION_TITLE'=>$q['title'],'QUESTION_OUT_OF'=>$q['out_of'],'INDICATORE_ID'=>$q['indicator_id'],'EXAM_CODE'=>$request->input('exam_code'),'EXAM_TYPE'=>$request->input('exam_type'),'SYEAR'=>$request->syear]; DB::table('learning_outcome_question_master')->insert($rows); return $this->ok([], 'Indicator mapping saved.'); }
        if ($module==='biomatrix') { $v=Validator::make($request->all(),['biomatrix_id'=>'required|string|max:65535']); if($v->fails()) return $this->fail($v->errors()->first()); DB::table('biomatrix')->insert(['biomatrix_id'=>$request->biomatrix_id,'sub_institute_id'=>$tenant,'created_at'=>now(),'updated_at'=>now()]); return $this->ok([], 'Biomatrix device saved.'); }
        if ($module==='subject-electives') { $v=Validator::make($request->all(),['standard_id'=>'required|integer','division_id'=>'required|integer','subject_id'=>'nullable|integer']); if($v->fails()) return $this->fail($v->errors()->first()); DB::table('subject_elective')->updateOrInsert(['sub_institute_id'=>$tenant,'syear'=>$request->syear,'standard_id'=>$request->standard_id,'division_id'=>$request->division_id],['subject_id'=>$request->input('subject_id',0),'elective_sub_no'=>$request->input('elective_sub_no','ELE1'),'updated_at'=>now(),'created_at'=>now()]); return $this->ok([], 'Standard-division elective mapping saved.'); }
        if ($module==='bazar-upload') return $this->bazarUpload($request);
        if ($module==='dynamic-reports') { $v=Validator::make($request->all(),['report_name'=>'required|max:255','definition'=>'required|array']); if($v->fails()) return $this->fail($v->errors()->first()); DB::table('report_module_data')->insert(['sub_institute_id'=>$tenant,'report_name'=>$request->report_name,'description'=>$request->input('description'),'privacy'=>$request->boolean('privacy'),'all_data'=>json_encode($request->definition,JSON_THROW_ON_ERROR)]); return $this->ok([], 'Report definition saved.'); }
        return $this->fail('This module is read-only or unknown.',405);
    }

    public function destroy(Request $request, string $module, int $id)
    {
        if ($r=$this->guard($request)) return $r; $tenant=$this->tenant($request);
        $table=['learning-outcomes'=>'learning_outcome_indicator','indicator-mappings'=>'learning_outcome_question_master','biomatrix'=>'biomatrix','subject-electives'=>'subject_elective','dynamic-reports'=>'report_module_data'][$module]??null;
        if(!$table) return $this->fail('This module does not support deletion.',405); $q=DB::table($table)->where($table==='learning_outcomes'||$table==='indicator-mappings'?'ID':'id',$id); if(in_array($module,['biomatrix','subject-electives','dynamic-reports','nomenclature'])) $q->where('sub_institute_id',$tenant); if(!$q->delete()) return $this->fail('Record not found.',404); return $this->ok([], 'Record deleted.');
    }

    private function bazarUpload(Request $r) { $v=Validator::make($r->all(),['kind'=>'required|in:position,margin,pnl','upload_date'=>'required|date','attachment'=>'required|file|mimes:xls,xlsx']); if($v->fails()) return $this->fail($v->errors()->first()); $map=['position'=>['sharebazar_position',['date','client_id','exchange','ScriptName','b_f_qty','b_f_rate','b_f_value','buy_qty','buy_rate','buy_amount','sale_qty','sale_rate','sale_amount','net_qty','net_rate','net_amount','closing_price','booked','notional','total']],'margin'=>['sharebazar_margin',['Code','exchange','script','qty','span','exposure','delivery_margin','additional_margin','ex_%','total']],'pnl'=>['sharebazar_pnl',['code','name','gross','exp','other_exp','gross_total','intrest','net_total']]]; [$table,$columns]=$map[$r->kind]; $data=IOFactory::load($r->file('attachment')->getRealPath())->getActiveSheet()->toArray(); array_shift($data); $rows=[]; foreach($data as $row){ if(!array_filter($row,fn($v)=>$v!==null&&$v!=='')) continue; $entry=array_combine($columns,array_pad(array_slice($row,0,count($columns)),count($columns),null)); $entry['upload_date']=$r->upload_date;$entry['created_at']=now();$rows[]=$entry; } DB::transaction(fn()=>collect($rows)->chunk(500)->each(fn($chunk)=>DB::table($table)->insert($chunk->all()))); return $this->ok(['imported'=>count($rows)], ucfirst($r->kind).' upload completed.'); }
    private function bazarReport(Request $r) { $map=['position'=>'sharebazar_position','margin'=>'sharebazar_margin','pnl'=>'sharebazar_pnl']; $table=$map[$r->input('kind','position')]??null; if(!$table) return $this->fail('Unknown Bazaar report.',422); $q=DB::table($table); if($r->filled('from_date'))$q->whereDate('upload_date','>=',$r->from_date);if($r->filled('to_date'))$q->whereDate('upload_date','<=',$r->to_date);return $this->ok(['records'=>$q->orderByDesc('upload_date')->paginate(min($r->integer('per_page',100),500))]); }
    private function studentMarks(Request $r) { $q=DB::table('result_marks as m')->join('tblstudent as s','s.id','=','m.student_id')->select('s.id as student_id','s.first_name','s.last_name','m.*')->where('s.sub_institute_id',$this->tenant($r)); if($r->filled('standard_id'))$q->where('m.standard_id',$r->standard_id); return $this->ok(['records'=>$q->paginate(min($r->integer('per_page',100),500))]); }
    private function brokenLinks() { $routes=collect(app('router')->getRoutes()->getRoutes())->map(fn($route)=>$route->getName())->filter(); $records=DB::table('tblmenumaster')->whereNotIn('link',['javascript:void(0);',null])->get(['id','name','link']); return $this->ok(['records'=>$records->map(fn($m)=>['id'=>$m->id,'name'=>$m->name,'link'=>$m->link,'status'=>$routes->contains($m->link)?'valid':'missing-route'])->values()]); }
}
