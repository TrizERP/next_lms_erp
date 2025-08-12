<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\admission\admissionEnquiryModel;
use App\Models\admission\admissionFormModel;
use App\Models\admission\admissionRegistrationModel;
use App\Models\settings\tblcustomfieldsModel;
use Illuminate\Support\Facades\Schema;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;
use DB;

class admissionMasterController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = session()->get("syear");

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        // get enquiry table fields
        $enquiryCustoms = $this->getAddCustoms('admission_enquiry',$sub_institute_id);
        $enquiryExclude = ['id', 'sub_institute_id', 'enquiry_no','fees_circular_html','syear','first_name','middle_name','last_name','gender','mobile','email','address','date_of_birth','age','admission_standard','created_by','created_on'];
        foreach ($enquiryCustoms as $key => $value) {
            if(!in_array($value,$enquiryExclude)){
                $enquiryExclude[] = $value['field_name'];
            }
        }
        $enquryFields = $this->getFields('enquiry',$enquiryExclude);

        // get registration table fields
        $registrationCustom = $this->getAddCustoms('admission_form',$sub_institute_id);
        $registrationExclude = ['id', 'sub_institute_id', 'enquiry_no','status','created_by','created_on','enquiry_id'];
        foreach ($registrationCustom as $key => $value) {
            if(!in_array($value,$enquiryExclude)){
                $registrationExclude[] = $value['field_name'];
            }
        }
        $RegistrationFields = $this->getFields('registration',$registrationExclude);

        // get confirmation table fields
        $confirmationCustom = $this->getAddCustoms('admission_registration',$sub_institute_id);
        $confirmationExculde = ['id', 'sub_institute_id', 'enquiry_no','admission_status','student_quota','admission_date','admission_division','created_by','created_on','enquiry_id'];
        foreach ($confirmationCustom as $key => $value) {
            if(!in_array($value,$enquiryExclude)){
                $confirmationExculde[] = $value['field_name'];
            }
        }
        $confirmationFields = $this->getFields('confirmation',$confirmationExculde);

        $getAllFields = $this->getAddCustoms('all_fields',$sub_institute_id);

        $res['enquryFields'] = $enquryFields;
        $res['RegistrationFields'] = $RegistrationFields;
        $res['confirmationFields'] = $confirmationFields;

        $res['enquiryCustoms'] = $enquiryCustoms;
        $res['registrationCustom'] = $registrationCustom;
        $res['confirmationCustom'] = $confirmationCustom;

        $res['allAddedFields'] = $getAllFields;

        $res['standard'] = DB::table('standard')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
        $res['ageValidation'] = DB::table('admission_age_validation as aa')
        ->join('standard as sd','sd.id','=','aa.standard_id')
        ->selectRaw('aa.*,sd.name')
        ->where(['aa.sub_institute_id'=>$sub_institute_id,'aa.syear'=>$syear])->get()->toArray();
        // echo "<pre>";print_r($res['allAddedFields']);exit;

        return is_mobile($type, 'admission/master/add', $res, 'view');
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = session()->get("syear");

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        // echo "<pre>";print_r($request->all());exit;
        $table_name = $table_alias = '';
        if($request->table_name=='enquiry'){
            $table_name = 'admission_enquiry';
            $table_alias = 'ae';
        }
        else if($request->table_name=='registration'){
            $table_name = 'admission_form';
            $table_alias = 'af';
        }
        else if($request->table_name=='confirmation'){
            $table_name = 'admission_registration';
            $table_alias = 'ar';
        }
        if($request->table_name!="changeFields")
        {
            $fieldData = $request->get($request->table_name.'Fields');
            $i=0;
            $dropdown = ['category','send_sms','sms_message','previous_division','previous_standard','religion','institute_branch','admission_standard','blood_group','payment_mode','cast'];
            $dates = ['followup_date','activity_date','cheque_date','admission_date','date_of_payment'];
            $times = ['activity_time'];
            if($table_name!='' && $table_alias!='' && !empty($fieldData)){
                
                // $findData = tblcustomfieldsModel::whereRaw('table_name="'.$table_name.'" AND sub_institute_id="'.$sub_institute_id.'"')->first(); 

                // if(!empty($findData)){
                //     $delete = tblcustomfieldsModel::where('table_name',$table_name)->delete();
                // }
                $foundEntries = [];
                foreach ($fieldData as $key => $value) {
                    $field_name = str_replace(' ','_',lcfirst($value));
                    $findData = tblcustomfieldsModel::whereRaw('table_name="'.$table_name.'" AND sub_institute_id="'.$sub_institute_id.'" and field_name="'.$field_name.'"')->first(); 
                    if(!empty($findData)){
                        $foundEntries[] = $findData->field_name;
                    }
                }
            $deleteEntries =tblcustomfieldsModel::whereRaw('table_name="'.$table_name.'" AND sub_institute_id="'.$sub_institute_id.'"')
                ->whereNotIn('field_name',$foundEntries)->delete();
            // echo "<pre>";print_r($foundEntries);exit;
            $foundFileds = [];
            foreach ($fieldData as $key => $value) {
                $field_name = str_replace(' ','_',lcfirst($value));
                if(!in_array($field_name,$foundEntries)){
                    $field_type = "textbox";
                    if(in_array($field_name,$dropdown)){
                        $field_type = "dropdown";
                    }
                    if(in_array($field_name,$dates)){
                        $field_type = "date";
                    }
                    if(in_array($field_name,$times)){
                        $field_type = "time";
                    }
                        $fields = [
                            'table_name'       => $table_name,
                            'table_alias'      => $table_alias,
                            'tab_sort_order'   => 1,
                            'field_name'       => $field_name,
                            'column_header'    => '',
                            'field_label'      => $value,
                            'user_type'        => '',
                            'field_type'       => $field_type,
                            'field_message'    => '',
                            'status'           => "1",
                            'sort_order'       => ($key+1),
                            'file_size_max'    => '',
                            'sub_institute_id' => $sub_institute_id,
                            'required'         => 0,
                            'is_deleted'       => 'N',
                            'common_to_all'    => 0,
                        ];

                        $insert = tblcustomfieldsModel::insert($fields);

                            if($insert){
                                $i++;
                            }
                    }else{
                        $i++;
                    }
                }

                if($i>0){
                    $res['status_code'] = 1;
                    $res['message'] = "Field Added Successfully!";
                }else{
                    $res['status_code'] = 0;
                    $res['message'] = "Failed to Insert Fields!";
                }
            }else{
                $res['status_code'] = 0;
                $res['message'] = "Failed to find Table";
            }
        }
        else{
            // echo "<pre>";print_r($request->all());exit;
            $j = 0;
            foreach ($request->fieldsId as $key => $value) {
                $field_name = isset($request->fieldsLabels[$key]) ? $request->fieldsLabels[$key] : '';
                $required = isset($request->is_required[$value]) ? $request->is_required[$value] : '';

                $fieldData = [];
                if($field_name!='' && $field_name!=''){
                    $fieldData = ['field_label'=>$field_name];
                }

                $fieldData['required']=0;
                if($required!='' && $required=="on"){
                    $fieldData['required']=1;
                }

                $update = tblcustomfieldsModel::where('id',$value)->update($fieldData);
                $j++;
            }
            if($j>0){
                $res['status_code'] = 1;
                $res['message'] = "Field Updated Successfully!";
            }else{
                $res['status_code'] = 0;
                $res['message'] = "Failed to Update Fields!";
            }
        }
        
        // echo "<pre>";print_r($res);exit;
       
        return is_mobile($type, 'admission_master.index', $res);
    }

    public function getFields($modelName,$exludeColumns){
        if($modelName=='enquiry'){
            $model = new admissionEnquiryModel;
        }
        if($modelName=='registration'){
            $model = new admissionFormModel;
        }
        if($modelName=='confirmation'){
            $model = new admissionRegistrationModel;
        }
        $table_name = $model->getTable();
        $columnsLists = Schema::getColumnListing($table_name);
        // Filter out the unwanted columns
        $filteredColumns = array_filter($columnsLists, function ($column) use ($exludeColumns) {
            return !in_array($column, $exludeColumns);
        });
        $allFields = array_values($filteredColumns);

        return $allFields;
    }

    public function getAddCustoms($tableName,$sub_institute_id){
        if($tableName=="all_fields"){           
            $data = tblcustomfieldsModel::whereIn('table_name',["admission_enquiry","admission_form","admission_registration"])->where('sub_institute_id',$sub_institute_id)->whereRaw('user_type="" AND common_to_all=0 AND status=1')->orderBy('table_name')->get()->toArray();
        }else{
            $data = tblcustomfieldsModel::where('table_name',$tableName)->where('sub_institute_id',$sub_institute_id)->whereRaw('user_type="" AND common_to_all=0 AND status=1')->get()->toArray();
        }

        return $data;
    }

    
    // age validation added by uma for hills and other on 12-8-2025 by uma
    public function admissionAgeValidation(Request $request){
        // return $request; 
        $type = $request->type;
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = session()->get("syear");
        $user_id = session()->get("user_id");
        $formType = $request->formType;
        $standard_id = $request->standard_id;
        $date = $request->date;

        if($type=="API"){
           
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear'); 
            $user_id = $request->get('user_id');            
        }

        $i=0;
        if($request->has('formType') && $formType=="add"){
            $checkExists = DB::table('admission_age_validation')->where(['sub_institute_id'=>$sub_institute_id,'standard_id'=>$standard_id,'syear'=>$syear])->first();

            if(empty($checkExists) && !isset($checkExists->id)){
                $i = DB::table('admission_age_validation')->insert([
                    'sub_institute_id'=>$sub_institute_id,
                    'standard_id'=>$standard_id,
                    'date'=>$date,
                    'syear'=>$syear,
                    'created_by'=>$user_id,
                    'created_at'=>now(),
                ]);
            }
        }
        else if($request->has('formType') && $formType=="edit" && $request->has('id')){

             $i = DB::table('admission_age_validation')->where('id',$request->id)->update([
                    'standard_id'=>$standard_id,
                    'date'=>$date,
                    'updated_by'=>$user_id,
                    'updated_at'=>now(),
                ]);
        }
        else if($formType=="delete"){
            $i = DB::table('admission_age_validation')->where('id',$request->id)->delete();
        }
        
        if($i!=0){
            $res['status_code']=1;
            $res['message']='Data Added Successfully';
        }
        else{
            $res['status_code']=0;
            $res['message']='Failed to Add Data, may be alreay exists';
        }

        return is_mobile($type, 'admission_master.index', $res);
    }

}
