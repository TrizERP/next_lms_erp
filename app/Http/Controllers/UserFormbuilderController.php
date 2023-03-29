<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\FormTable;
use App\Models\FormSubmitData;
use App\Models\lms\chapterModel;
use function App\Helpers\is_mobile;

class UserFormbuilderController extends Controller
{
    public function index(): Factory|View|Application
    {
        $formBuils = FormTable::all();
        
        return view('formbuilder.formbuilderList', compact('formBuils'));
    }

    public function formbuilder(): Factory|View|Application
    {
        return view('formbuilder.formbuilder');
    }

    public function saveformbuilder(Request $request, $id = NULL)
    {
        try {
            if ($id != NULL) {
                $formBuils = FormTable::find($id);
            } else {
                $formBuils = new FormTable();
            }
            
            $formBuils->form_name = $request->formname ?: '';
            $formBuils->form_xml = $request->dataxml ?: '';
            $formBuils->form_json = $request->datajson ?: '';
            $formBuils->form_active = 0;
            $formBuils->save();
            
            return 'true';
            
        } catch (Exception $e) {
            return  $e->getMessage();
        }
    }

    public function editformbuilder(Request $request, $id): Factory|View|Application
    {
        $editformBuils = FormTable::find($id);
        
        return view('formbuilder.formbuilderEdit', compact('editformBuils'));
    }

    public function deleteformbuilder(Request $request, $id): RedirectResponse
    {
        $DelformBuils = FormTable::find($id);
        $DelformBuils->delete();
        
        return redirect()->route('formbuild.list');
    }

    public function getformbuilder($name)
    {
        return FormTable::where('form_name', $name)->first();
    }

    public function Apigetformbuilder($name = Null)
    {
        try {
            if ($name != NULL) {
                $formBuils = FormTable::where('form_name', '=', $name)->first();
            } else {
                $formBuils = FormTable::all();
            }
            
            return response(["status" => true, "data" => $formBuils], 200);
            
        } catch (Exception $e) {
            
            return response(["status" => false, "data" => ''], 400);
        }
    }

    public function formbuilder__()
    {
        return view('formbuilder.demo');
    }

