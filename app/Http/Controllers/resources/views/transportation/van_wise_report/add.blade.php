{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Vanwise Report</h4>
            </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <br><br><br>
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    {!! App\Helpers\get_school_details("","","") !!}
                    <table id="example" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>SR No</th>
                                <th>Gr No.</th>
                                <th>Name</th>
                                <th>Std/Div</th>
                                <th>Mobile</th>
                                <th>Address</th>
                                <th>Route Name</th>
                                <th>Shift</th>
                                <th>Bus</th>
                                <th>Stop</th>
                                <th>Driver</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                             @php
                                $j = 1;
                             @endphp
                            @foreach($data['data'] as $key => $data)

                            <tr>
                                <td>{{$j++}}</td>
                                <td>{{$data->enrollment_no}}</td>
                                <td>{{$data->name}}</td>
                                <td>{{$data->stddiv}}</td>
                                <td>{{$data->mobile}}</td>
                                <td>{{$data->address}}</td>
                                <td>{{$data->route_name}}</td>
                                <td>{{$data->shift_title}}</td>
                                <td>{{$data->bus_name}}</td>
                                <td>{{$data->stop_name}}</td>
                                <td>{{$data->driver}}</td>
                                <td>{{$data->van_vise_amount}}</td>
                            </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <center>
                        <a href="{{ route('van_wise_report.index') }}" class="btn btn-success">Search Again</a>
                    </center>
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
</div>


@include('includes.footerJs')
<script>
    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });
    $(document).ready(function() {
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
                title: 'Vanwise Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Vanwise Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Vanwise Report' }, 
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Vanwise Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
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
@include('includes.footer')
@endsection