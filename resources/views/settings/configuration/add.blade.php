@extends('layout')
@section('container')
<style>
   .select-module {
   display: flex;
   flex-wrap: wrap;
   }
   .select-module>label {
   padding: 10px;
   }
   .displayTabs {
   padding: 12px 0px;
   text-align: center;
   }
   .activeTab {
   /* border-bottom:4px solid black; */
   background: #fff;
   border-top-right-radius: 10px;
   border-top-left-radius: 10px;
   /* padding: 12px 0px; */
   }
   .ContainerCard {
   margin: 0px !important;
   width: 100%;
   box-shadow: none !important;
   }
   .basicContainer {
   width: 100%;
   }
   .basicCard,
   .basicSubContents {
   width: 100%;
   border: 1px solid #ddd;
   display: flex;
   }
   .basicCard {
   padding: 20px 6px;
   flex-wrap: wrap;
   margin-bottom: 30px;
   }
   .basicSubContents,
   .basicContents {
   height: 120px;
   }
   .basicContents {
   width: 50%;
   /* margin:0px 6px; */
   padding: 0px 10px 0px 10px;
   }
   .content40 {
   width: 40%;
   padding: 16px;
   border-right: 1px solid #ddd;
   display: flex;
   justify-content: space-between;
   }
   .content60 {
   width: 60%;
   padding: 16px;
   display: flex;
   justify-content: space-between;
   }
   .dragIcon {
   cursor: grab;
   }
   .dragIcon>span {
   font-size: 24px;
   }
   h1,
   h2,
   h3,
   h4,
   h5,
   h6,
   p {
   margin-bottom: 0px !important;
   }
   /* Green switch styling */
   .customVisible:checked~.custom-control-label::before {
   color: #fff;
   border-color: #0dc143;
   background-color: #0dc143;
   }
   .infoDiv{
        margin : 0px 20px;
        padding : 10px;
        border-left:5px solid #6297C3;  
        border-radius: 5px; 
        height: 116px;
        margin-bottom: 30px;
        color: #6297C3;
    }
    .selected-items {
        display: flex;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    .selected-item {
        background: black;
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        margin: 5px;
        display: flex;
        align-items: center;
    }
    .selected-item span {
        margin-left: 8px;
        cursor: pointer;
        font-weight: bold;
    }
    .datalistInput {
        width: 100%;
        padding: 8px;
        margin-bottom: 5px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .radio-group {
        display: flex;
        border: 2px solid #ccc;
        border-radius: 10px; /* Capsule Shape */
        overflow: hidden;
        width: 130px;
    }

    .radio-btn {
        flex: 1;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease-in-out;
        background-color: white;
        border: none;
        margin:0px;
    }
    .radio-btn input {
        display: none;
    }

    /* YES Selected */
    .radio-btn.yes.selected {
        background: #28a745;
        color: white;
    }

    /* NO Selected */
    .radio-btn.no.selected {
        background: #dc3545;
        color: white;
    }
    .duplicateHead{
        display: flex;
        align-items: center;
        gap: 20px; 
    }
    /* // rights blade css  */
    .dots {
    height: 16px;
    width: 16px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
  }
  .dotsDiv{
    display: flex;
    align-items: center;
    justify-content: flex-end; 
  }
  .dotBlack, .knob-start{
    background: black !important;
  }
  .dotOrange, .knob-middle{
    background: orange !important;
  }
  .dotGreen, .knob-end{
    background: green !important;
  }

  .fieldContainer {
    display: flex;
    align-items: center;
    /* Align items vertically */
    gap: 10px;
    /* Space between switch and text */
  }

  .toggle-container {
    display: flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
  }

  .toggle-switch {
    width: 45px;
    height: 16px;
    background: #fff;
    border : 1px solid #ddd;
    border-radius: 25px;
    position: relative;
    transition: background 0.3s;
  }

  .toggle-knob {
    width: 16px;
    height: 16px;
    background: white;
    /* Default knob color */
    border-radius: 50%;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    transition: left 0.3s, background 0.3s;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
  }

  /* Default */
  .knob-middle {
    left: 50%;
    transform: translate(-50%, -50%);
  }

  /* Middle */
  .knob-end {
    left: calc(100% - 16px) !important;
  }

  /* End */

  .fieldNames h6 {
    margin: 0;
    /* Remove default margin */
    font-size: 16px;
  }
  .mdi-up, .mdi-down{
    background: #ededed;
    font-size: 36px;
    border-radius: 6px;
    display: flex;
    justify-content: center;
    width: 32px;
    padding: 8px 34px;
  }
</style>
<div id="page-wrapper">
   <div class="container-fluid">
   @if ($sessionData = Session::get('data'))
                <div class="@if($sessionData['status']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif
      <!-- Start Select Module -->
      <div class="card" style="width:100%">
         <div class="select-module">
            <label for="module_select"><b>Select Module</b></label>
            <select name="module_name" id="module_name" class="form-control col-md-2">
               @foreach($data['modulesList'] as $key=>$value)
               <option value="{{$value['module']}}">{{$value['module']}}</option>
               @endforeach
            </select>
         </div>
      </div>
      <!-- End  Select Module -->
      <!-- start module details -->
      <!-- <div class="card" style="width:100%"> -->
      <!-- tabs start  -->
      <div class="tabRow">
         <div class="row" style="padding:15px 15px 0px 15px;">
            <div class="displayTabs detailTab col-md-2 activeTab" onclick="activeTabs('detail')">
               <h6>Detail View Layout</h6>
            </div>
            <div class="displayTabs duplicateTab col-md-2" onclick="activeTabs('duplicate')">
               <h6>Duplicate Prevention</h6>
            </div>
            <!-- rights module in progress  -->
            <!-- <div class="displayTabs rightsTab col-md-2" onclick="activeTabs('rights')">
               <h6>Field Rights</h6>
            </div> -->
            <div class="col-md-8"></div>
         </div>
         <!-- tabs containers start -->
         <div class="row" style="margin:0px !important">
            <div class="card ContainerCard">
               <div class="dataContainer detailContainer show">

                  <div class="addBlock" align="right">
                     <button class="btn btn-outline-dark" data-toggle="modal" data-target="#addModal"><span class="mdi mdi-plus"></span> Add Feild</button>
                     @if(isset($data['deletedData']) && count($data['deletedData']) > 0)
                     <button class="btn btn-outline-secondary" data-toggle="modal" data-target="#restoreModal"><span class="mdi mdi-restore"></span> Restore</button>
                     @endif
                  </div>
                  
                  <div class="basicContainer">
                  </div>
               </div>
                <!-- tab 2 starts  -->
               <div class="dataContainer duplicateContainer hide">
                    @include('settings.configuration.duplicate')
               </div>   
                <!-- tab 2 ends  -->

                <!-- tab 3 starts module in progress -->
               {{-- <div class="dataContainer rightsContainer hide">
                    @include('settings.configuration.rights')
                </div> --}}
                <!-- tab 3 ends  -->

            </div>
         </div>
         <!-- tabs containers start -->
      </div>
      <!-- tabs  ends-->
      <!-- </div> -->
      <!-- end module details -->
      <!-- edit module starts  -->
      <div class="openEditModel">
      </div>
      <!-- edit module end  -->
   </div>
</div>
<!-- Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
   <div class="modal-dialog" role="document" style="max-width: 1000px !important;">
      <div class="modal-content">
         <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Add Field</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
            </button>
         </div>
         <div class="modal-body">

          <form class="row" action="{{route('configurations.store')}}" method="POST">
            @csrf
            <input type="hidden" name="module" id="addModule">
            <input type="hidden" name="main_menu" id="addMainMenu">
            <input type="hidden" name="table_name" id="addTableName">
            <input type="hidden" name="formType" value="addFields">
               <div class="col-md-6 mb-3">
                  <label class="font-weight-bold text-dark">Select Field</label>
                  <select name="field_name" id="fieldSelect" class="form-control fieldSelect">
                  </select>
               </div>
               <div class="col-md-6 mb-3">
                  <label class="font-weight-bold text-dark">Select Type</label>
                  <select name="field_type" id="field_type_add" class="form-control">
                     @foreach($data['fieldTypes'] as $key=>$value)
                     <option value="{{$value}}">{{ucfirst($value)}}</option>
                     @endforeach
                  </select>
               </div>
               <!-- Label Name -->
               <div class="col-md-6 mb-3">
                  <label class="font-weight-bold text-dark">Label Name</label>
                  <input type="text" name="field_label" class="form-control" placeholder="Enter Label Value.." required autocomplete="off">
               </div>
               <!-- Default Value -->
               <div class="col-md-6 mb-3">
                  <label class="font-weight-bold text-dark">Default Value</label>
                  <input type="text" name="default_value" class="form-control" placeholder="Enter Default Value.." autocomplete="off">
               </div>
               <!-- Validation Rules -->
               <div class="col-md-6 mb-3">
                  <label class="font-weight-bold text-dark">Validation Rules</label>
                  <input type="text" name="validation_rules" class="form-control" placeholder="Enter Validation Rules.." autocomplete="off">
               </div>
               <!-- Show Field Switch -->
               <div class="col-md-3 mb-3">
                  <label class="font-weight-bold text-dark">Show Field</label>
                  <div class="custom-control custom-switch">
                     <input type="checkbox" name="is_visible" class="custom-control-input customVisible" id="showField" value="1" checked>
                     <label class="custom-control-label" for="showField"></label>
                  </div>
               </div>
               <!-- Mandatory Field Switch -->
               <div class="col-md-3 mb-3">
                  <label class="font-weight-bold text-dark">Mandatory Field</label>
                  <div class="custom-control custom-switch">
                     <input type="checkbox" name="is_mandatory" class="custom-control-input"  value="1" id="mandatoryField">
                     <label class="custom-control-label" for="mandatoryField"></label>
                  </div>
               </div>
               <div class="col-md-12 mb-4 dropDownDiv">
                  <label class="font-weight-bold text-dark">Dropdown Options</label>
                  <div id="dropdown-options-container" class="p-3 border rounded bg-light">
                     <div class="row add-dropdown-option mb-2">
                        <div class="col-md-5">
                           <label class="small text-secondary">Value</label>
                           <input type="text" name="option_keys[]" class="form-control" placeholder="Enter Dropdown Value.." autocomplete="off">
                        </div>
                        <div class="col-md-5">
                           <label class="small text-secondary">Display Name</label>
                           <input type="text" name="option_values[]" class="form-control" placeholder="Enter Dropdown Text.." autocomplete="off">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                           <i class="mdi mdi-plus-circle text-primary add-option-dropdown" onclick="addOption('add-dropdown-option','dropdown')"></i>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-md-12 mb-4 radioDiv">  
                  <label class="font-weight-bold text-dark">Radio Options</label>
                  <div id="dropdown-options-container" class="p-3 border rounded bg-light">
                     <div class="row add-radio-option mb-2">
                        <div class="col-md-5">
                           <label class="small text-secondary">Value</label>
                           <input type="text" name="option_keys[]" class="form-control"  placeholder="Enter Radio Text.." autocomplete="off">
                        </div>
                        <div class="col-md-5">
                           <label class="small text-secondary">Display Name</label>
                           <input type="text" name="option_values[]" class="form-control"  placeholder="Enter Radio Text.." autocomplete="off">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                           <i class="mdi mdi-plus-circle text-primary add-option-radio" onclick="addOption('add-radio-option','radio')"></i>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-md-12 mb-4 checkboxDiv">  
                  <label class="font-weight-bold text-dark">Checkbox Options</label>
                  <div id="dropdown-options-container" class="p-3 border rounded bg-light">
                     <div class="row add-checkbox-option mb-2">
                        <div class="col-md-5">
                           <label class="small text-secondary">Value</label>
                           <input type="text" name="option_keys[]" class="form-control"  placeholder="Enter checkbox Text.." autocomplete="off">
                        </div>
                        <div class="col-md-5">
                           <label class="small text-secondary">Display Name</label>
                           <input type="text" name="option_values[]" class="form-control"  placeholder="Enter checkbox Text.." autocomplete="off">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                           <i class="mdi mdi-plus-circle text-primary add-option-checkbox" onclick="addOption('add-checkbox-option','checkbox')"></i>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-md-12">
                  <center>
                     <button type="submit" class="btn btn-success">Add Field</button>
                  </center>
               </div>
           </form>
         </div>

      </div>

   </div>
</div>

<!-- Restore Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:1000px !important">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Restore Fields</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      <form action="{{route('restoreData')}}" method="POST" onsubmit="checkRestoreCheckbox()">
        @csrf
        <div class="table-responsive">
        <table id="example" class="table">
            <thead>
                <tr>
                    <th>Sr No.</th>
                    <th>Module</th>
                    <th>Field Name</th>
                    <th>Field Label</th>
                    <th>Field Type</th>
                    <th align="left">Deleted At</th>
                    <!-- <th scope="col" align="left">Action</th> -->
                </tr>
            </thead>
            <tbody>
                @if(isset($data['deletedData']) && count($data['deletedData']) > 0)
                    @foreach($data['deletedData'] as $key=>$value)
                    <tr>
                        <td><input type="checkbox" name="fieldIds[]" value="{{$value['id']}}">&nbsp;{{$key+1}}</td>
                        <td>{{$value['module']}}</td>
                        <td>{{$value['field_name']}}</td>
                        <td>{{$value['field_label']}}</td>
                        <td>{{$value['field_type']}}</td>
                        <td>{{ date('d-m-Y',strtotime($value['deleted_at'])) }}</td>
                        {{-- <td align="center"><button class="btn btn-outline-warning" onclick="restoreData('{{$value['id']}}')"><span class="mdi mdi-restore"></span></button></td>--}}
                    </tr>
                    @endforeach
                @else 
                <tr>
                    <td align="center" colspan="4">No Data Found</td>
                </tr>
                @endif
            
            </tbody>
        </table>
        </div>
        <div class="row">
            <div class="col-md-12 text-center">
                <button type="submit" class="btn btn-primary"><span class="mdi mdi-restore"></span> Restore</button>
            </div>
        </div>
        </form>
      </div>
    </div>
  </div>
</div>
@include('includes.footerJs')
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
   $(document).ready(function () {
        $('.dropDownDiv').hide();
        $('.radioDiv').hide();
          $('.checkboxDiv').hide();
        // get all fields set by institute or triz
       getFieldData();
   
       $('#module_name').on('change', function () {
           getFieldData();
       })

       $('#field_type_add').on('change',function(){
          $('.dropDownDiv').hide();
          $('.radioDiv').hide();
          $('.checkboxDiv').hide();
          var field_type = $(this).val();
          // alert(field_type);
          if(field_type==='dropdown'){
            $('.dropDownDiv').show();
          }
          if(field_type==='radio'){
            $('.radioDiv').show();
          }
          if(field_type==='checkbox'){
            $('.checkboxDiv').show();
          }
       })
   })
   function activeTabs(activeTab) {
       $('.displayTabs').removeClass('activeTab');
       $('.' + activeTab + 'Tab').addClass('activeTab');
       $('.dataContainer').hide();
       $('.' + activeTab + 'Container').show();
   }
   
   function getFieldData() {
       var selModule = $('#module_name').val();
       var mainMenu = "{{ isset($_REQUEST['main_menu_id']) ? $_REQUEST['main_menu_id'] : 0}}";

       $('.basicContainer').empty();
       $.ajax({
           url: "{{route('getFeildLists')}}",
           data: { module: selModule,main_menu_id:mainMenu },
           type: 'get',
           success: function (result) {
               console.log(result);
               let response = result['tableData'];
               let row = ``;
               let count = 0;
               $.each(response, function (sectionName, sectionData) {

               row += `<h5><b>${sectionName}</b></h5> <div class="basicCard">`;
               $.each(sectionData, function (index, field) {
                 //    append values to hidden fields of add fields 
                    $('#addModule').val(field.module);
                    $('#addMainMenu').val(field.main_menu);
                    $('#addTableName').val(field.table_name);

                   if (count === 0) {
                       row += `<div class="basicContents"  data-id="${field.field_name}" data-section="Basic Details"><div class="basicSubContents">`;
                   }
   
                   row += `
                   
                         <div class="content40">
                             <div class="dragIcon">
                                 <span class="mdi mdi-drag"></span>
                             </div>
                             <div class="fieldData text-right">
                                 <p class="fieldTitle" style="margin:0px !important">
                                     <b>${field.field_label}</b> 
                                     ${field.is_mandatory ? '<span class="mdi mdi-asterisk" style="color:red"></span>' : ''}
                                 </p>
                                 <p class="fieldType" style="margin:0px !important">${field.field_type}</p>
                             </div>
                         </div>
   
                         <div class="content60">
                             <div class="column1">
                                 <ul>
                                     <li style="padding-bottom:30px;color:${field.is_mandatory ? "black" : "#979797"}"><span class="mdi mdi-information"></span> Mandatory</li>
                                     <li style="color:${field.validation_rules ? "black" : "#979797"}"><span class="mdi mdi-asterisk"></span> Validation</li>
                                 </ul>
                             </div>
                             <div class="column2">
                                 <ul>
                                     <li style="padding-bottom:30px;color:${field.is_visible ? "black" : "#979797"}"><span class="mdi mdi-eye-outline"></span> Visible</li>
                                     <li style="color:${field.default_value ? "black" : "#979797"}"><span class="mdi mdi-alpha-d-circle"></span> Default</li>
                                 </ul>
                             </div>
                             <div class="column3">
                                 <span class="mdi mdi-pencil" onclick="editModel('${field.field_name}','${field.field_type}','${field.section}')"></span>`;
                                 if(sectionName==='Custom Details'){
                                    row += `<span class="mdi mdi-delete" onclick="deleteField(${field.id})"></span>`;
                                 }
                            row += `  </div>
                         </div>
                 `;
   
                   count++;
   
                   if (count === 1) {
                       // Close the row after every 2 items or at the last item
                       row += `</div></div>`;
                       count = 0;
                   }
               });
               row += `</div>`;
            });

               $(".basicContainer").append(row);
   
               $('#fieldSelect').empty();
               $('#optionsList').empty();

               Object.values(result.table_fields).forEach(element => {
                 let formattedText = element.replace(/_/g, ' ') // Replace underscores with spaces
                             .replace(/\b\w/g, char => char.toUpperCase()); // Capitalize first letter of each word
   
                   $('.fieldSelect').append(`<option value='${element}'>${formattedText}</option>`);
                
               });
               Object.values(result.duplicateFields).forEach(element => {
                 let formattedText = element.replace(/_/g, ' ') // Replace underscores with spaces
                             .replace(/\b\w/g, char => char.toUpperCase()); // Capitalize first letter of each word
   
                   $('.fieldSelect').append(`<option value='${element}'>${formattedText}</option>`);
                 
                   $('#optionsList').append(`<option value='${element}'></option>`);

               });
            //    console.log('duplicate_status='+result.masterTable.duplicate_status);
               if (result.masterTable) {
                    if(result.masterTable.duplicate_status === 1){
                        $(".radio-btn.yes").addClass("selected");
                        $(".radio-btn.no").removeClass("selected");
                        $(".radio-btn.yes input").prop("checked", true);
                        $('.duplicateFields').show(); 
                    }

                    if (result.masterTable.duplicate_fields != null) {
                        let container = $("#selectedItems");
                        container.html(""); // Clear container

                        // Convert comma-separated string into an array
                        let selectedValues = result.masterTable.duplicate_fields.split(",");

                        selectedValues.forEach(function (val, index) {
                            container.append(
                                `<div class='selected-item'>
                                    ${val} <span class="remove-item" data-index="${index}">&times;</span>
                                </div>`
                            );
                        });

                        $("#selectedValues").val(selectedValues.join(",")); // Store values in hidden input
                    }
                }
                
              $('#mainMenu').val(mainMenu);
              $('#moduleName').val(selModule);
   
           },
           error: function (response) {
               alert('Opps ! Something went wrong');
           }
       })
   }
   
   function editModel(fieldName, fieldType, sectionName) {
       var selModule = $('#module_name').val();
       $('.openEditModel').empty();
       $.ajax({
           url: "{{ route('configurations.edit', ':fieldName') }}".replace(':fieldName', fieldName),
           type: 'GET',
           data: {
               fieldName: fieldName,
               fieldType: fieldType,
               sectionName: sectionName,
               module: selModule
           },
           success: function (response) {
               console.log(response);
               let updateUrl = "{{ route('configurations.update', ':id') }}".replace(':id', response['editData'].id);
   
               $('#exampleModal').remove();
               let model = `
                       <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                           <div class="modal-dialog" role="document" style="max-width: 1000px !important;">
                               <div class="modal-content shadow-lg">
                                   <div class="modal-header">
                                       <h5 class="modal-title" id="exampleModalLabel">
                                           <i class="fas fa-edit"></i> Edit Field Properties: ${response['editData'].field_label}
                                       </h5>
                                       <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                           <span aria-hidden="true">&times;</span>
                                       </button>
                                   </div>
   
                                   <div class="modal-body">
                                       <form action='${updateUrl}' method="POST" class="p-3">
                                           <input type="hidden" name="_method" value="PUT">
                                           <input type="hidden" name="_token" value="{{ csrf_token() }}">
   
                                           <div class="row">
                                               <!-- Field Type -->
                                               <div class="col-md-6 mb-3">
                                                   <label class="font-weight-bold text-dark">Select Field Type</label>
                                                   <select class="form-control" disabled>
                                                       <option>${response['editData'].field_type}</option>
                                                   </select>
                                               </div>
   
                                               <!-- Label Name -->
                                               <div class="col-md-6 mb-3">
                                                   <label class="font-weight-bold text-dark">Label Name</label>
                                                   <input type="text" name="field_label" value="${response['editData'].field_label}" class="form-control" autocomplete="off">
                                               </div>
   
                                               <!-- Default Value -->
                                               <div class="col-md-6 mb-3">
                                                   <label class="font-weight-bold text-dark">Default Value</label>
                                                   <input type="text" name="default_value" class="form-control"
                                                       ${response['editData'].default_value != null ? `value="${response['editData'].default_value}"` : 'placeholder="Enter default value"'} autocomplete="off">
                                               </div>
   
                                               <!-- Validation Rules -->
                                               <div class="col-md-6 mb-3">
                                                   <label class="font-weight-bold text-dark">Validation Rules</label>
                                                   <input type="text" name="validation_rules" class="form-control"
                                                       ${response['editData'].validation_rules != null ? `value="${response['editData'].validation_rules}"` : 'placeholder="Enter Validations like number, email, etc."'} autocomplete="off">
                                               </div>
   
                                               <!-- Show Field Switch -->
                                               <div class="col-md-6 mb-3">
                                                   <label class="font-weight-bold text-dark">Show Field</label>
                                                   <div class="custom-control custom-switch">
                                                       <input type="checkbox" name="is_visible" class="custom-control-input customVisible" id="showField"
                                                           value="1" ${response['editData'].is_visible == 1 ? 'checked' : ''}>
                                                       <label class="custom-control-label" for="showField"></label>
                                                   </div>
                                               </div>
   
                                               <!-- Mandatory Field Switch -->
                                               <div class="col-md-6 mb-3">
                                                   <label class="font-weight-bold text-dark">Mandatory Field</label>
                                                   <div class="custom-control custom-switch">
                                                       <input type="checkbox" name="is_mandatory" class="custom-control-input" id="mandatoryField"  value="1"
                                                           value="1" ${response['editData'].is_mandatory == 1 ? 'checked' : ''}>
                                                       <label class="custom-control-label" for="mandatoryField"></label>
                                                   </div>
                                               </div>
   
                                               <input type="hidden" value="${response['editData'].main_menu}" name="main_menu_id">`;
   
                                               // **Dropdown Options Handling**
                                               if (response['editData'].field_type === 'dropdown' || response['editData'].field_type === 'radio' || response['editData'].field_type === 'checkbox') {
                                                   model += `
                                                     <div class="col-md-12 mb-4">
                                                         <label class="font-weight-bold text-dark">${response['editData'].field_type?.charAt(0).toUpperCase() + response['editData'].field_type?.slice(1) || ''} Options</label>
                                                         <div id="dropdown-options-container" class="p-3 border rounded bg-light">`;
   
                                                                 // Parse field_value safely
                                                                 let jsonVal;
                                                                 try {
                                                                     jsonVal = JSON.parse(response['editData'].field_value || "{}");
                                                                 } catch (e) {
                                                                     console.error("Invalid JSON:", response['editData'].field_value);
                                                                     jsonVal = {};
                                                                 }
   
                                                                 let optionsArray = Object.entries(jsonVal);
   
                                                                 optionsArray.forEach(([key, value]) => {
                                                                     model += `
                                                         <div class="row dropdown-option-remove mb-2">
                                                             <div class="col-md-5">
                                                                 <label class="small text-secondary">Value</label>
                                                                 <input type="text" name="option_keys[]" value="${key}" class="form-control" autocomplete="off">
                                                             </div>
                                                             <div class="col-md-5">
                                                                 <label class="small text-secondary">Display Name</label>
                                                                 <input type="text" name="option_values[]" value="${value}" class="form-control" autocomplete="off">
                                                             </div>
                                                             <div class="col-md-2 d-flex align-items-end">
                                                               <i class="mdi mdi-minus-circle text-danger remove-option-edit" onclick="removeOption('dropdown-option-remove','edit',this)"></i>
                                                             </div>
                                                         </div>
                                                     `;
                                                                 });
   
                                                                 model += `
                                                         <div class="row dropdown-option mb-2">
                                                             <div class="col-md-5">
                                                                 <label class="small text-secondary">Value</label>
                                                                 <input type="text" name="option_keys[]" class="form-control" autocomplete="off">
                                                             </div>
                                                             <div class="col-md-5">
                                                                 <label class="small text-secondary">Display Name</label>
                                                                 <input type="text" name="option_values[]" class="form-control" autocomplete="off">
                                                             </div>
                                                             <div class="col-md-2 d-flex align-items-end">
                                                                 <i class="mdi mdi-plus-circle text-primary add-option" onclick="addOption('dropdown-option','edit')"></i>
                                                             </div>
                                                         </div>
                                                     </div>
                                                 </div>`;
                                               }
   
                           model += `
                                           <div class="col-md-12">
                                               <center>
                                                   <button type="submit" class="btn btn-success">
                                                       Update
                                                   </button>
                                               </center>
                                           </div>
                                       </div>
                                 </form>
                             </div>
                         </div>
                   </div>
             </div>`;
             $('.openEditModel').append(model);
             $('#exampleModal').modal('show');
               
           },
           error: function (response) {
               alert('Opps ! Something went wrong');
           }
       });
   }
   
  //  $(document).on('click', '.add-option', function () {
    function addOption(className,type){
    // <div class="row dropdown-option">
       let optionRow = `
       <div class="row ${className}">
           <div class="col-md-5">
               <label>Value</label>
               <input type="text" name="option_keys[]" class="form-control" autocomplete="off">
           </div>
           <div class="col-md-5">
               <label>Display Name</label>
               <input type="text" name="option_values[]" class="form-control" autocomplete="off">
           </div>
           <div class="col-md-2 d-flex align-items-end">
               <i class="mdi mdi-minus-circle text-danger remove-option-${type}" onclick="removeOption('${className}','${type}',this)"></i>
           </div>
       </div>
       `;
   
       // Insert before the last dropdown option (the one with the add button)
       $('.'+className).last().before(optionRow);
   };
   
   // **Event Listener for Removing an Option**
  //  $(document).on('click', '.remove-option', function () {
    function removeOption(className,type,element){
      // alert(className);
       $(element).closest('.'+className).remove();
   };
   
   $(document).ready(function () {
       $(".basicContainer").sortable({
           placeholder: "sortable-placeholder",
           handle: ".dragIcon",
           items: ".basicContents",
           update: function (event, ui) {
               var selModule = $('#module_name').val();
               let sortedFields = $(this).sortable("toArray", { attribute: "data-id" });
               let section = ui.item.data('section'); 
               // Uncomment when backend is ready
               $.ajax({
                   url: "{{route('updateMenuSortOrder')}}",
                   data: {section: section, orderArr: sortedFields, masterType: 'institute', module: selModule},
                   type: "POST",
                   success: function (response) {
                       console.log("Updated order:", response);
                       if (response.status === 0) {
                           alert(response.message);
                       }
                   },
                   error: function (response) {
                       alert('Oops! Something went wrong');
                   }
               });
           }
       }).disableSelection();
   });

   function deleteField(id) {
        if (confirm('Are you sure you want to delete this field?')) {
            $.ajax({
                url: "{{ route('configurations.destroy', ':id') }}".replace(':id', id),
                type: 'DELETE', // Use DELETE for proper RESTful route
                data: {
                    _token: "{{ csrf_token() }}" // Include CSRF token for Laravel
                },
                success: function (result) {
                    if (result.status=="1") {
                        window.location.reload();
                    } else {
                        alert('Failed to delete the field.');
                    }
                },
                error: function (xhr) {
                    alert('Oops! Something went wrong.');
                    console.error(xhr.responseText);
                }
            });
        }
    }
    // function restoreData(fieldId){
    //     if (confirm('Are you sure you want to restore this field?')) {
    //         $.ajax({
    //             url: "{{ route('restoreData') }}",
    //             type: 'POST',
    //             data: {
    //                 fieldId:fieldId,
    //             },
    //             success: function (result) {
    //                 if (result.status=="1") {
    //                     window.location.reload();
    //                 } else {
    //                     alert('Failed to delete the field.');
    //                 }
    //             },
    //             error: function (xhr) {
    //                 alert('Oops! Something went wrong.');
    //                 console.error(xhr.responseText);
    //             }
    //         });
    //     }
    // }

    function checkRestoreCheckbox(){
        var checked_questions = err = 0;

        $("input[name='fieldIds[]']:checked").each(function() {
            checked_questions = checked_questions + 1;
        });

        if (checked_questions == 0) {
            alert("Please Select Atleast one Checkbox!");
            err = 1;
        }

        if (err == 1) {
            event.preventDefault();
        }

        return err;
    }

    $(document).ready(function () {
    let selectedValues = [];

    $("#searchInput").on("change blur", function () {  // Use change/blur for better accuracy
        let value = $(this).val().trim();
        
        let validOptions = $("#optionsList option").map(function () { 
            return $(this).val().trim(); 
        }).get();

        // Ensure the value is valid and not already selected
        if (validOptions.includes(value) && !selectedValues.includes(value)) {
            console.log("Function called");
            selectedValues.push(value);
            updateSelectedItems();
        }

        $(this).val(""); // Clear input after selection
    });

    function updateSelectedItems() {
        let container = $("#selectedItems");
        container.html(""); // Clear container

        selectedValues.forEach(function (val, index) {
            container.append(
                `<div class='selected-item'>
                    ${val} <span class="remove-item" data-index="${index}">&times;</span>
                </div>`
            );
        });

        $("#selectedValues").val(selectedValues.join(",")); // Store values in hidden input
    }

    $(document).on("click", ".remove-item", function () {
        let index = $(this).data("index");
        selectedValues.splice(index, 1);
        updateSelectedItems();
    });

    // Toggle radio duplicate blade
    $(".duplicateFields").hide();

    $(".radio-btn").on("click", function () {
        $(".radio-btn").removeClass("selected");
        $(this).addClass("selected");
        $(this).find("input").prop("checked", true);
        
        let val = $(this).find("input").val();
        if (val === "1") {
            $(".duplicateFields").fadeIn(); 
        } else {
            $(".duplicateFields").fadeOut();
        }
    });
});



$(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
        });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );

    } );
</script>
@include('includes.footer')
@endsection