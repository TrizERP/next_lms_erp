@extends('layout')
@section('container')
    <link rel="stylesheet" href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css">

    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Shift Update</h4>
                </div>
            </div>

            <div class="card" id="shiftForm">
                @if ($sessionData = Session::get('data'))
                    @if ($sessionData['status'] == 1)
                        <div class="alert alert-success alert-block">
                        @else
                            <div class="alert alert-danger alert-block">
                    @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            @php
                $dep_ids = $emp_ids = '';
                if(isset($data['department_id'])){
                    $dep_ids = $data['department_id'];
                }

                if(isset($data['selEmp'])){
                    $emp_ids = $data['selEmp'];
                }
            @endphp
            <form action="{{ route('user_bulk_shift_update.create') }}">
                @csrf
                <div id="shiftRows">
                    <div class="row mt-2 cloneRaw">
                       {!! App\Helpers\HrmsDepartments("4","multiple",$dep_ids,"multiple",$emp_ids,'') !!}
                       <div class="col-md-4">
                            <label for="">Select Shift</label>
                            <select name="shift_id" id="shift_id" class="form-control" required>
                                <option value="">select shift</option>
                                @foreach($data['shift_data'] as $key=>$value)
                                    @php 
                                        $start_time = \Carbon\Carbon::parse($value['start_time'])->format('h:i A');
                                        $end_time = \Carbon\Carbon::parse($value['end_time'])->format('h:i A');
                                        $selected = '';
                                        if(isset($data['shift_id']) && $data['shift_id'] == $value['id']){
                                            $selected = 'selected';
                                        }
                                    @endphp
                                    <option value="{{$value['id']}}" {{$selected}}>{{$value['shift_name']}} ({{$start_time.' - '.$end_time}}) </option>
                                @endforeach
                            </select>
                       </div>
                    </div>
                </div>
                <div class="col-md-12 mt-2">
                    <center>
                        <button type="submit" id="submitBtn" class="btn btn-primary">Submit</button>
                        <button type="button" id="cancelEdit" class="btn btn-secondary" style="display:none;">Cancel</button>
                    </center>
                </div>
            </form>
        </div>

        @if (isset($data['employeeLists']) && !empty($data['employeeLists']))
            <div class="card">
                <form action="{{route('user_bulk_shift_update.store')}}" method="post" onsubmit="CheckSelection();">
                    @csrf
                    <div class="timeData">
                        @php 
                            $start_time = isset($data['shiftData']->start_time) ? \Carbon\Carbon::parse($data['shiftData']->start_time)->format('h:i A') : '-';
                            $end_time = isset($data['shiftData']->end_time) ? \Carbon\Carbon::parse($data['shiftData']->end_time)->format('h:i A') : '-';
                        @endphp
                        <center>
                            <p><b>Start Time: </b>{{$start_time}} - <b>End Time: </b>{{$end_time}}</p>

                            <input type="hidden" name="start_time" value="{{ ($data['shiftData']->start_time) ? $data['shiftData']->start_time : '-'}}">

                            <input type="hidden" name="end_time" value="{{ ($data['shiftData']->end_time) ? $data['shiftData']->end_time : '-'}}">
                        </center>
                    </div>
                    <div class="table-responsive mt-20 tz-report-table">
                        <table id="example2" class="table table-striped">
                            <thead>
                                <tr>
                                    <th> <input id="checkall" onchange="checkAll(this);" type="checkbox"> Sr No. </th>
                                    <th>Department</th>
                                    <th>Employee No</th>
                                    <th class="text-left">Employee Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['employeeLists'] as $key => $value)
                                    <tr>
                                        <td> <input id="{{$value['id']}}" value="{{$value['id']}}" name="employees[]" type="checkbox"> {{ $key + 1 }}  </td>
                                        <td>{{ $value['department'] }}</td>
                                        <td>{{ $value['employee_no'] }}</td>
                                        <td>{{ $value['full_name'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <center>
                                <input type="submit" value="Update" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

    @include('includes.footerJs')
    <script>
        $(document).ready(function() {
          var table = $('#example2').DataTable({
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdfHtml5',
                        title: 'Employee Leave Allocation Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Employee Leave Allocation Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Employee Leave Allocation Report'},
                    {extend: 'print', text: ' PRINT', title: 'Employee Leave Allocation Report'},
                    'pageLength'
                ],
            });
            //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

            $('#example2 thead tr').clone(true).appendTo('#example2 thead');
            $('#example2 thead tr:eq(1) th').each(function (i) {
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
        });
        function checkAll(ele) {
	     var checkboxes = document.getElementsByTagName('input');
	     if (ele.checked) {
	         for (var i = 0; i < checkboxes.length; i++) {
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = true;
	             }
	         }
	     } else {
	         for (var i = 0; i < checkboxes.length; i++) {
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = false;
	             }
	         }
	     }
	}

    function CheckSelection(){
        var checked_questions = err = 0;

		$("input[name='employees[]']:checked").each(function() {
		checked_questions = checked_questions + 1;
		});

		if (checked_questions == 0) {
		alert("Please Select Atleast one Student from search");
		err = 1;
		}

		if (err == 1) {
		event.preventDefault();
		}

		return err;
    }
    </script>
    @include('includes.footer')
@endsection