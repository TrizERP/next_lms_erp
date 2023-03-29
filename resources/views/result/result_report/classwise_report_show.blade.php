@include('includes.headcss')
<style>
    tfoot input {
        width: 100%;
        padding: 3px;
        box-sizing: border-box;
    }
    tfoot {
     display: table-header-group;
    }
</style>
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Classwise Report</h4> 
            </div>
        </div>        
        @if(isset($data['all_student']))
            @php
                if(isset($data['all_student']))
                {
                    $all_student = $data['all_student'];
                }
            @endphp 
            <div class="card">  
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">                       
                             <thead>
                                <tr>
                                    <th>Standard</th>
                                    <th>Roll No.</th>
                                    <th>Student Name</th>
                                        @if(isset($data['date_arr']))
                                            @foreach($data['date_arr'] as $k => $date_point)
                                                <th>{{$date_point}}</th>
                                            @endforeach
                                        @endif
                                    <th>Total</th>
                                    <th>Percentage(%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($all_student as $key => $all_data)
                                    @php
                                        $total = $obtained_total = $percentage = 0;
                                        $student_id = $all_data['id'];
                                    @endphp                                    
                                    <tr>
                                        <td>{{$all_data['standard_name']}} - {{$all_data['division_name']}}</td>
                                        <td>{{$all_data['roll_no']}}</td>    
                                        <td>{{$all_data['first_name']}} {{$all_data['middle_name']}} {{$all_data['last_name']}}</td>
                                        @if(isset($data['date_arr']))
                                        @foreach($data['date_arr'] as $k => $date_point)
                                            @if(isset($data['WRT_data'][$student_id][$k]) && count($data['WRT_data'][$student_id][$k]) > 0)
                                                    @if($data['WRT_data'][$student_id][$k]['is_absent'] == 'AB')
                                                        <td style="font-weight: bold;color:red;">{{$data['WRT_data'][$student_id][$k]['is_absent']}}</td>
                                                    @else
                                                        <td>{{$data['WRT_data'][$student_id][$k]['obtained_points']}}</td>
                                                    @endif
                                                @php
                                                    $total = $total + $data['WRT_data'][$student_id][$k]['total_points'];
                                                    $obtained_total = $obtained_total + $data['WRT_data'][$student_id][$k]['obtained_points'];
                                                    $per = (($obtained_total * 100) / $total);
                                                    $percentage = number_format($per,2);
                                                @endphp
                                                @else
                                              <td>
                                                    -
                                              </td>
                                            @endif
                                        @endforeach
                                        @endif
                                        <td>{{$obtained_total}}/{{$total}}</td>
                                        <td>{{$percentage}}</td>
                                    </tr>
                                @endforeach
                            </tbody>    
                        </table>
                    </div>     
                </div>
            </div>    
        @endif           
    </div>
</div>

@include('includes.footerJs')

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
                title: 'Inward Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Classwise Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Classwise Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Classwise Report' }, 
            'pageLength' 
        ], 
        }); 
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
