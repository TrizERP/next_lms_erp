@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Leave Encashment</h4>
            </div>
        </div>
       
        <div class="card">
                @if(session('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ session('success') }}</strong>
                    </div>
                @endif
                <form action="{{route('leave_encashment.store')}}" method="post">
                @csrf
                @if(isset($data['record']) && !empty($data['record']))
                    <!-- <div class="row mb-4">
                        <div class="col-md-4">
                            <input type="submit" class="btn btn-success" name="generate" value="Generate">
                        </div>
                    </div> -->
                @endif
                <div class="table-responsive">
                    <table id="example" class="table table-box table-bordered">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Employee Name</th>
                                <th>Department</th>
                                <th>Joinning Date</th>
                                <th>Pending Leaves</th>
                                <th>Per Day Salary</th>
                                <th class="text-left">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $i=1; @endphp
                            @foreach($data['record'] as $emp_id => $value)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>{{$value->emp_name}}</td>
                                <td>{{$value->department_name}}</td>
                                <td>{{$value->joined_date}}</td>
                                <td>{{$value->PENDING}}</td>
                                <td>{{$value->per_day_salary}}</td>
                                <td class="text-left">{{$value->total_amount}}
                                    <input type="hidden" name="emp_id[]" value="{{$value->emp_id}}">
                                    <input type="hidden" name="amount[]" value="{{$value->total_amount}}">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            </div>
</div>

@include('includes.footerJs')
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
                    title: 'Fees Monthly Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Fees Monthly Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Fees Monthly Report'},
                {extend: 'print', text: ' PRINT', title: 'Fees Monthly Report'},
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
