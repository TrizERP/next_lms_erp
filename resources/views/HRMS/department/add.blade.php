@extends('lmslayout')
@section('container')
@include('HRMS.department.model')

<style>
  .activeTrue{
    background : #f2f2f2 !important;
    border-radius:10px;
  }
  .showDiv{
    display:block;
  }
  .hideDiv{
    display:none;
  }
  table.table-striped.table-hover.dataTable {
    width:100% !important;
  }
  hr{
    border-top : 6px solid rgba(0,0,0,.1);
  }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-8 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Department Setting</h4>
            </div>
        </div>
        <!-- message  -->
        <div class="card" style="background:transparent !important;border:none !important">
        @if ($sessionData = Session::get('data')) @if($sessionData['status_code'] == 1)
        <div class="alert alert-success alert-block">
          @else
          <div class="alert alert-danger alert-block">
            @endif
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $sessionData['message'] }}</strong>
          </div>
          @endif
        <!-- add title and icons for side menus  -->
        @php 
        $titles = ["Department","Sub Department","Manage Employee"];
        $icons = ["mdi-plus-circle-outline","mdi-plus-circle-multiple-outline","mdi-account-multiple-plus"];
        @endphp
        <!-- make container for details -->
        <div class="row">
          <!-- side div -->
            <div class="col-md-3 mr-2">
              <div class="card row" style="border-radius:20px">
                  <div class="col-md-12 mb-2">

                    <!-- start titles-->
                    @foreach($titles as $key=>$title)
                    <div class="card activeCard active-{{$key}} border-none" style="margin-top:10px !important;" onclick="activeCard({{$key}})">
                      <div class="row" style="padding:8px;align-items:center">
                        <div class="col-md-2">
                            <span class="mdi {{$icons[$key]}}" style="font-size:30px"></span>
                        </div>
                        <div class="col-md-10">
                          <h4 style="padding:10px;marign:0 !important"><b>{{$title}}</b></h4>
                        </div>
                      </div>
                    </div>
                    @endforeach
                    <!-- end title-->

                  </div>
              </div>
            </div>
          <!-- side div ends  -->

          <!-- content div  -->
            <div class="col-md-8">
              <div class="card" style="width:100%;border-radius:20px">
                <!-- add department start -->
                  <div class="main main-0" style="padding:20px">
                    <div class="divHead">
                      <h3><b>Add Department</b></h3>
                    </div>
                    <hr>
                    <div class="divBody">
                      <form action="{{route('add_department.store')}}" method="post" class="row mt-4">
                        @csrf
                          <div class="col-md-4 form-group">
                            <label for="deparment_title">Department Name</label>
                            <input type="text" name="department_name" id="department_name" placeholder="Department Name" required class="form-control" autocomplete="off">
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
                            <label for="tasks">Department Tasks</label>
                            <input type="text" name="tasks" id="tasks" class="form-control" placeholder="Department Tasks" autocomplete="off" >
                          </div>

                          <div class="col-md-12 form-group">
                            <label for="roles_responsibility">Aims & Objectives</label>
                            <textarea name="roles_responsibility" id="roles_responsibility" class="form-control"  style="height:10vh"></textarea>
                          </div>

                          <div class="col-md-12">
                            <center>
                              <input type="submit" name="add" Value="Add Department" class="btn btn-primary">
                            </center>
                          </div>
                      </form>
                      <!-- .viewLists   -->
                      <div class="viewList mt-6">
                        <div id="accordion">
                          <div class="card border-none">
                            <div class="card-header bg-white" id="departmentAccordation">
                                <button class="btn btn-outline-info collapsed" data-toggle="collapse" data-target="#departmentCollapse" aria-expanded="false" aria-controls="collapseTwo">
                                  View Departments
                                </button>
                            </div>
                            <div id="departmentCollapse" class="collapse" aria-labelledby="departmentAccordation" data-parent="#accordion">
                              <div class="card-body table-responsive mt-20 tz-report-table">
                              <table id="departmentTable" class="table table-striped" style="width:100% !important">
                                <thead>
                                  <tr>
                                    <th>Sr No.</th>
                                    <th>Department</th>
                                    <th><span class="mdi mdi-account-multiple" style="font-size:1.5rem"></span></th>
                                    <th>Department Tasks</th>
                                    <th class="text-left">Aims & Objectives</th>
                                    <th>Action</th>
                                  </tr>
                                </thead>
                                <tbody>
                                 @foreach($data['userDepartmentList'] as $key=>$value)
                                 <tr>
                                    <td>{{$key+1}}</td>
                                    <td>{{$value->department}}</td>
                                    <td  onclick="getEmpModel('{{$value->emp_ids}}','{{$value->department}}','department')">{{$value->total_emp}}</td>
                                    <td>{{$value->tasks}}</td>
                                    <td>{{$value->roles_responsibility}}</td>
                                    <td class="text-left">
                                    @if($value->sub_institute_id!=0)
                                        <div class="d-inline">
                                          <a data-toggle="modal" data-target="#departmentEdit{{$value->id}}" class="btn btn-info btn-outline">
                                              <i class="ti-pencil-alt"></i>
                                          </a>
                                        </div> 
                                        <!-- can not delete if sub_institute and total employee and sub dep id > 0 -->
                                        @if($value->total_emp==0 && $value->sub_dep==0)
                                        <form action="{{ route('add_department.destroy', $value->id)}}" method="post" class="d-inline">
                                          @csrf
                                          @method('DELETE')
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
                <!-- Sub Department start -->
                  <div class="main main-1" style="padding:20px">
                     <div class="divHead">
                      <h3><b>Add Sub Department</b></h3>
                    </div>
                    <hr>
                    <div class="divBody">
                    <form action="{{route('add_department.store')}}" method="post" class="row mt-4">
                        @csrf
                          <div class="col-md-3 form-group">
                            <label for="parent_id">Select Department</label>
                            <select name="parentDiv" id="parentDiv" class="form-control" required>
                              @foreach($data['departmentList'] as $key=>$value)
                              <option value="{{$value->id}}">{{$value->department}}</option>
                              @endforeach
                            </select>
                          </div>

                          <div class="col-md-3 form-group">
                            <label for="deparment_title">Sub Department Name</label>
                            <input type="text" name="department_name" id="department_name" placeholder="Sub Department Name" required class="form-control" autocomplete="off">
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
                            <label for="tasks">Department Tasks</label>
                            <input type="text" name="tasks" id="tasks" class="form-control" placeholder="Department Tasks">
                          </div>

                          <div class="col-md-12 form-group">
                            <label for="roles_responsibility">Aims & Objectives</label>
                            <textarea name="roles_responsibility" id="roles_responsibility" class="form-control" style="height:10vh"></textarea>
                          </div>

                          <div class="col-md-12">
                            <center>
                              <input type="submit" name="add" Value="Add Sub Department" class="btn btn-primary">
                            </center>
                          </div>
                      </form>
                      <!-- .viewLists   -->
                      <div class="viewList mt-6">
                        <div id="accordion">
                          <div class="card border-none">
                            <div class="card-header bg-white" id="subDepartmentAccordation">
                                <button class="btn btn-outline-info collapsed" data-toggle="collapse" data-target="#subDepartmentCollapse" aria-expanded="false" aria-controls="collapseTwo">
                                  View Sub Departments
                                </button>
                            </div>
                            <div id="subDepartmentCollapse" class="collapse" aria-labelledby="subDepartmentAccordation" data-parent="#accordion">
                              <div class="card-body table-responsive mt-20 tz-report-table">
                              <table id="subDepartmentTable" class="table table-striped" style="width:100% !important">
                                <thead>
                                  <tr>
                                    <th>Sr No.</th>
                                    <th>Department</th>
                                    <th>Sub Department</th>
                                    <th><span class="mdi mdi-account-multiple" style="font-size:1.5rem"></span></th>
                                    <th>Sub Department Tasks</th>
                                    <th>Aims & Objectives</th>
                                    <th class="text-left">Action</th>
                                  </tr>
                                </thead>
                                <tbody>
                                 @foreach($data['SubDepartmentList'] as $key=>$value)
                                 <tr>
                                    <td>{{$key+1}}</td>
                                    <td>{{$value->mainDepartment}}</td>
                                    <td>{{$value->department}}</td>
                                    <td onclick="getEmpModel('{{$value->emp_ids}}','{{$value->department}}','sub department')">{{$value->total_emp}}</td>
                                    <td>{{$value->tasks}}</td>
                                    <td>{{$value->roles_responsibility}}</td>
                                    <td class="text-left">
                                      <div class="d-inline">
                                        @if($value->sub_institute_id!=0)
                                          <a data-toggle="modal" data-target="#subDepartmentEdit{{$value->id}}" class="btn btn-info btn-outline">
                                              <i class="ti-pencil-alt"></i>
                                          </a>
                                        </div>
                                        <!-- can not delete if sub_institute and total employee > 0 -->
                                        @if($value->total_emp==0) 
                                        <form action="{{ route('add_department.destroy', $value->id)}}" method="post" class="d-inline">
                                          @csrf
                                          @method('DELETE')
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
                <!-- Sub Department end -->
                <!-- Manage employee start -->
                  <div class="main main-2" style="padding:20px">
                     <div class="divHead">
                      <h3><b>Manage Employee</b></h3>
                    </div>
                    <hr>
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
                              @foreach($data['employeesList'] as $key=>$value)
                              <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value->emp_name}}</td>
                                <td>{{$value->emp_department}}</td>
                                <td>{{$value->user_role}}</td>
                                <td>
                                  <div class="d-inline">
                                      <a href="{{ route('add_user.edit',$value->emp_id)}}" class="btn btn-info btn-outline" target="_blank"><i class="ti-pencil-alt"></i></a>
                                  </div>
                                </td>
                              </tr>
                              @endforeach
                            </tbody>
                          <table>
                        </div>
                      <!-- end table -->
                    </div>
                  </div>
                <!-- Manage employee end -->
              </div>
            </div>
          <!-- content div end -->
        </div>
        <!-- end container  -->
      
        <!-- container end  -->
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script>
$(document).ready(function () {
    function initializeDataTable(tableId, reportName) {
        var table = $(tableId).DataTable({
            select: true,
            lengthMenu: [
                [50, 100, 500, 1000, -1],
                ['50', '100', '500', '1000', 'Show All']
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
    $('.active-0').addClass('activeTrue');
    $('.main').addClass('hideDiv');
    $('.main-0').removeClass('hideDiv');
    $('.main-0').addClass('showDiv');
  })

  function activeCard(divNo){
    $('.activeCard').removeClass('activeTrue');
    $('.main').removeClass('showDiv');
    $('.main').addClass('hideDiv');

    $('.active-'+divNo).addClass('activeTrue');
    $('.main-'+divNo).removeClass('hideDiv');
    $('.main-'+divNo).addClass('showDiv');
  }

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
</script>
@include('includes.footer')
@endsection
