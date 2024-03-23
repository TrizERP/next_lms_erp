{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Graphical Fees</h4>
            </div>
        </div>
        <!-- table -->
        <div class="card">
			<div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
						<table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <td>SR No.</td>
                                    <td>Grade</td>
                                    <td>Standard</td>
                                    <td>Division</td>
                                    <td>Student Name</td>
                                    <td>Total Fees</td>
                                    <td>Paid Fees</td>                                                                                                           
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key=>$value)
                                <tr>
                                    <td>{{$key+1}}</td>
                                    <td>{{$value->title}}</td>
                                    <td>{{$value->standard_name}}</td>
                                    <td>{{$value->division_name}}</td>
                                    <td>{{$value->student_name}}</td>
                                    <td>{{$value->tot_amount}}</td>
                                    <td>{{$value->tot_paid}}</td>                                    
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>            
        <!-- graph  -->
        <div class="card">            
            <center>
                <div id="container" style="width:70%;"></div>
            </center>          
        </div>
    </div>
</div>
@include('includes.footerJs')
<script src="https://code.highcharts.com/highcharts.js"></script>
{{-- <script src="https://code.highcharts.com/highcharts-3d.js"></script> --}}
{{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/7.2.0/highcharts.js"></script> --}}
<script src="https://code.highcharts.com/modules/sunburst.js"></script>
{{-- <script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script> --}}
<script>

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
                title: 'Inactive Student Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Inactive Student Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Inactive Student Report' }, 
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Inactive Student Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details() !!}`);
                }
            },
            'pageLength' 
        ], 
        }); 
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

    var data = {!! json_encode($data['chartData']) !!};

// Splice in transparent for the center circle
Highcharts.getOptions().colors.splice(0, 0, 'transparent');


Highcharts.chart('container', {

    chart: {
        height: '80%'
    },

    title: {
        text: 'Fees Chart'
    },
    subtitle: {
        text: 'All Data '
    },
    series: [{
        type: "sunburst",
        data: data,
        allowDrillToNode: true,
        cursor: 'pointer',
        dataLabels: {
            format: '{point.name}',
            filter: {
                property: 'innerArcLength',
                operator: '>',
                value: 16
            }
        },
        levels: [{
            level: 1,
            levelIsConstant: false,
            dataLabels: {
                filter: {
                    property: 'outerArcLength',
                    operator: '>',
                    value: 64
                }
            }
        }, {
            level: 2,
            colorByPoint: true
        },
        {
            level: 3,
            colorVariation: {
                key: 'brightness',
                to: -0.5
            }
        }, {
            level: 4,
            colorVariation: {
                key: 'brightness',
                to: 0.5
            }
        }]

    }],
    tooltip: {
        headerFormat: "",
        pointFormat: 'The Count of <b>{point.name}</b> is <b>{point.value}</b>'
    },credits: {
        enabled: false
    },

});
</script>
@include('includes.footer')
@endsection