    /**
     * Form Builder
     * View Form
     * @field $id
     */
    public function viewForm(Request $request)
    {
        if (!session()->get('user_id')) {
            return redirect()->route('dashboard');
        }

        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $id = $request->id;
        $chapter_id = $request->chapter_id;

        $getSubmitFormData = false;
        if ( $chapter_id ) {
            $getSubmitFormData = FormSubmitData::where('sub_institute_id', $sub_institute_id)
                ->where('form_id', $id)
                ->where('chapter', $chapter_id)
                ->get()
                ->first();
    
        }
        $submitFormData = new FormSubmitData();

        $result = FormTable::find($id);
        $html = '';

        if ($result) {
            $formData = json_decode($result->form_json);
            if (!empty($formData)) {
                $html .= "<div class='container'>
                    <form action='" . route('submit_form_data') . "' method='post'>
                        <input type='hidden' name='_token' value='" . csrf_token() . "'>
                        <input type='hidden' name='form_id' value='$id' />
                ";
                
                if ( $getSubmitFormData ) {
                    $html .= "<input type='hidden' name='form_submited_id' value='$getSubmitFormData->id' />";
                }

                if ( $chapter_id ) {
                    $html .= "<input type='hidden' name='chapter_id' value='$chapter_id' />";
                }

                foreach ($formData as $data) {

                    switch ($data->type) {
                        case 'header':
                            $heading_tag = $data->subtype ?? '';
                            $heading_label = $data->label ?? '';
                            $heading_class = $data->className ?? '';
                            $html .= "<$heading_tag class='$heading_class'>$heading_label</$heading_tag>";
                            break;

                        case 'date':
                            $date_class = $data->className ?? '';
                            $date_label = $data->label ?? '';
                            $date_required = (isset($data->required) && $data->required) ? 'required' : '';
                            $date_name = $data->name ?? '';
                            $date_value = $data->value ?? '';

                            $html .= "<div class='form-group date'>
                                <label for='input_from'>$date_label</label>
                                <input type='date' value='" . $this->setValue($getSubmitFormData, $date_name, $date_value) . "' name='$date_name' class='$date_class' $date_required>
                            </div>";
                            break;

                        case 'select':
                            $select_class = isset($data->className) ? $data->className : '';
                            $select_label = isset($data->label) ? $data->label : '';
                            $select_required = (isset($data->required) && $data->required) ? 'required' : '';
                            $select_original_name = isset($data->name) ? $data->name : '';
                            $select_placeholder = isset($data->placeholder) ? $data->placeholder : '';
                            $select_multiple = (isset($data->multiple) && $data->multiple) ? 'multiple' : '';

                            if ($select_multiple) {
                                $select_name = $select_original_name . '[]';
                            } else {
                                $select_name = $select_original_name;
                            }

                            $set_default_value = '';
                            
                            if ( $select_label == 'Subject' ) {
                                if ( $getSubmitFormData ) {
                                    $selected_value = (array) json_decode($getSubmitFormData->form_data);
                                    if ( isset($selected_value[$select_original_name]) ) {
                                        $set_default_value = "data-value={$selected_value[$select_original_name]}";
                                    }
                                }
                                $html .= "<input type='hidden' name='subject' value=''>";
                            } else if ( $select_label == 'Standard' ) {
                                if ( $getSubmitFormData ) {
                                    $selected_value = (array) json_decode($getSubmitFormData->form_data);
                                    if ( isset($selected_value[$select_original_name]) ) {
                                        $set_default_value = "data-value={$selected_value[$select_original_name]}";
                                    }
                                }
                                $html .= "<input type='hidden' name='standard' value=''>";
                            } else if ( $select_label == 'Chapters' ) {
                                if ( $getSubmitFormData ) {
                                    $selected_value = (array) json_decode($getSubmitFormData->form_data);
                                    if ( isset($selected_value[$select_original_name]) ) {
                                        $set_default_value = "data-value={$selected_value[$select_original_name]}";
                                    }
                                }
                                $html .= "<input type='hidden' name='chapter' value=''>";
                            }

                            $html .= "<div class='form-group'>
                                <label for='input_from'>$select_label</label>
                                <select class='$select_class $select_label' id='".strtolower($select_label)."' name='$select_name' $set_default_value $select_required $select_multiple>";

                            if ($select_placeholder) {
                                $html .= "<option value=''>$select_placeholder</option>";
                            }

                            if ( $select_label == 'Standard' ) {
                                $get_standards = DB::table('standard')
                                ->select('id', 'name')
                                ->where('sub_institute_id', $sub_institute_id)
                                ->get()
                                ->toArray();

                                if ( $get_standards ) {
                                    foreach ( $get_standards as $standard ) {
                                        $std_name = $standard->name;
                                        $std_id = $standard->id;

                                        if ( $getSubmitFormData ) {
                                            $get_selected_std = $this->setValue($getSubmitFormData, $select_original_name, $std_id);
                                            if ($select_multiple) {
                                                if (is_array($get_selected_std) && in_array($std_id, $get_selected_std)) {
                                                    $selected_std = 'selected';
                                                } else {
                                                    $selected_std = '';
                                                }
                                            } elseif (is_array($get_selected_std)) {
                                                if (in_array($std_id, $get_selected_std)) {
                                                    $selected_std = 'selected';
                                                } else {
                                                    $selected_std = '';
                                                }
                                            } else {
                                                $selected_std = '';
                                            }
                                        } else {
                                            $selected_std = '';
                                        }
                                        
                                        $html .= "<option value='$std_id' $selected_std >$std_name</option>";
                                    }
                                }
                            } 
                            if ( $select_label == 'Subject' ) {

                            } else if (isset($data->values) && !empty($data->values)) {
                                foreach ($data->values as $option) {
                                    
                                    $option_label = isset($option->label) ? $option->label : '';
                                    $option_value = isset($option->value) ? $option->value : '';
                                    $option_selected = (isset($option->selected) && $option->selected) ? 'selected' : '';
                                    // dd($option);
                                    if ($getSubmitFormData) {
                                        $option_value_selected = $this->setValue($getSubmitFormData, $select_original_name, $option_value);

                                        if ($select_multiple) {
                                            if (is_array($option_value_selected) && in_array($option_value, $option_value_selected)) {
                                                $option_selected = 'selected';
                                            } else {
                                                $option_selected = '';
                                            }
                                        } elseif (is_array($option_value_selected) && in_array($option_value, $option_value_selected)) {
                                            $option_selected = 'selected';
                                        } else {
                                            $option_selected = '';
                                        }
                                    }

                                    if ( $select_label == 'Standard' ) {
                                        $get_standard_id = DB::table('standard')
                                            ->select('id')
                                            ->where('name', $option->value)
                                            ->where('sub_institute_id', $sub_institute_id)
                                            ->first();
                                        
                                        if ( $get_standard_id ) {
                                            $standard_id = $get_standard_id->id;
                                        } else {
                                            $standard_id = $option_value;
                                        }
                                    } else {
                                        $standard_id = $option_value;
                                    }


                                    $html .= "<option value='$standard_id' $option_selected >$option_label</option>";
                                }
                            } else {
                                if (!$select_placeholder) {
                                    $html .= "<option disabled>No options available</option>";
                                }
                            }

                            $html .= "</select>
                            </div>";

                            break;

                        case 'text':
                            $text_required = (isset($data->required) && $data->required) ? 'required' : '';
                            $text_label = $data->label ?? '';
                            $text_placeholder = $data->placeholder ?? '';
                            $text_class = $data->className ?? '';
                            $text_name = $data->name ?? '';
                            $text_value = $data->value ?? '';
                            $text_subtype = $data->subtype ?? '';
                            $text_maxlength = $data->maxlength ?? '';

                            if ( $text_label == 'Chapters' ) {
                                $html .= "<input type='hidden' name='chapter' value=''>";
                            }

                            $html .= "<div class='form-group'>
                                <label>$text_label</label>
                                <input type='$text_subtype' class='$text_class' id='".strtolower($text_label)."' name='$text_name' value='" . $this->setValue($getSubmitFormData, $text_name, $text_value) . "' placeholder='$text_placeholder' maxlength='$text_maxlength' $text_required>
                            </div>";

                            break;

                        case 'number':
                            $number_required = (isset($data->required) && $data->required) ? 'required' : '';
                            $number_label = $data->label ?? '';
                            $number_placeholder = $data->placeholder ?? '';
                            $number_class = $data->className ?? '';
                            $number_name = $data->name ?? '';
                            $number_value = $data->value ?? '';
                            $number_min = $data->min ?? 0;
                            $number_max = $data->max ?? 0;
                            $number_step = $data->step ?? 1;

                            $html .= "<div class='form-group'>
                                <label>$number_label</label>
                                <input type='number' class='$number_class' name='$number_name' value='" . $this->setValue($getSubmitFormData, $number_name, $number_value) . "' placeholder='$number_placeholder' min='$number_min' max='$number_max' step='$number_step' $number_required>
                            </div>";

                            break;

                        case 'textarea':
                            $textarea_required = (isset($data->required) && $data->required) ? 'required' : '';
                            $textarea_label = $data->label ?? '';
                            $textarea_description = $data->description ?? '';
                            $textarea_class = $data->className ?? '';
                            $textarea_name = $data->name ?? '';
                            $textarea_value = $data->value ?? '';
                            $textarea_subtype = $data->subtype ?? '';
                            $textarea_rows = $data->rows ?? '';

                            if ($textarea_subtype == 'textarea') {
                                $html .= "<div class='form-group'>
                                <label>$textarea_label</label>";
                                if ($textarea_description) {
                                    $html .= "<small class='form-text text-muted'>$textarea_description</small>";
                                }
                                $html .= "<textarea class='$textarea_class' name='$textarea_name' rows='$textarea_rows' $textarea_required>" . $this->setValue($getSubmitFormData, $textarea_name, $textarea_value) . "</textarea>
                                </div>";
                            } else if ($textarea_subtype == 'tinymce') {
                                $html .= "<div class='form-group'>
                                <label>$textarea_label</label>";
                                if ($textarea_description) {
                                    $html .= "<small class='form-text text-muted'>$textarea_description</small>";
                                }
                                $html .= "<textarea class='$textarea_class tinymce' name='$textarea_name' rows='$textarea_rows' $textarea_required>" . $this->setValue($getSubmitFormData, $textarea_name, $textarea_value) . "</textarea>
                                </div>";
                            } else if ($textarea_subtype == 'quill') {
                                $html .= "<div class='form-group'>
                                    <label>$textarea_label</label>";

                                if ($textarea_description) {
                                    $html .= "<small class='form-text text-muted'>$textarea_description</small>";
                                }

                                $html .= "<input type='hidden' name='$textarea_name' />
                                    <div id='quill-editor' class='quill-editor $textarea_class'>" . $this->setValue($getSubmitFormData, $textarea_name, $textarea_value) . "</div>
                                </div>";
                            }
                            break;

                        case 'radio-group':
                            echo "<pre>";
                            print_r($data);
                            exit;
                            break;

                        default:
                            $html .= "<div class='alert alert-light' role='alert'>
                                No form field match.
                            </div>";
                            break;
                    }
                }

                $html .= "<button type='submit' class='btn btn-primary'>Submit</button>
                    </form>
                </div>";
            } else {
                $html .= "<div class='alert alert-light' role='alert'>
                Oops, no formdata found.
              </div>";
            }
        } else {
            $html .= "<div class='alert alert-light' role='alert'>
            Oops, there are no forms.
          </div>";
        }

        return view('formbuilder.formview', compact('html'));
    }

