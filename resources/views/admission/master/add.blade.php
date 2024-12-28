@extends('layout')
@section('container')
<style>
    .tab-title{
        background:#865bef;
    }

    .fieldTitle{
        padding: 16px 0px;
        background: #ae9adf;
        border-radius: 32px;
    }
    .fieldLists{
        background:#ede8fb;
        padding: 10px;
        border-top-left-radius: 30px;
        border-top-right-radius: 30px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-evenly;
    }
    .fieldSpans{
        padding:12px;
        background:#fff;
        border:none;
        border-radius:20px;
        margin: 7px;
        box-shadow: 5px 10px #ddd;
    }
    .childDiv{
        margin-top: 20px;
        padding: 20px;
        background: #f9e8fb;
        border-bottom-left-radius: 30px;
        border-bottom-right-radius: 30px;
    }
    .btn{
        margin-top:10px;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Master Settings</h4>
            </div>
        </div>

        <div class="card">
            <div class="col-lg-12 col-sm-12 col-xs-12">
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code']==1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
                <div class="sttabs tabs-style-linemove triz-verTab bg-white style2 text-center">
                    <!-- tabs list starts -->
                    <ul class="nav nav-tabs tab-title mb-4">
                        <li class="nav-item"><a href="#section-linemove-1" class="nav-link active" aria-selected="true" data-toggle="tab"><span>Admission Enquiry</span></a></li>
                        <li class="nav-item"><a href="#section-linemove-2" class="nav-link" aria-selected="false" data-toggle="tab"><span>Admission Registration</span></a></li>
                        <li class="nav-item"><a href="#section-linemove-3" class="nav-link" aria-selected="false" data-toggle="tab"><span>Admission Confirmation</span></a></li>
                        <li class="nav-item"><a href="#section-linemove-4" class="nav-link" aria-selected="false" data-toggle="tab"><span>Change Fields Name</span></a></li>
                    </ul>
                    <!-- tabs list ends -->

                    <!-- tabs sections start -->
                    <div class="tab-content">
                        <!-- tab 1 start  -->
                        <div class="tab-pane p-3 active" id="section-linemove-1" role="tabpanel">
                            <div class="col-md-12">
                                <div class="parentDiv">
                                    <h4 class="fieldTitle">Select Enquiry Fields</h4>
                                    <div class="fieldLists">
                                        @foreach($data['enquryFields'] as $key => $value)
                                        @php 
                                        $fieldName = str_replace('_',' ',ucwords($value));
                                        @endphp
                                        <div class="fieldSpans" onclick="addFields('enquiry','{{$fieldName}}','{{$value}}')" id="enquiry{{$value}}">{{$fieldName}}</div>
                                        @endforeach
                                    </div>
                                </div>
                                <form action="{{route('admission_master.store')}}" method="post">
                                    @csrf
                                    <input type="hidden" name="table_name" value="enquiry">
                                    <div class="childDiv" id="enquiryChildDiv">
                                    @if(!empty($data['enquiryCustoms']))
                                        @foreach($data['enquiryCustoms'] as $key=>$value)
                                        @php 
                                        $fieldName = str_replace('_',' ',ucwords($value["field_name"]));
                                        @endphp
                                        <input class="fieldSpans" name='enquiryFields[]' value='{{$fieldName}}' onclick="removeFields('enquiry','{{$fieldName}}','{{$value["field_name"]}}')" id="enquiryInput{{$value["field_name"]}}" readonly>
                                        @endforeach
                                    @endif
                                    </div>
                                    <input type="submit" value="Add Fields" name="submit" class="btn btn-primary" id="enquirybtn">
                                </form>
                            </div>
                        </div>
                        <!-- tab 1 end  -->
                        
                        <!-- tab 2 start  -->
                        <div class="tab-pane p-3" id="section-linemove-2" role="tabpanel">
                            <div class="col-md-12">
                                <div class="parentDiv">
                                    <h4 class="fieldTitle">Select Registration Fields</h4>
                                    <div class="fieldLists">
                                        @foreach($data['RegistrationFields'] as $key => $value)
                                        @php 
                                        $fieldName = str_replace('_',' ',ucwords($value));
                                        @endphp
                                        <div class="fieldSpans" onclick="addFields('registration','{{$fieldName}}','{{$value}}')" id="registration{{$value}}">{{$fieldName}}</div>
                                        @endforeach
                                    </div>
                                </div>
                                <form action="{{route('admission_master.store')}}" method="post">
                                    @csrf
                                    <input type="hidden" value="registration"  name="table_name">
                                    <div class="childDiv" id="registrationChildDiv">
                                    @if(!empty($data['registrationCustom']))
                                        @foreach($data['registrationCustom'] as $key=>$value)
                                        @php 
                                        $fieldName = str_replace('_',' ',ucwords($value["field_name"]));
                                        @endphp
                                        <input class="fieldSpans" name='registrationFields[]' value='{{$fieldName}}' onclick="removeFields('registration','{{$fieldName}}','{{$value["field_name"]}}')" id="registrationInput{{$value["field_name"]}}" readonly>
                                        @endforeach
                                    @endif
                                    </div>
                                    <input type="submit" value="Add Fields" name="submit" class="btn btn-primary" id="registrationbtn">
                                </form>
                            </div>
                        </div>
                        <!-- tab 2 end  -->

                        <!-- tab 3 start  -->
                        <div class="tab-pane p-3" id="section-linemove-3" role="tabpanel">
                            <div class="col-md-12">
                                <div class="parentDiv">
                                    <h4 class="fieldTitle">Select Confirmation Fields</h4>
                                    <div class="fieldLists">
                                        @foreach($data['confirmationFields'] as $key => $value)
                                        @php 
                                        $fieldName = str_replace('_',' ',ucwords($value));
                                        @endphp
                                        <div class="fieldSpans" onclick="addFields('confirmation','{{$fieldName}}','{{$value}}')" id="confirmation{{$value}}">{{$fieldName}}</div>
                                        @endforeach
                                    </div>
                                </div>
                                <form action="{{route('admission_master.store')}}" method="post">
                                    @csrf
                                    <input type="hidden" value="confirmation"  name="table_name">
                                    <div class="childDiv" id="confirmationChildDiv">
                                    @if(!empty($data['confirmationCustom']))
                                        @foreach($data['confirmationCustom'] as $key=>$value)
                                        @php 
                                        $fieldName = str_replace('_',' ',ucwords($value["field_name"]));
                                        @endphp
                                        <input class="fieldSpans" name='confirmationFields[]' value='{{$fieldName}}' onclick="removeFields('confirmation','{{$fieldName}}','{{$value["field_name"]}}')" id="confirmationInput{{$value["field_name"]}}" readonly>
                                        @endforeach
                                    @endif
                                    </div>
                                    <input type="submit" value="Add Fields" name="submit" class="btn btn-primary" id="confirmationbtn">
                                </form>
                            </div>
                        </div>
                        <!-- tab 3 end  -->

                        <!-- tab 4 start  -->
                        <div class="tab-pane p-3" id="section-linemove-4" role="tabpanel">
                            <div class="col-md-12">
                                @if(isset($data['allAddedFields']) && !empty($data['allAddedFields']))
                                <form action="{{route('admission_master.store')}}" method="post">
                                    @csrf 
                                    <input type="hidden" value="changeFields"  name="table_name">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Sr No</th>
                                                <th>Module Name</th>
                                                <th>Field Name</th>
                                                <th class="text-left">Field Required</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($data['allAddedFields'] as $key=>$value)
                                                @php 
                                                    $moduleName = 'Enquiry';
                                                    if($value['table_name']=="admission_form"){
                                                        $moduleName = "Registration";
                                                    }
                                                    if($value['table_name']=="admission_registration"){
                                                        $moduleName = "Confirmation";
                                                    }
                                                @endphp 
                                                <tr>
                                                    <td>{{$key+1}}</td>
                                                    <td>{{$moduleName}}</td>
                                                    <td>
                                                        <input type="hidden" name="fieldsId[]" value="{{$value['id']}}">
                                                        <input type="text" name="fieldsLabels[]" class="fieldSpans resizableHorizontal" value="{{$value['field_label']}}" style="border:1px solid #ddd !important">
                                                    </td>
                                                    <td class="text-left">
                                                        <input type="checkbox" name="is_required[]" @if($value['required']==1) checked @endif> {{$value['field_label']}}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <input type="submit" value="Update Fields" name="submit" class="btn btn-primary" id="confirmationbtn">
                                </form>
                                @endif
                            </div>
                        </div>
                        <!-- tab 4 end  -->

                    </div>
                    <!-- tabs sections ends -->
                </div>
            </div>
        </div>

    </div>
</div>
@include('includes.footerJs')
<script>
    $(document).ready(function(){
        @if(!empty($data['enquiryCustoms']))
            $('#enquiryChildDiv').show();
            $('#enquirybtn').show();
        @else
            $('#enquiryChildDiv').hide();
            $('#enquirybtn').hide();
        @endif

        @if(!empty($data['registrationCustom']))
            $('#registrationChildDiv').show();
            $('#registrationbtn').show();
        @else
            $('#registrationChildDiv').hide();
            $('#registrationbtn').hide();
        @endif

        @if(!empty($data['confirmationCustom']))
            $('#confirmationChildDiv').show();
            $('#confirmationbtn').show(); 
        @else
            $('#confirmationChildDiv').hide();
            $('#confirmationbtn').hide();   
        @endif
    })

   function addFields(tableName,FieldName,key){
    $('#'+tableName+'ChildDiv').show();
    $('#'+tableName+'btn').show();
    var input = `<input class="fieldSpans" name='${tableName}Fields[]' value='${FieldName}' onclick="removeFields('${tableName}','${FieldName}','${key}')" id="${tableName}Input${key}" readonly>`;
    $('#'+tableName+'ChildDiv').append(input);
    $('#'+tableName+key).hide();
   }

   function removeFields(tableName,FieldName,key){
    $('#'+tableName+key).show();
    $('#'+tableName+'Input'+key).remove();

    if ($('#' + tableName + 'ChildDiv').find('input').length === 0) {
        $('#'+tableName+'btn').hide();
        $('#' + tableName + 'ChildDiv').hide();
    }
   }
</script>
@include('includes.footer')
@endsection