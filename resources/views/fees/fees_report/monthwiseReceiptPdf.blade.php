@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Receipt PDF</h4> </div>
            </div>
        @php
            $to_date = $from_date= now();

            if(isset($data['to_date']))
            {
                $to_date = $data['to_date'];
            }
            if(isset($data['from_date']))
            {
                $from_date = $data['from_date'];
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
                <form action="{{ route('monthly_receipt_pdf.create') }}" enctype="multipart/form-data" class="row">
                    @csrf
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off" required>
                    </div>

                     <div class="col-md-4 form-group ml-0 mr-0">
                        <label>To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off" required>
                    </div>

                    <div class="col-md-4 form-group mt-4">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </form>
            </div>
       <!-- table start  -->
       @if(!empty($data['fees_details']))

       <div class="card">
       {!! App\Helpers\get_school_details("", "", "") !!}
        <form action="{{route('monthly_receipt_pdf.store')}}" method="post">
            @csrf
			<div class="table-responsive">
				<table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>SR No.</th>
                            <th>Receipt No.</th>
                            <th>Received Date</th>
                            <th>{{App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{App\Helpers\get_string('std/div','request')}}</th>
                            <th class="text-left">Paid Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['fees_details'] as $key => $value)
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>{{$value->receipt_no}}</td>
                            <td>{{$value->receiptdate}}</td>
                            <td>{{$value->student_name}}</td>
                            <td>{{$value->standard}} / {{$value->division}}</td>
                            <td>{{$value->amount}}</td>
                        </tr>
                        <input type="hidden" name="studentData[{{$value->student_id}}][receipt_no]" value="{{$value->receipt_no}}">
                        <textarea name="studentData[{{$value->student_id}}][fees_html]" id="fees_html" class="d-none">{{$value->fees_html}}</textarea>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    <center>
                        <input type="submit" name="download" value="Download" class="btn btn-primary">
                    </center>
                </div>
            </div>
            </form>
        </div>
        @endif
       <!-- table end  -->
    </div>
</div>

@include('includes.footerJs')
<script>
		$(document).ready(function() {
			var table = $('#example').DataTable({
				select: true,
				lengthMenu: [
					[100, 500, 1000, -1],
					['100', '500', '1000', 'Show All']
				],
				dom: 'Bfrtip',
				buttons: [{
						extend: 'pdfHtml5',
						title: 'Fees Collection Report',
						orientation: 'landscape',
						pageSize: 'LEGAL',
						pageSize: 'A0',
						exportOptions: {
							columns: ':visible'
						},
					},
					{
						extend: 'csv',
						text: ' CSV',
						title: 'Fees Collection Report'
					},
					{
						extend: 'excel',
						text: ' EXCEL',
						title: 'Fees Collection Report'
					},
					{
                        extend: 'print',
                        text: ' PRINT',
                        title: 'Fees Collection Report',
                        customize: function (win) {
                            $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
							var paymentDetails = $('#paymentDetails').clone();
							$(win.document.body).append(paymentDetails);
							$(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                        }
                    },
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
@include('includes.footer')
@endsection