    /**
     * # Set value for edit form
     */
    public function setValue($edit_form_data, $form_field_key, $form_field_value)
    {
        if ($edit_form_data) {
            if (isset($edit_form_data->form_data) && $edit_form_data->form_data) {
                $edit_form_data_decode = (array) json_decode($edit_form_data->form_data);


                if (strpos($form_field_key, 'select') !== false) {
                    return isset($edit_form_data_decode[$form_field_key]) ? explode(", ", $edit_form_data_decode[$form_field_key]) : $form_field_value;
                }
                return $edit_form_data_decode[$form_field_key] ?? $form_field_value;
            }
        }
        return $form_field_value;
    }

    /**
     * Submit Form
     */
    public function submitFrom(Request $request)
    {
        unset($request['_token']);

        $form_data = [];
        foreach ($request->all() as $key => $val) {
            if (strpos($key, 'select') !== false) {
                if (is_array($val)) {
                    $form_data[$key] = implode(', ', $val);
                } else {
                    $form_data[$key] = $val;
                }
            } else {
                $form_data[$key] = $val;
            }
        }

        $data_json = json_encode($form_data);

        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $form_id = $request->form_id;
        $standard_id = $request->standard;
        $subject_id = $request->subject;
        $chapter_title = $request->chapter;

        $chapter_id = $request->chapter_id;
        // $form_submited_id = $request->form_submited_id;
        $form_submited_id = '32';
        // $getSubmitFormData = FormSubmitData::/* where('user_id', $user_id)
        //     -> */where('sub_institute_id', $sub_institute_id)
        //     ->where('form_id', $form_id)
        //     ->where('chapter', $chapter_id)
        //     ->get()
        //     ->first();

        if ($form_submited_id) {
            $submitFormData = FormSubmitData::find( $form_submited_id );
        } else {
            $submitFormData = new FormSubmitData();
        }

        // $chapter_list = DB::table('chapter_master')               
        //->where(['sub_institute_id'=>session()->get('sub_institute_id'),'subject_id'=>$request->subject_id,"standard_id"=>$standard_id])  
        //->pluck('chapter_name', 'id');  
        
        if ( $submitFormData ) {
            $chapter = chapterModel::select('id')
                ->where( 'sub_institute_id', session()->get('sub_institute_id') )
                ->where( 'subject_id', $subject_id )
                ->where( "standard_id", $standard_id )
                ->where( 'chapter_name', $chapter_title)
                ->first();
    
            $submitFormData->form_id = $form_id;
            $submitFormData->user_id = $user_id;
            $submitFormData->standard = $standard_id;
            $submitFormData->subject = $subject_id;
            $submitFormData->chapter = $chapter_title;
            $submitFormData->form_data = $data_json;
            $submitFormData->sub_institute_id = $sub_institute_id;
            $submitFormData->save();
            
            if ($submitFormData) {
                if ( $chapter_id ) {
                    // {{ route('lms_lessonplan.index',['standard_id'=>$chdata->standard_id,'subject_id'=>$chdata->subject_id,'chapter_id'=>$chdata->id]) }}
                    return redirect()->route('lms_lessonplan.index',['standard_id'=>$standard_id,'subject_id'=>$subject_id,'chapter_id'=>$chapter_id]);
                    // return view('formbuilder.formview', compact('html'));
                } else {
                    
                    return redirect()->route('formbuild.list');
                }
    
                // return response()->json(['data' => $submitFormData], 201);
            } else {
                return response()->json(['data' => 'record not inserted.'], 404);
            }
        } else {
            return response()->json(['data' => 'record not found.'], 404);
        }
    }

