{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Status Report</h4> </div>
            </div>
        @php
        $grade_id = $standard_id = $division_id = $enrollment_no = $receipt_no = $from_date = $to_date = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
            if(isset($data['enrollment_no']))
            {
                $enrollment_no = $data['enrollment_no'];
            }
            if(isset($data['receipt_no']))
            {
                $receipt_no = $data['receipt_no'];
            }
            if(isset($data['from_date']))
            {
                $from_date = $data['from_date'];
            }
            if(isset($data['to_date']))
            {
                $to_date = $data['to_date'];
            }
        @endphp


                <div class="card">
                    @if ($sessionData = Session::get('data'))
                    @if($sessionData['status_code'] == 1)
                    <div class="alert alert-success alert-block">
                    @else
                    <div class="alert alert-danger alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('show_fees_status_report') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf

                        <div class="row">

                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                            @if(isset($data['months']))
                                <div class="col-md-6 form-group">
                                    <label>Months:</label>
                                    <select name="month[]" class="form-control" required="required" multiple="multiple">
                                        @foreach($data['months'] as $key => $value)
                                            <option value="{{$key}}" @if(isset($data['month']))
                                            @if(in_array($key,$data['month']))
                                                SELECTED
                                            @endif
                                            @endif>{{$value}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if(isset($data['fees_heads']))
                                <div class="col-md-6 form-group">
                                    <label>Fees Heads:</label>
                                    <select name="fees_head[]" class="form-control" required="required" multiple="multiple">
                                        @foreach($data['fees_heads'] as $key => $value)
                                            <option value="{{$key}}" @if(isset($data['fees_head']))
                                            @if(in_array($key,$data['fees_head']))
                                                SELECTED
                                            @endif
                                            @endif>{{$value}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        @if(isset($data['fees_data']))
        @php
            if(isset($data['fees_data'])){
                $fees_data = $data['fees_data'];
            }
        @endphp

                <div class="card">

	                    <div class="col-lg-12 col-sm-12 col-xs-12">
		                    <div class="table-responsive">
		                        <table id="example" class="table table-striped">
		                            <thead>
		                                <tr>
                                            <th><input type="checkbox" id="fees_check_all"/></th>
		                                    <th>Sr No.</th>
		                                    <th>{{ App\Helpers\get_string('grno','request')}}</th>
		                                    <th>{{ App\Helpers\get_string('studentname','request')}}</th>
		                                    <th>{{ App\Helpers\get_string('standard','request')}}</th>
		                                    <th>{{ App\Helpers\get_string('division','request')}}</th>
                                            @foreach($data['fees_head'] as $dk => $dv)
                                                <th>{{$data['fees_heads'][$dv]}}</th>
                                            @endforeach
                                            <th>Amount</th>
		                                </tr>
		                            </thead>
		                            <tbody>
		                            @php
		                            $j=1;
		                            @endphp
		                            @if(isset($data['fees_data']))
		                                @foreach($fees_data as $key => $value)
                                        @php
                                        $amount = 0;
                                        @endphp

                                         @foreach($data['fees_head'] as $dk => $dv)
                                            @if(isset($data['fees_details'][$value['id']][$data['fees_heads'][$dv]]))
                                                @php $amount += $data['fees_details'][$value['id']][$data['fees_heads'][$dv]]; @endphp
                                            @endif
                                        @endforeach
                                        @if($amount)
		                                <tr>
                                            <td>
                                                <input type='checkbox' name="stu_ids[]" class="remain_fees" data-id="{{ $value['id'] }}" data-name="{{ $value['student_name'] }}" data-remain_fees="{{ $amount }}" data-mobile="{{ $value['mobile'] }}" />
                                            </td>
		                                    <td> {{$j}} </td>
		                                    <td>{{$value['enrollment_no']}}</td>
		                                    <td>{{App\Helpers\sortStudentName($value['student_name'])}}</td>
		                                    <td>{{$value['standard_name']}}</td>
		                                    <td>{{$value['division_name']}}</td>

                                            @foreach($data['fees_head'] as $dk => $dv)
                                                @if(isset($data['fees_details'][$value['id']][$data['fees_heads'][$dv]]))
                                                    <td>{{$data['fees_details'][$value['id']][$data['fees_heads'][$dv]]}}</td>
                                                @else
                                                    <td>00</td>
                                                @endif
                                            @endforeach

                                            <td>{{$amount}}</td>
		                                </tr>
                                        @endif
		                            @php
		                            $j++;
		                            @endphp
		                                @endforeach
		                            @endif
		                            </tbody>
		                        </table>
		                    </div>
                            <div class="col-md-12 form-group mt-4">
                                <center>
                                    <a href="javascript:void(0)" id="remain_fees_sms" class="btn btn-success">Sent SMS</a>
                                    <a href="javascript:void(0)" id="remain_fees_notification" class="btn btn-success">Sent Notification</a>
                                </center>
                            </div>
	                    </div>
                </div>

        @endif

</div>

@include('includes.footerJs')
<script>
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
	             console.log(i)
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = false;
	             }
	         }
	     }
	}
