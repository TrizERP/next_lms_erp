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
   padding: 36px 0px 0px 0px;
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
   h6 {
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
        color: #6297C3;
    }
</style>
<div id="page-wrapper">
   <div class="container-fluid">
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
            <div class="col-md-8"></div>
         </div>
         <!-- tabs containers start -->
         <div class="row" style="margin:0px !important">
            <div class="card ContainerCard">
               <div class="dataContainer detailContainer show">
                  <div class="addBlock">
                     <button class="btn btn-outline-secondary" data-toggle="modal" data-target="#addModel"><span class="mdi mdi-plus"></span> Add Feild</button>
                  </div>
                  <div class="basicContainer">
                  </div>
               </div>
                <!-- tab 2 starts  -->
               <div class="dataContainer duplicateContainer hide">

                    <div class="row infoDiv">
                        <h4 style="width:100%"><span class="fa fa-info-circle"></span> info</h4>
                        <p style="width:100%">Duplicate prevention feature only prevents new duplicate records from getting created by users and external applications. Records created from Import, and from Workflows will not be checked for duplicates.</p>
                        <br>
                        <p style="width:100%">Existing duplicate records can be removed using “Find Duplicates” feature from the module page.</p>
                    </div>

                    <div class="row mx-2 my-4">
                        <div class="col-md-12 d-flex">
                            <div>  <label>Enable duplicate check </label>&nbsp;&nbsp;&nbsp;&nbsp;</div> 
                            <div class="custom-control custom-switch">
                                <input type="checkbox" name="duplicate" class="custom-control-input customVisible" id="duplicate" value="1">
                                <label class="custom-control-label" for="duplicate"></label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label> Select the unique fields on which duplicate records are to be checked</label>
                           <div class="selectFields">
                           <input type="text" list="duplicateFields-list" id="authors" value="" size="50" name="authors" placeholder="Type author names">
<datalist id="duplicateFields-list">
  
