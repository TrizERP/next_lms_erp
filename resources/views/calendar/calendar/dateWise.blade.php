@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Search by Date Calendar</h4>
            </div>
        </div>
    @php 
    $from_date =  $to_date = date('Y-m-d');
    if(isset($data['from_date'])) { $from_date = $data['from_date']; }
	if(isset($data['to_date'])) { $to_date = $data['to_date'];}
    @endphp
        <!-- form start  -->
        <div class="card">
            <a href="{{route('calendar.index')}}" class="btn btn-primary">Back to Calender</a>
            <form action="{{route('searchByDate')}}" method="get">
                @csrf 
                <div class="row" style="margin-top:45px">
                    <div class="col-md-4 form-group">
                        <label for="from_date">From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="to_date">To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-12">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
        <!-- form end  -->
        
        <!-- table start  -->
        @if(isset($data['searchData']))
        <div class="card">
            <div class="table-responsive">
				<table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Date</th>
                            <th>Event</th>
                            <th class="text-left">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($data['searchData'] as $key=>$value)
                    <tr>
                        <td>{{$key+1}}</td>
                        <td>{{date('d-m-Y',strtotime($value->school_date))}}</td>
                        <td>{{isset($value->title) ? $value->title : '-'}}</td>
                        <td>{{isset($value->description) ? $value->description : '-'}}</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
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
						title: 'Event Lists Report',
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
						title: 'Event Lists Report'
					},
					{
						extend: 'excel',
						text: ' EXCEL',
						title: 'Event Lists Report'
					},
					{
                        extend: 'print',
                        text: ' PRINT',
                        title: 'Event Lists Report',
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