</script>
<script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell

     var table = $('#example').DataTable( {
         select: true,
         lengthMenu: [
                        [100, 500, 1000, -1],
                        ['100', '500', '1000', 'Show All']
        ],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'pdfHtml5',
                title: 'Fees Status Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                pageSize: 'A0',
                exportOptions: {
                     columns: ':visible'
                },
            },
            { extend: 'csv', text: ' CSV', title: 'Fees Status Report' },
            { extend: 'excel', text: ' EXCEL',title: 'Fees Status Report' },
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Fees Status Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details("$grade_id", "$standard_id", "$division_id") !!}`);
                    $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                }
            },
            'pageLength'
        ],
        });
        //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');


        $('#example thead tr').clone(true).appendTo( '#example thead' );
        $('#example thead tr:eq(1) th').each( function (i) {
            var title = $(this).text();
            $(this).html( '<input type="text" placeholder="Search '+title+'" />' );

            $( 'input', this ).on( 'keyup change', function () {
                if ( table.column(i).search() !== this.value ) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );

} );
</script>
<script>
    // SEND SMS
    $(document).ready(function(){
        $('#remain_fees_sms').on('click', function(){
            
            // get checked sudend ids
            var studentsData = [];
            $('.remain_fees:checked').each(function() {
                var student_id = $(this).data('id');
                var student_name = $(this).data('name');
                var student_mobile = $(this).data('mobile');
                var student_remain_fees = $(this).data('remain_fees');
                var studentinfo = {
                    student_id: student_id,
                    student_mobile: student_mobile,
                    student_remain_fees: student_remain_fees,
                    student_name: student_name,
                    sendType:'sms',
                };
                studentsData.push(studentinfo);
            });
            if(studentsData.length===0){
                alert('Please select atleast one checkbox!');
            }
            // send sms using ajax
            var path = "{{ route('remainFeesNotification') }}";
            console.warn('ajax path', path);
            $.ajax({
                url: path,
                type: 'POST',
                data: { studentsData: studentsData},
                dataType: 'json',
                success: function ( response ) {
                    if ( response.status == 200 ) {
                        window.location.href="{{ route('fees_status_report.index') }}";
                    }
                }
            });
        });

        $('#remain_fees_notification').on('click', function(){
            // get checked sudend ids
            var studentsData = [];
            $('.remain_fees:checked').each(function() {
                var student_id = $(this).data('id');
                var student_name = $(this).data('name');
                var student_mobile = $(this).data('mobile');
                var student_remain_fees = $(this).data('remain_fees');
                var studentinfo = {
                    student_id: student_id,
                    student_mobile: student_mobile,
                    student_remain_fees: student_remain_fees,
                    student_name: student_name,
                    sendType:'notification',
                };
                studentsData.push(studentinfo);
            });
            if(studentsData.length===0){
                alert('Please select atleast one checkbox!');
            }
            // send sms using ajax
            var path = "{{ route('remainFeesNotification') }}";
            console.warn('ajax path', path);
            $.ajax({
                url: path,
                type: 'POST',
                data: { studentsData: studentsData},
                dataType: 'json',
                success: function ( response ) {
                    if ( response.status == 200 ) {
                        window.location.href="{{ route('fees_status_report.index') }}";
                    }
                }
            });
        });

        // check all
        $('#fees_check_all').on('click', function(){
            var isChecked = $(this).is(':checked');
            if ( isChecked ) {
                $('.remain_fees').prop('checked', true);
            }else {
                $('.remain_fees').prop('checked', false);
            }
        });
    });
</script>

@include('includes.footer')
@endsection
