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
                <h4 class="page-title">Weightage Conversion Report</h4> 
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
                                        @php
                                            $total_weightage = 0;
                                        @endphp
                                        @if(isset($data['get_exam_master_heading']))
                                            @foreach($data['get_exam_master_heading'] as $k => $exam_master_heading)
                                                <th style="text-align:center;">{{$exam_master_heading->ExamTitle}} </br>({{ $exam_master_heading->weightage }})</th>

                                                @php 
                                                    $total_weightage += (int) $exam_master_heading->weightage;
                                                @endphp
                                            @endforeach
                                        @endif
                                    <th style="text-align:center;">Marks Obtained </br>(Out of {{ $total_weightage }})</th>
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
                                        @php
                                            $total = 0.0;
                                        @endphp
                                        @foreach($data['get_exam_master_heading'] as $exam)
                                            @php
                                                $exam_id = $exam->Id;
                                                $mark = $data['mark_arr'][$student_id][$exam_id] ?? [0]; // Default to [0] if not set

                                                // Convert each element in $mark to a float before summing
                                                foreach ($mark as $value) {
                                                    $total += number_format($value, 2);
                                                }
                                            @endphp
                                            <td style="text-align:center;">
                                                @if($mark[0] != 0)
                                                    {{ $mark[0] }}
                                                @else
                                                    0
                                                @endif
                                            </td>
                                        @endforeach
                                        <td style="text-align:center;">{{ $total }}</td>
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
