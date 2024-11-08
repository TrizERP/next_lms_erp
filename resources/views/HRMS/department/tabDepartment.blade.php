<script src="{{ asset("/ckeditor_wiris/ckeditor4/ckeditor.js") }}"></script>
@include('HRMS.department.tabModel')
<style>
   .activeTrue{
   background : #dfdddd !important;
   border-radius:10px;
   }
   .showDiv{
   display:block;
   }
   /* .hideDiv{
   /* display:none; */
   table.table-striped.table-hover.dataTable {
   width:100% !important;
   }
   .headHr{
   border-top : 6px solid rgba(0,0,0,.1);
   }
   .control-bar a:hover, .control-bar input:hover, [contenteditable]:focus, [contenteditable]:hover{
   background:none !important;
   }
</style>
<!-- add title and icons for side menus  -->
@php 
$titles = ["Add Department","Manage Employee","Manage Tasks"];
$icons = ["mdi-plus-circle-outline","mdi-account-multiple-plus","mdi-calendar-plus"];
$taskType = ['Daily Task','Weekly Task','Monthly Task','Yearly Task'];
@endphp
<div class="card">
   <center>
      <ul class="nav nav-tabs tab-title subtabs mb-4">
         <li class="nav-item"><a href="#section-dep-0" class="nav-link section-dep-0 active" aria-selected="true" data-toggle="tab"><span>Add Department</span></a></li>
         <li class="nav-item"><a href="#section-dep-1" class="nav-link section-dep-1" aria-selected="false" data-toggle="tab"><span>Manage Employee</span></a></li>
         <li class="nav-item"><a href="#section-dep-2" class="nav-link section-dep-2" aria-selected="false" data-toggle="tab"><span>Manage Tasks</span></a></li>
      </ul>
   </center>
   <div class="tab-content">
      <!-- tab 3 start  -->
      <div class="tab-pane p-3" id="section-dep-2" role="tabpanel">
         <!-- start Manage task  -->
         <div class="main main-2" style="padding:20px">
            <!-- <div class="divHead">
               <h3><b>Manage Task</b></h3>
               </div>
               <hr class="headHr"> -->
            <div class="divBody">
               <form action="{{route('institute_detail.store')}}" method="post" class="row mt-4" enctype="multipart/form-data">
                  @csrf
                  <input type="hidden" name="formName" value="addTask">
                  <div class="row cloneManageTask" id="cloneManageTask" style="padding:6px">
                     <!-- department select  -->
                     <div class="col-md-4 form-group">
                        <label for="selectDepartment">Select Department</label>
                        <select name="arr[0][selDepartment]" id="selDepartment0" class="form-control selDepartment" data-val="0"  required>
                           <option value="">Select Department</option>
                           @foreach($data['departmentData']['departmentList'] as $key=>$value)
                           <option value="{{$value['id']}}">{{$value['department']}}</option>
                           @endforeach
                        </select>
                     </div>
                     <!-- sub department select  -->
                     <div class="col-md-4 form-group">
                        <label for="selectSubDepartment">Select Sub Department</label>
                        <select name="arr[0][selSubDepartment]" id="selSubDepartment0" class="form-control selSubDepartment" data-val="0">
                        </select>
                     </div>
                     <!-- employee select  -->
                     <div class="col-md-4 form-group">
                        <label for="selEmployees">Select Employee</label>
                        <select name="arr[0][TASK_ALLOCATED_TO][]" id="selEmployees0" class="form-control selEmployees" data-val="0" multiple required>
                        </select>
                     </div>
                     <!-- add task  -->
                     <div class="col-md-4  form-group">
                        <label for="task">Task</label>
                        <input type="text" name="arr[0][TASK_TITLE]" id="task0" class="form-control task" data-val="0" required autocomplete="off" >
                     </div>
                     <!-- add task Description -->
                     <div class="col-md-4  form-group">
                        <label for="task">Task Description</label>
                        <textarea name="arr[0][TASK_DESCRIPTION]" id="TASK_DESCRIPTION0" class="form-control" data-val="0"></textarea>
                     </div>
                     <!-- task attachment -->
                     <div class="col-md-4  form-group">
                        <label for="attachment">Attachment</label>
                        <input type="file" name="arr[0][TASK_ATTACHMENT]" id="TASK_ATTACHMENT0" class="form-control" data-val="0" >
                     </div>
                     <!-- skills -->
                     <div class="col-md-4 form-group">
                        <label>Skills </label>
                        {{-- <input type="email" id='skill0' list="dataLists" name="arr[0][skills]" class="form-control skillInput" multiple  autocomplete="off" >
                        <datalist id="dataLists">
                           @foreach($data['skillLists'] as $k=>$val)
                           <option value="{{$val->skills}}"></option>
                           @endforeach
                        </datalist>
                        --}}
                        <select name="arr[0][skills][]" id="skill0" class="form-control" multiple>
                           @foreach($data['skillLists'] as $k=>$val)
                           <option value="{{$val->name}}">{{$val->name}}</option>
                           @endforeach
                        </select>
                     </div>
                     <!-- add KRA -->
                     <div class="col-md-4  form-group">
                        <label for="task">Add KRA</label>
                        <input type="text" name="arr[0][KRA]" id="KRA0" class="form-control" data-val="0" autocomplete="off" >
                     </div>
                     <!--add KPA -->
                     <div class="col-md-4  form-group">
                        <label for="task">Add KPA</label>
                        <input type="text" name="arr[0][KPA]" id="KPA0" class="form-control" data-val="0" autocomplete="off" >
                     </div>
                     <!-- add Type -->
                     <div class="col-md-4  form-group">
                        <label for="task">Add Type</label>
                        <select name="arr[0][selType]" id="selType0" class="form-control selType" data-val="0" >
                           <option value="">Select Type</option>
                           @foreach($taskType as $key=>$value)
                           <option value="{{$value}}">{{$value}}</option>
                           @endforeach
                        </select>
                     </div>
                     <!-- add manageby -->
                     <div class="col-md-4  form-group">
                        <label for="manageby">Monitoring / Observation by</label>
                        <select name="arr[0][manageby]" id="manageby0" class="form-control manageby" data-val="0" required>
                           <option value="">Select Monitoring / Observation by</option>
                           @foreach($data['taskManagerLists'] as $key=>$value)
                           <option value="{{$value->id}}">{{$value->name}}</option>
                           @endforeach
                        </select>
                     </div>
                     <!-- add manageby -->
                     <div class="col-md-4  form-group">
                        <label for="manageby">Monitoring / Observation Points</label>
                        <textarea name="arr[0][observation_point]" id="observation_point0" class="form-control"></textarea>
                     </div>
                     <hr style="border:1px solid #ddd;width:100%">
                  </div>
                  <div class="row" id="pasteManageTask" style="padding:6px;margin:0px">
                  </div>
                  <!-- submit button  -->
                  <div class="col-md-12">
                     <center>
                        <input type="submit" value="Assign Task" name="add" class="btn btn-primary ml-2">
                        <a id="cloneTaskButton" class="btn btn-success"><span class="mdi mdi-plus" style="color:#fff"></span></a>
                     </center>
                  </div>
               </form>
            </div>
         </div>
         <!-- Manage task end  -->   
      </div>
      <!-- tab 3 ends  -->
      <!-- tab 1  -->
      <div class="tab-pane p-3 active" id="section-dep-0" role="tabpanel">
         <!-- add department -->
         <div class="main main-0">
            <!-- <div class="divHead">
               <h3><b>Add Department</b></h3>
               </div>
               <hr class="headHr"> -->
            <div class="divBody">
               <form action="{{route('institute_detail.store')}}" method="post" class="row mt-4">
                  @csrf
                  <input type="hidden" name="formName" value="addDepartment">
                  <div class="col-md-4 form-group">
                     <label for="deparment_title">Department Name</label>
                     <input type="text" name="department_name" id="department_name" placeholder="Department Name" required class="form-control" autocomplete="off">
                  </div>
                  <!-- for sub department  -->
                  <div class="col-md-4 form-group">
                     <input type="checkbox" id="is_subDepartment" name="is_subDepartment">
                     <label for="deparment_title">Is Sub Department ?</label>
                     <select name="parentDiv" id="hideDep" class="form-control">
                        <option value="">Select Department</option>
                        @foreach($data['departmentData']['departmentList'] as $key=>$value)
                        <option value="{{$value['id']}}">{{$value['department']}}</option>
                        @endforeach
                     </select>
                  </div>
                  <div class="col-md-4 form-group">
                     <label class="control-label">Calculate PF/PT</label>
                     <div class="radio-list">
                        <label class="radio-inline p-0">
                           <div class="radio radio-success">
                              <input type="radio" checked="" name="is_calculated" id="calculate" value="0" required>
                        <label for="calculate">Calculate</label>
                        </div>
                        </label>
                        <label class="radio-inline">
                           <div class="radio radio-success">
                              <input type="radio" name="is_calculated" id="not_calculate" value="1" required>
                        <label for="not_calculate">Not Calculate</label>
                        </div>
                        </label>
                     </div>
                  </div>
                  <div class="col-md-12 form-group">
                     <label for="roles_responsibility">Aims & Objectives</label>
                     <!-- <textarea name="roles_responsibility" id="roles_responsibility" class="form-control"  style="height:10vh"></textarea> -->
                     <textarea name="roles_responsibility" id="roles_responsibility" contenteditable="true"></textarea>
                  </div>
                  <div class="col-md-12">
                     <center>
                        <input type="submit" name="add" Value="Add Department" class="btn btn-primary">
                     </center>
                  </div>
               </form>
               <!-- Sub viewLists   -->
               <div class="viewList mt-6">
                  <div id="accordion">
                     <div class="card border-none">
                        <div class="card-header bg-white" id="subDepartmentAccordation">
                           <button class="btn btn-outline-info collapsed" data-toggle="collapse" data-target="#subDepartmentCollapse" aria-controls="collapseTwo">
                           View Added Departments
                           </button>
                        </div>
                        <div id="subDepartmentCollapse" class="collapse" aria-labelledby="subDepartmentAccordation" data-parent="#accordion">
                           <div class="card-body table-responsive mt-20 tz-report-table">
                              <table id="subDepartmentTable" class="table table-striped" style="width:100% !important">
                                 <thead>
                                    <tr>
                                       <th>Sr No.</th>
                                       <th>Department</th>
                                       <th>Main Department</th>
                                       <th><span class="mdi mdi-account-multiple" style="font-size:1.5rem"></span></th>
                                       <th>Aims & Objectives</th>
                                       <th class="text-left">Action</th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    @foreach($data['departmentData']['SubDepartmentList'] as $key=>$value)
                                    <tr>
                                       <td>{{$key+1}}</td>
                                       <td>{{$value['department']}}</td>
                                       <td>{{$value['mainDepartment']}}</td>
                                       <td><a onclick="getEmpModel('{{$value['emp_ids']}}','{{$value['department']}}','sub department')">{{$value['total_emp']}}</a></td>
                                       <td>{!! substr($value['roles_responsibility'],0,200) !!}....</td>
                                       <td class="text-left">
                                          <div class="d-inline">
                                             @if($value['sub_institute_id']!=0 && $value['total_subDep']==0)
                                             <a data-toggle="modal" data-target="#departmentEdit{{$value['id']}}" class="btn btn-info btn-outline">
                                             <i class="ti-pencil-alt"></i>
                                             </a>
                                          </div>
                                          <!-- can not delete if sub_institute and total employee > 0 -->
                                          @if($value['total_emp']==0  && $value['total_subDep']==0) 
                                          <form action="{{ route('institute_detail.destroy', $value['id'])}}" method="post" class="d-inline">
                                             @csrf
                                             @method('DELETE')
                                             <input type="hidden" name="formName" value="addDepartment">
                                             <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger">
                                             <i class="ti-trash"></i>
                                             </button>
                                          </form>
                                          @endif
                                          @else 
                                          -
                                          @endif
                                       </td>
                                    </tr>
                                    @endforeach
                                 </tbody>
                              </table>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <!-- viewlist end  -->
            </div>
         </div>
         <!-- add department end -->
      </div>
      <!-- tab 2 start  -->
      <div class="tab-pane p-3" id="section-dep-1" role="tabpanel">
         <!-- Manage emp start -->
         <div class="main main-1" style="padding:20px">
            <!-- <div class="divHead">
               <h3><b>Manage Employee</b></h3>
               </div>
               <hr class="headHr"> -->
            <div class="divBody">
               <div class="table-responsive mt-20 tz-report-table">
                  <table id="example" class="table table-striped" style="width:100% !important">
                  <thead>
                     <tr>
                        <th>Sr No.</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th class="text-left">Action</th>
                     </tr>
                  </thead>
                  <tbody>
                     @foreach($data['departmentData']['employeesList'] as $key=>$value)
                     <tr>
                        <td>{{$key+1}}</td>
                        <td>{{$value['emp_name']}}</td>
                        <td>{{$value['emp_department']}}</td>
                        <td>{{$value['user_role']}}</td>
                        <td>
                           <div class="d-inline">
                              <a href="{{ route('add_user.edit',$value['emp_id'])}}" class="btn btn-info btn-outline" target="_blank"><i class="ti-pencil-alt"></i></a>
                           </div>
                        </td>
                     </tr>
                     @endforeach
                  </tbody>
                </table>
               </div>
               <!-- end table -->
            </div>
         </div>
         <!-- Manage Emp end -->
      </div>
      <!-- tab 2 end  -->
   </div>
   <!-- tab content end  -->