</datalist>
                           </div>
                        </div>
                    </div>

               </div>   
                <!-- tab 2 ends  -->

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
<div class="modal fade" id="addModel" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
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
                  <input type="text" name="field_label" class="form-control" placeholder="Enter Label Value.." required>
               </div>
               <!-- Default Value -->
               <div class="col-md-6 mb-3">
                  <label class="font-weight-bold text-dark">Default Value</label>
                  <input type="text" name="default_value" class="form-control" placeholder="Enter Default Value..">
               </div>
               <!-- Validation Rules -->
               <div class="col-md-6 mb-3">
                  <label class="font-weight-bold text-dark">Validation Rules</label>
                  <input type="text" name="validation_rules" class="form-control" placeholder="Enter Validation Rules..">
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
                           <input type="text" name="option_keys[]" class="form-control" placeholder="Enter Dropdown Value..">
                        </div>
                        <div class="col-md-5">
                           <label class="small text-secondary">Display Name</label>
                           <input type="text" name="option_values[]" class="form-control" placeholder="Enter Dropdown Text..">
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
                           <input type="text" name="option_keys[]" class="form-control"  placeholder="Enter Radio Text..">
                        </div>
                        <div class="col-md-5">
                           <label class="small text-secondary">Display Name</label>
                           <input type="text" name="option_values[]" class="form-control"  placeholder="Enter Radio Text..">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                           <i class="mdi mdi-plus-circle text-primary add-option-radio" onclick="addOption('add-radio-option','radio')"></i>
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
@include('includes.footerJs')
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
   $(document).ready(function () {
        $('.dropDownDiv').hide();
        $('.radioDiv').hide();
        // get all fields set by institute or triz
       getFieldData();
   
       $('#module_name').on('change', function () {
           getFieldData();
       })

       $('#field_type_add').on('change',function(){
          $('.dropDownDiv').hide();
          $('.radioDiv').hide();
          var field_type = $(this).val();
          // alert(field_type);
          if(field_type==='dropdown'){
            $('.dropDownDiv').show();
          }
          if(field_type==='radio'){
            $('.radioDiv').show();
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
       $('.basicContainer').empty();
       $.ajax({
           url: "{{route('getFeildLists')}}",
           data: { module: selModule },
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
                                     <li style="padding-bottom:30px"><span class="mdi mdi-information"></span> ${field.is_mandatory ? "Mandatory" : "Optional"}</li>
                                     <li><span class="mdi mdi-asterisk"></span> ${field.validation_rules ? "Validation" : "No Validation"}</li>
                                 </ul>
                             </div>
                             <div class="column2">
                                 <ul>
                                     <li style="padding-bottom:30px"><span class="mdi mdi-eye-outline"></span> ${field.is_visible ? "Visible" : "Hidden"}</li>
                                     <li><span class="mdi mdi-alpha-d-circle"></span> Default</li>
                                 </ul>
                             </div>
                             <div class="column3">
                                 <span class="mdi mdi-pencil" onclick="editModel('${field.field_name}','${field.field_type}','${field.section}')"></span>
                             </div>
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
               $('#duplicateFields-list').empty();

               Object.values(result.table_fields).forEach(element => {
                 let formattedText = element.replace(/_/g, ' ') // Replace underscores with spaces
                             .replace(/\b\w/g, char => char.toUpperCase()); // Capitalize first letter of each word
   
                   $('.fieldSelect').append(`<option value='${element}'>${formattedText}</option>`);
                 
                   $('#duplicateFields-list').append(`<option value='${element}'></option>`);
                
               });
   
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
                                                   <input type="text" name="field_label" value="${response['editData'].field_label}" class="form-control">
                                               </div>
   
                                               <!-- Default Value -->
                                               <div class="col-md-6 mb-3">
                                                   <label class="font-weight-bold text-dark">Default Value</label>
                                                   <input type="text" name="default_value" class="form-control"
                                                       ${response['editData'].default_value != null ? `value="${response['editData'].default_value}"` : 'placeholder="Enter default value"'}>
                                               </div>
   
                                               <!-- Validation Rules -->
                                               <div class="col-md-6 mb-3">
                                                   <label class="font-weight-bold text-dark">Validation Rules</label>
                                                   <input type="text" name="validation_rules" class="form-control"
                                                       ${response['editData'].validation_rules != null ? `value="${response['editData'].validation_rules}"` : 'placeholder="Enter Validations like number, email, etc."'} >
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
                                               if (response['editData'].field_type === 'dropdown' || response['editData'].field_type === 'radio') {
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
                                                                 <input type="text" name="option_keys[]" value="${key}" class="form-control">
                                                             </div>
                                                             <div class="col-md-5">
                                                                 <label class="small text-secondary">Display Name</label>
                                                                 <input type="text" name="option_values[]" value="${value}" class="form-control">
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
                                                                 <input type="text" name="option_keys[]" class="form-control">
                                                             </div>
                                                             <div class="col-md-5">
                                                                 <label class="small text-secondary">Display Name</label>
                                                                 <input type="text" name="option_values[]" class="form-control">
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
               <input type="text" name="option_keys[]" class="form-control">
           </div>
           <div class="col-md-5">
               <label>Display Name</label>
               <input type="text" name="option_values[]" class="form-control">
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


   var datalist = $('datalist');
var options = $('datalist option');
var optionsarray = jQuery.map(options ,function(option) {
        return option.value;
});
var input = $('input[list]');
var inputcommas = (input.val().match(/,/g)||[]).length;
var separator = ',';
        
function filldatalist(prefix) {
    if (input.val().indexOf(separator) > -1 && options.length > 0) {
        datalist.empty();
        for (i=0; i < optionsarray.length; i++ ) {
            if (prefix.indexOf(optionsarray[i]) < 0 ) {
                datalist.append('<option value="'+prefix+optionsarray[i]+'">');
            }
        }
    }
}
input.bind("change paste keyup",function() {
    var inputtrim = input.val().replace(/^\s+|\s+$/g, "");
  //console.log(inputtrim);
    var currentcommas = (input.val().match(/,/g)||[]).length;
  //console.log(currentcommas);
    if (inputtrim != input.val()) {
        if (inputcommas != currentcommas) {
            var lsIndex = inputtrim.lastIndexOf(separator);
            var str = (lsIndex != -1) ? inputtrim.substr(0, lsIndex)+", " : "";
            filldatalist(str);
            inputcommas = currentcommas;
        }
        input.val(inputtrim);
    }
});
</script>
@include('includes.footer')
@endsection