    /**
     * Form Builder
     * Display Record
     */
    public function displayFormDataRecord(Request $request)
    {
        $form_id = 1;
        $user_id = session()->get('user_id');
        $sub_institute_id = session()->get('sub_institute_id');

        // Get Form
        $get_from_fields_json = FormTable::find($form_id);

        // get form submitted Data
        $get_form_data = FormSubmitData::where('form_id', $form_id)
            ->where('user_id', $user_id)
            ->where('sub_institute_id', $sub_institute_id)
            ->get()
            ->first();

        
            
        $form_fields_object = json_decode($get_from_fields_json->form_json);
        if ( !empty($form_fields_object) && !empty($get_form_data) ) {
            $form_data = (array) json_decode($get_form_data->form_data);
            $fieldObject = [];
            
            foreach ($form_fields_object as $formField) {
                
                if ($formField->type == 'header') {
                    $fieldObject['header'] = $formField->label;
                    // continue;
                }

                if ( $formField->type == 'text' || $formField->type == 'textarea' || $formField->type == 'number' || $formField->type == 'date' ) {
                    if ( isset($form_data[$formField->name]) ) {
                        $formField->value = $form_data[$formField->name];
                        $fieldObject[$formField->label] = $form_data[$formField->name];
                    }
                }

                if ( $formField->type == 'select' ) {

                    if ( $formField->label == 'Standard' ) {
                        if ( isset($form_data[$formField->name]) ) {
                            
                            $get_standard_id = DB::table('standard')
                            ->select('id')
                            ->where('name', $form_data[$formField->name])
                            ->where('sub_institute_id', $sub_institute_id)
                            ->first();

                            $formField->values = $form_data[$formField->name];
                            $fieldObject[$formField->label] = $form_data[$formField->name];
                        }                        
                    }

                    if ( isset($form_data[$formField->name]) ) {
                        $formField->values = $form_data[$formField->name];
                        $fieldObject[$formField->label] = $form_data[$formField->name];
                    }
                }
            }
        }

        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = !empty($fieldObject) ? "SUCCESS": 'No data found';
        $res['data'] = $fieldObject;
        
        return is_mobile($type,'lms/lessonplan/form_data_table_add_lessonplan',$res,"view");  
    }
}