</div>
<script>
   CKEDITOR.config.toolbar_Full = [
     { name: 'document', items: ['Source'] },
     { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', '-', 'Undo', 'Redo'] },
     { name: 'editing', items: ['Find'] },
     { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
     { name: 'paragraph', items: ['JustifyLeft', 'JustifyCenter', 'JustifyRight'] }
   ];
   CKEDITOR.config.height = '40px';
   
   CKEDITOR.plugins.addExternal('divarea', '../examples/extraplugins/divarea/', 'plugin.js');
   CKEDITOR.plugins.addExternal('sharedspace', '../examples/extraplugins/sharedspace/', 'plugin.js');
   CKEDITOR.plugins.addExternal('filebrowser', '../examples/extraplugins/filebrowser/', 'plugin.js');
   CKEDITOR.plugins.addExternal('enterkey', '../examples/extraplugins/enterkey/', 'plugin.js');
   CKEDITOR.plugins.addExternal('FMathEditor', '../examples/extraplugins/FMathEditor/', 'plugin.js');
   CKEDITOR.config.removePlugins = 'maximize,resize';
   CKEDITOR.config.sharedSpaces = { top: 'toolbar1' };
   
   CKEDITOR.replace('roles_responsibility', {
     extraPlugins: 'filebrowser,divarea,sharedspace,FMathEditor,enterkey',
     enterMode: '2',
     language: 'en',
     filebrowserUploadUrl: "{{route('uploadimage',['_token' => csrf_token() ])}}",
     filebrowserUploadMethod: 'form'
   });
   @foreach($data['departmentData']['SubDepartmentList'] as $key=>$value)
   
   CKEDITOR.replace('roles_responsibility_{{$value["id"]}}', {
    extraPlugins: 'filebrowser,divarea,sharedspace,FMathEditor,enterkey',
    enterMode: '2',
    language: 'en',
    filebrowserUploadUrl: "{{route('uploadimage',['_token' => csrf_token() ])}}",
    filebrowserUploadMethod: 'form'
   });
   @endforeach
</script>
<script>
   $(document).ready(function () {
       function initializeDataTable(tableId, reportName) {
           var table = $(tableId).DataTable({
               select: true,
               lengthMenu: [
                   [100, 500, 1000, -1],
                   ['100', '500', '1000', 'Show All']
               ],
               dom: 'Bfrtip',
               buttons: [
                   {
                       extend: 'pdfHtml5',
                       title: reportName + 'Report',
                       orientation: 'landscape',
                       pageSize: 'A0', // This line should be after 'LEGAL' to override it
                       exportOptions: {
                           columns: ':visible'
                       },
                   },
                   { extend: 'csv', text: ' CSV', title: reportName + 'Report' },
                   { extend: 'excel', text: ' EXCEL', title: reportName + 'Report' },
                   {
                       extend: 'print',
                       text: ' PRINT',
                       title: reportName + 'Report',
                       customize: function (win) {
                           var lastColumnIndex = table.columns(':visible').indexes().length - 1;
                           $(win.document.body).find('table').each(function () {
                               $(this).find('tr').each(function () {
                                   $(this).find('th').eq(lastColumnIndex).remove();
                                   $(this).find('td').eq(lastColumnIndex).remove();
                               });
                           });
                       }
                   },
                   'pageLength',
                 
               ],
           });
   
           // Clone header row for filtering
           $(tableId + ' thead tr').clone(true).appendTo(tableId + ' thead');
           $(tableId + ' thead tr:eq(1) th').each(function (i) {
               var title = $(this).text();
               $(this).html('<input type="text" placeholder="Search ' + title + '" />');
   
               $('input', this).on('keyup change', function () {
                   if (table.column(i).search() !== this.value) {
                       table
                           .column(i)
                           .search(this.value)
                           .draw();
                   }
               });
           });
           
       }
   
       // Initialize all tables
       initializeDataTable('#departmentTable', 'Department');
       initializeDataTable('#subDepartmentTable', 'Sub Department');
       initializeDataTable('#example', 'Employee');
   });
   
   $(document).ready(function(){
       // $('.taskDate').hide();
       $('#emailForm').on('submit', function(event) {
           event.preventDefault(); // Prevent the default form submission
   
           let isSelected = false;
           $('.selEmployees').each(function() {
               if ($(this).val() !== '' && $(this).val() !== null) {
                   isSelected = true;
               }
           });
   
           if (!isSelected) {
               alert('Please select at least one employee');
               return false;
           }
   
           let isTask = false;
           $('.task').each(function() {
               if ($(this).val() !== '' && $(this).val() !== null) {
                   isTask = true;
               }
           });
   
           if (!isTask) {
               alert('Please Enter task name');
               return false;
           }
   
           if (validateEmail()) {
               this.submit(); // Submit the form programmatically if validation passes
           }
       });
   
       $('#hideDep').hide();
       // checkbox checked
       $('#is_subDepartment').on('change',function(){
         if ($(this).is(':checked')) {
               $('#hideDep').show();
           } else {
               $('#hideDep').hide();
           }
       })
       
      //  updateCard(0);
   
       $('.selDepartment').on('change',function(){
           var depId = $(this).val();
           var dataVal = $(this).attr('data-val');
           // get sub dep 
           $('#selSubDepartment'+dataVal).empty();  
          $.ajax({
           url : "{{route('subDepartmentList')}}",
           data : {depId : depId},
           type:"GET",
           success : function(response){
               if (Array.isArray(response)) {
                   // Iterate over the array and create table rows
                 $('#selSubDepartment'+dataVal).append(`<option value="">Select Sub Department</option>`)
                   response.forEach(function (department,index) {
                       $('#selSubDepartment'+dataVal).append(`<option value="${department.id}">${department.department}</option>`);
                   });
               } else {
                   console.error('Response is not an array for sub dep');
               }
           } 
          })
       //    get emp 
       $('#selEmployees'+dataVal).empty(); 
          $.ajax({
           url : "{{route('departmentEmployeeList')}}",
           data : {depId : depId},
           type:"GET",
           success : function(response){
               if (Array.isArray(response)) {
                   // Iterate over the array and create table rows
                   response.forEach(function (employee,index) {
                       $('#selEmployees'+dataVal).append(`<option value="${employee.id}">${employee.name}</option>`);
                   });
               } else {
                   console.error('Response is not an array for sub dep');
               }
           }
          })
       })
       // sub dep 
       $('.selSubDepartment').on('change',function(){
           var dataVal = $(this).attr('data-val');
           var depId = $('#selDepartment'+dataVal).val();
           var subDepId = $('#selSubDepartment'+dataVal).val();
           
         // get emp 
         $('#selEmployees'+dataVal).empty();
           $.ajax({
             url : "{{route('departmentEmployeeList')}}",
             data : {depId : depId,subDepId:subDepId},
             type:"GET",
             success : function(response){
                 if (Array.isArray(response)) {
   
                     // Iterate over the array and create table rows
                     response.forEach(function (employee,index) {
                         $('#selEmployees'+dataVal).append(`<option value="${employee.id}">${employee.name}</option>`);
                     });
                 } else {
                     console.error('Response is not an array for sub dep');
                 }
             }
           })
       })
   
       // get date field 
       // $('.selType').on('change',function(){
       //   var dataVal = $(this).attr('data-val');
       //     if($(this).val()=="Day-wise"){
       //         $('#taskDate'+dataVal).show();
       //     }else{
       //         $('#taskDate'+dataVal).hide();
       //     }
       // })
       
   });
   
   //  function updateCard(divNo) {
   //      $('.activeCard').removeClass('activeTrue');
   //      $('.main').removeClass('showDiv').addClass('hideDiv');
   
   //      $('.active-' + divNo).addClass('activeTrue');
   //      $('.main-' + divNo).removeClass('hideDiv').addClass('showDiv');
   //  }
   
   //  function activeCard(divNo){
   //      updateCard(divNo);
   //  }
   
   
     function getEmpModel(emp_ids,dep_name,type){
       $('#empModelBody').empty();
       $.ajax({
         url : '{{route("departmentEmpLists")}}',
         data :{emp_ids:emp_ids},
         type : 'GET',
         success : function (response)
         {
           if (Array.isArray(response)) {
               var tableBody = $('#empModelBody');
               tableBody.empty(); // Clear any existing content
   
               // Iterate over the array and create table rows
               response.forEach(function (employee,index) {
                   var row = $('<tr></tr>');
                   row.append('<td>' + (index+1) + '</td>');
                   row.append('<td>' + employee.name + '</td>');
                   row.append('<td>' + employee.mobile + '</td>');
                   row.append('<td>' + dep_name + '</td>');
                   tableBody.append(row);
               });
   
               // Show the modal
               $('#empDataModal').modal('show');
           } else {
               console.error('Response is not an array');
           }
         } 
      })
     }
     // clone tasks 
     $(document).ready(function(){
       let cloneIndex = 1; // Initial clone index
   
       function cloneManageTask() {
           // Calculate the highest existing index
           let highestIndex = 0;
           $('div.cloneManageTask').each(function() {
               let currentName = $(this).find('select, input, textarea').first().attr('name');
               if (currentName) {
                   let matches = currentName.match(/\[([0-9]+)\]/);
                   if (matches) {
                       let currentIndex = parseInt(matches[1]);
                       if (currentIndex > highestIndex) {
                           highestIndex = currentIndex;
                       }
                   }
               }
           });
   
           let newIndex = highestIndex + 1; // New index for the clone
   
           // Clone the div and update its id and attributes
           let $clone = $('#cloneManageTask').clone(true);
           $clone.removeAttr('id').attr('id', 'cloneManageTask' + newIndex);
           //  change form-control  name
           $clone.find('input, select, textarea').each(function(){
               let name = $(this).attr('name');
               if (name) {
                   name = name.replace(/\[\d+\]/, '[' + newIndex + ']');
                   $(this).attr('name', name);
               }
               $(this).val('');
               $(this).attr('data-val', newIndex);
           });
           //  change form-control id
           $clone.find('select, input, textarea').each(function() {
               let id = $(this).attr('id');
               if (id) {
                   id = id.replace(/\d+$/, newIndex);
                   $(this).attr('id', id);
               }            
           });
           $clone.find('.hasDatepicker').removeClass('hasDatepicker').removeAttr('id');
           $clone.appendTo('#pasteManageTask');
       }
   
       $('#cloneTaskButton').click(function(e) {
           e.preventDefault();
           cloneManageTask();
       });
   
   });
   
   // for data list 
   function validateEmail() {
     const emailInput = $('.skillInput');
       const emailValue = emailInput.val();
       const atSymbolIndex = emailValue.indexOf('@');
   
       if (atSymbolIndex !== -1) {
           emailInput.addClass('is-invalid');
           return false;
       } else {
           emailInput.removeClass('is-invalid');
           return true;
       }
     }
</script>