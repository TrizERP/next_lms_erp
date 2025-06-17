{{-- @include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation') --}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Student Discipline</h4> 
			</div>
		</div>        
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @if(isset($data['stu_data']))
                    <form action="{{ route('dicipline.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <input type="hidden" name="grade" value="{{ $data['grade']}}">
                        <input type="hidden" name="standard" value="{{ $data['standard']}}">
                        <input type="hidden" name="division" value="{{ $data['division']}}">
                        
                       
                         <div class="table-responsive ">
							<table id="example" class="table table-striped display">
							<thead>
								<tr>
									<th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>
									<th>Sr No</th>
									<th>Student Name</th>
									<th>Standard</th>
									<th>Division</th>
									<th>Mobile</th>
									<th>Select</th>
									<th class="text-left">Message</th>
								</tr>
							</thead>
                            @php
                            $arr = $data['stu_data'];
                            @endphp
                            @foreach ($arr as $id=>$col_arr)                            
                            <tr>

                                <td><input type="checkbox" name="{{ 'values[stud_id]['.$col_arr['student_id'].']'}}" class="ckbox1">  </td>
                                <td>{{ $id+1}}</td>
                                <td>
                                    {{-- $col_arr['name'] --}}
                                    {{App\Helpers\sortStudentName($col_arr['name'])}}
                                </td>
                                <td>{{ $col_arr['standard_name']}}</td>
                                <td>{{ $col_arr['division_name']}}</td>
                                <td>{{ $col_arr['mobile']}}</td>
                                <td>
                                    <select name="{{ 'values[dd]['.$col_arr['student_id'].']'}}"
                                            class="form-control titleSelect">
                                        <option value="">Select</option>
                                        @foreach ($data['dd'] as $id => $name)
                                            <option value="{{ $name}}" data-id="{{$id}}" data-student="{{$col_arr['student_id']}}">{{ $name}}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    @if(in_array(session()->get('sub_institute_id'),[195]))  
                                        <!-- <select class="form-control" name="{{ 'values[text]['.$col_arr['student_id'].']'}}" id="messageSelect-{{$col_arr['student_id']}}">
                                            <option value="">Please Select Title</option>
                                        </select> -->
                                        <input type="text" list="messageSelect-{{$col_arr['student_id']}}" class="form-control interest-input" name="{{ 'values[text]['.$col_arr['student_id'].']'}}" autocomplete="off">
                                        <datalist id="messageSelect-{{$col_arr['student_id']}}"></datalist>
                                    @else 
                                        <textarea name="{{ 'values[text]['.$col_arr['student_id'].']'}}" class="form-control resizableVertical"></textarea>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </table>
						</div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>

                    </form>
                    @else
                    No Student Found.
                    @endif
                </div>
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>        
    </div>
</div>


@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>

<script>
    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });

    @if(in_array(session()->get('sub_institute_id'),[195]))  
    $('.titleSelect').on('change', function () {
        var selectedValue = $(this).val();
        var id = $(this).find(':selected').data('id');
        var student = $(this).find(':selected').data('student');

        $.ajax({
            url: `{{ route('dicipline-Master.show', '') }}/${id}`,
            type: "GET",
            success: function (data) {
            //    alert(data);
                var messageSelect = $('#messageSelect-' + student);
                messageSelect.empty();
                messageSelect.append('<option value="">Select</option>');
                if (data['masterData'] && data['masterData'].length > 0) {
                    $.each(data['masterData'], function (index, value) {
                        // messageSelect.append(`<option value='${value.message}'>${value.message}</option>`);
                        messageSelect.append(`<option value='${value.message}'>${value.message}</option>`);
                            // <label><input type="checkbox" value='${value.message}'>${value.message}</label>
                    });
                } else {
                    messageSelect.append('<option value="">No messages available</option>');
                }
            }
        });
    });
    @endif
    
$(document).ready(function() {
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
                    .search(this.value)
                    .draw();
            }
        });
    });

} );


</script>

@include('includes.footer')
@endsection
