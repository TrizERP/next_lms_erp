@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Custom Fields</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                   
                        @if ($sessionData = Session::get('data'))
                        <div class="alert alert-danger alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                        @endif
                        <h3 class="box-title h4 pb-2 border-bottom mb-3">Add Custom Fields</h3>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="nav flex-column nav-pills pr-2 border-right" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                                    <a class="nav-link active" id="v-pills-Textbox-tab" data-toggle="pill" href="#v-pills-Textbox" role="tab" aria-controls="v-pills-Textbox" aria-selected="true" onclick="setValue('text');">
                                        Textbox
                                    </a>
                                    <a class="nav-link" id="v-pills-Checkbox-tab" data-toggle="pill" href="#v-pills-Checkbox" role="tab" aria-controls="v-pills-Checkbox" aria-selected="false" onclick="setValue('checkbox');clearValue('ddn','ddv');">
                                        Checkbox
                                    </a>
                                    <a class="nav-link" id="v-pills-Dropdown-tab" data-toggle="pill" href="#v-pills-Dropdown" role="tab" aria-controls="v-pills-Dropdown" aria-selected="false" onclick="setValue('dropdown');clearValue('cdn','cdv');">
                                        Dropdown
                                    </a>
                                    <a class="nav-link" id="v-pills-Date-tab" data-toggle="pill" href="#v-pills-Date" role="tab" aria-controls="v-pills-Date" aria-selected="false" onclick="setValue('date');" >
                                        Date
                                    </a>
                                    <a class="nav-link" id="v-pills-FileUpload-tab" data-toggle="pill" href="#v-pills-FileUpload" role="tab" aria-controls="v-pills-FileUpload" aria-selected="false" onclick="setValue('file');">
                                        File Upload
                                    </a>
                                    <a class="nav-link" id="v-pills-Textarea-tab" data-toggle="pill" href="#v-pills-Textarea" role="tab" aria-controls="v-pills-Textarea" aria-selected="false" onclick="setValue('textarea');">
                                        Textarea
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="tab-content" id="v-pills-tabContent">
                                    <form action="@if (isset($data))
                                        {{ route('add_fields.update', $data['id']) }}
                                        @else
                                        {{ route('add_fields.store') }}
                                        @endif" enctype="multipart/form-data" method="post">
                                        @if(!isset($data))
                                        {{ method_field("POST") }}
                                        @else
                                        {{ method_field("PUT") }}
                                        @endif
                                        @csrf
                                        <!-- <div class="tab-pane fade show active"  role="tabpanel"> -->
                                            <div class="row">
                                                <div class="col-md-4 form-group">
                                                    <label>Module Name</label>
                                                    <select id='table_name' name="table_name" class="form-control">
                                                        <option value=""> Select Module Name </option>
                                                        <option value="tblstudent">Student</option>
                                                        <option value="tbluser">Staff</option>
                                                        <option value="admission_enquiry">Admission Enquiry</option>
                                                        <option value="admission_form">Admission Form</option>
                                                        <option value="admission_registration">Admission Registration</option>
                                                        <option value="visitor_master">Visitor Management</option>         
                                                        <option value="inward">inward</option>
                                                        <option value="outward">outward</option>   
                                                        <option value="front_desk">front_desk</option>
                                                        <option value="task">task</option> 
                                                        <option value="complaint">complaint</option>
                                                        <option value="petty_cash">petty_cash</option> 
                                                        <option value="hostel_master">hostel_master</option>
                                                        <option value="lms_teacher_resource">lms_teacher_resource</option>
                                                        <!--  <option value="fees_collect">fees_collect</option>-->                  
                                                    </select>
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Field Name</label>
                                                    <input type="text" id='field_name' name="field_name" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Field Label</label>
                                                    <input type="text" id='field_label' name="field_label" class="form-control">
                                                </div>
                                                <!-- <div class="col-md-4 form-group">
                                                    <label>Sort Order</label>
                                                    <input type="text" id='field_sort_order' required name="field_sort_order" class="form-control">
                                                </div> -->                                                
                                                <div class="col-md-4 form-group ml-0 mr-0">
                                                    <div class="checkbox checkbox-info">
                                                        <input id="required" name="required" value="1" type="checkbox">
                                                        <label for="required"> Mandatory </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 form-group ml-0">
                                                    <div class="checkbox checkbox-info">
                                                        <input id="common_to_all" value="1"  name="common_to_all" type="checkbox">
                                                        <label for="common_to_all"> Common For All </label>
                                                    </div>
                                                </div>
                                            </div>
                                        <!-- </div> -->


                                        <div class="tab-pane fade show active" id="v-pills-Textbox" role="tabpanel" aria-labelledby="v-pills-Textbox-tab">
                                            <div class="col-md-4 form-group ml-0">
                                                <label>Field Message</label><small> (Tooltip)</small>
                                                 <!-- <select class="form-control" required name="field_id">
                                                <option value=""> Select Module Name </option>
                                                <option value="">u_id</option>
                                                <option value="">name</option>
                                                <option value="">Adress</option>
                                                <option value="">father</option>
                                                <option value="">mother</option>
                                                <option value="">email</option>
                                                <option value="">maths</option>
                                                <option value="">english grammer</option>
                                                <option value="">soical science</option>
                                                <option value="">science</option>
                                                <option value="">gujrati</option>
                                                <option value="">hindi</option>
                                                <option value="">student</option>
                                                <option value="">komal</option>
                                                <option value="">500</option>
                                                <option value=""></option>
                                                </select> -->                                                    
                                                <input type="text" id='field_message' name="field_message[]" class="form-control">
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="v-pills-Checkbox" role="tabpanel" aria-labelledby="v-pills-Checkbox-tab">
                                            <div class="row align-items-center addButtonCheckbox">
                                                <div class="col-md-4 form-group">
                                                    <label>Checkbox Display Name</label>
                                                    <input type="text" id="cdn[]" name="display_name[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Checkbox Value</label>
                                                    <input type="text" id="cdv[]" name="f_value[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                <br>
                                                    <a href="javascript:void(0);" onclick="addNewRowCheckbox();" class="btn btn-info"><span class=""><i class="fal fa-plus"></i></span></a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="v-pills-Dropdown" role="tabpanel" aria-labelledby="v-pills-Dropdown-tab">
                                            <div class="row align-items-center addButtonDrop">
                                                <div class="col-md-4 form-group">
                                                    <label>Dropdown Display Name</label>
                                                    <input type="text" id="ddn[]" name="display_name[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Dropdown Value</label>
                                                    <input type="text" id="ddv[]" name="f_value[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                <br>
                                                    <a href="javascript:void(0);" onclick="addNewRowDropdown();" class="btn btn-info"><span><i class="fal fa-plus"></i></span></a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="v-pills-Date" role="tabpanel" aria-labelledby="v-pills-Date-tab">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Field Message</label><small> (Tooltip)</small>
                                                        <input type="text" id='field_message' name="field_message[]" class="form-control">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="v-pills-FileUpload" role="tabpanel" aria-labelledby="v-pills-FileUpload-tab">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>File Max Size</label><small> (MB)</small>
                                                        <input type="number" id='file_size_max' name="file_size_max" class="form-control">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="v-pills-Textarea" role="tabpanel" aria-labelledby="v-pills-Textarea-tab">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Field Message</label><small> (Tooltip)</small>
                                                        <input type="text" id='field_message' name="field_message[]" class="form-control">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <input type="hidden" value="textbox" name="field_type" id="field_type">
                                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>

