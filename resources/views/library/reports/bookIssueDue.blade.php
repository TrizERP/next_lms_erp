@extends('layout') @section('container')
<div id="page-wrapper" style="color:#000;">
	<div class="container-fluid">

		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Book Issued & Due Reports</h4>
			</div>
		</div>
		<!-- search card start  -->
		<div class="card">
			@if(!empty($data['message'])) @if(!empty($data['status_code']) && $data['status_code'] == 1)
			<div class="alert alert-success alert-block">
				@else
				<div class="alert alert-danger alert-block">
					@endif
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{{ $data['message'] }}</strong>
				</div>
				@endif 
                @php $grade_id = $standard_id = $division_id = $report_type = ''; 
                if(isset($data['grade_id'])){ 
                    $grade_id = $data['grade_id']; 
                    $standard_id = $data['standard_id']; 
                    $division_id = $data['division_id']; 
                } 
                $from_date = $to_date = now()->format('Y-m-d');
                if(isset($data['from_date'])){
                    $from_date = $data['from_date'];
                }

                if(isset($data['to_date'])){
                    $to_date = $data['to_date'];
                }
				if(isset($data['report_type'])){
                    $report_type = $data['report_type'];
                }
				$report_types = ['loan'=>'Loan Report','overdue'=>'Overdue Report'];
				$i=1;
                @endphp
				<form action="{{ route('book_issue_report.create') }}" enctype="multipart/form-data" method="post">
                @csrf
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<div class="row">
									<div class="col-md-3 form-group">
									<label for="report-type">Report Type</label>
										<select name="report_type" id="report_type" class="form-control">
											@foreach($report_types as $key=>$value)
											<option value="{{$key}}" @if($report_type!='' && $report_type==$key)  selected @endif>{{$value}}</option>
											@endforeach
										</select>
									</div>
									{{ App\Helpers\SearchChain('3','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
								</div>
							</div>
						</div>

						<div class="col-md-4 form-group">
							<label>{{App\Helpers\get_string('studentname')}}
								<i class="mdi mdi-lead-pencil"></i>
							</label>
							<input type="text" id="stu_name" placeholder="{{App\Helpers\get_string('studentname')}}" name="stu_name" class="form-control"
							 @if(isset($data[ 'stu_name'])) value="{{$data['stu_name']}}" @endif>
						</div>
						
						<div class="col-md-4 form-group">
							<label>Mobile</label>
							<input type="text" id="mobile" placeholder="Mobile" name="mobile" class="form-control" @if(isset($data[ 'mobile'])) value="{{$data['mobile']}}"
							 @endif>
						</div>
						<div class="col-md-4 form-group">
							<label>{{App\Helpers\get_string('grno')}}
								<i class="mdi mdi-lead-pencil"></i>
							</label>
							<input type="text" id="grno" placeholder="{{App\Helpers\get_string('grno')}}" name="grno" class="form-control" @if(isset($data[
							 'grno'])) value="{{$data['grno']}}" @endif>
						</div>
                        <div class="col-md-4 form-group">
							<label>From Date</label>
							<input type="text" id="from_date" placeholder="from_date" name="from_date" class="form-control mydatepicker" value="{{$from_date}}">
						</div>
                        <div class="col-md-4 form-group">
							<label>To Date</label>
							<input type="text" id="to_date" placeholder="to_date" name="to_date" class="form-control mydatepicker" value="{{$to_date}}">
						</div>
					</div>

					<div class="col-md-12 form-group">
						<center>
							<input type="submit" name="submit" value="Search" class="btn btn-success">
						</center>
					</div>
			</div>
			</form>
		</div>
		<!-- search card ends  -->

		<!-- data card ends  -->
        @if(isset($data['details']))
        <div class="card">
			<div class="table-responsive">
				<table id="example" class="table table-box table-bordered">
					<thead>
						<tr>
							<th>Sr No.</th>
							<th>{{ App\Helpers\get_string('studentname')}}</th>
							<th>{{ App\Helpers\get_string('grno')}}</th>
							<th>Mobile</th>
							<th>{{ App\Helpers\get_string('std/div')}}</th>
							<th>Book Name</th>
							<th>Item Code</th>							
							<th>Issued Date</th>
							<th>Due Date</th>
							<th class="text-left">Return Date</th>	
						</tr>
					</thead>
					<tbody>
					@foreach($data['details'] as $key=>$value)
					<tr>
					@php
						$return_date = '';
						if($value['return_date'] !='' && $value['return_date']!=null && $value['return_date']!="0000-00-00 00:00:00"){
							$return_date= \Carbon\Carbon::parse($value['return_date'])->format('d-m-Y H:s:i');
						}
					@endphp
						<td>{{$i++}}</td>
						<td>{{$value['student_name']}}</td>	
						<td>{{$value['enrollment_no']}}</td>						
						<td>{{$value['mobile']}}</td>						
						<td>{{$value['standard'].'/'.$value['division']}}</td>						
						<td>{{$value['book_title']}}</td>						
						<td>{{$value['item_code']}}</td>						
						<td>{{\Carbon\Carbon::parse($value['issued_date'])->format('d-m-Y') }}</td>						
						<td>{{\Carbon\Carbon::parse($value['due_date'])->format('d-m-Y') }}</td>						
						<td>{{ $return_date }}</td>						
					</tr>
					@endforeach
					</tbody>
			</table>
			</div>
        </div>
        @endif
		<!-- data card ends  -->        
                                
		<!-- end container -->
	</div>
</div>

@include('includes.footer') @include('includes.footerJs')
<script>
	 $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Book Issued & Due Reports',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Book Issued & Due Reports'},
                {extend: 'excel', text: ' EXCEL', title: 'Book Issued & Due Reports'},
                {extend: 'print', text: ' PRINT', title: 'Book Issued & Due Reports'},
                'pageLength'
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
 @endsection