function setValue(value){
    document.getElementById('field_type').value = value;
}

function clearValue(id1,id2)
{
    $("input[id^="+id1+"]").val("");
    $("input[id^="+id2+"]").val("");
    // document.getElementById(id1).value = '';
    // document.getElementById(id2).value = '';
}

function addNewRowCheckbox(){
    var html = '<div class="clearfix"></div><div class="row align-items-center addButtonCheckbox">';    
    html += '<div class="col-md-4 form-group">                                       <label>Checkbox Display Name</label>                                        <input type="text" id="cdn[]" required name="display_name[]" class="form-control">                                    </div>                                    <div class="col-md-4 form-group">                                        <label>Checkbox Value</label>                                        <input type="text" id="cdv[]" required name="f_value[]" class="form-control">                                    </div>                                    <div class="col-md-4 form-group">                                    <br>                                       <a href="javascript:void(0)" onclick="removeNewRowCheckbox();"><span class="btn btn-danger"><i class="fal fa-minus"></i></span></a>                                    </div></div>';
    $('.addButtonCheckbox:last').after(html);
}

function addNewRowDropdown(){
    var html = '<div class="clearfix"></div><div class="row align-items-center addButtonDrop">';    
    html += '<div class="col-md-4 form-group">                                       <label>Dropdown Display Name</label>                                        <input type="text" id="ddn[]" required name="display_name[]" class="form-control">                                    </div>                                    <div class="col-md-4 form-group">                                        <label>Dropdown Value</label>                                        <input type="text" id="ddv[]" required name="f_value[]" class="form-control">                                    </div>                                    <div class="col-md-4 form-group">                                    <br>                                       <a href="javascript:void(0)" onclick="removeNewRowDrop();"><span class="btn btn-danger"><i class="fal fa-minus"></i></span></a>                                    </div></div>';
    $('.addButtonDrop:last').after(html);
}

function removeNewRowDrop() {     
    $(".addButtonDrop:last" ).remove();    
}
function removeNewRowCheckbox() {       
    $(".addButtonCheckbox:last" ).remove();    
}
</script>
@include('includes.footer')
