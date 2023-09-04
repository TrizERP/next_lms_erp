@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Missing Document Report</h4>
            </div>
        </div>        
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

            @php
            $grade_id = $standard_id = $division_id = '';
            
                if(isset($data['grade_id'])){
                    $grade_id = $data['grade_id'];
                    $standard_id = $data['standard_id'];
                    $division_id = $data['division_id'];
                }
            @endphp   
            <form action="{{ route('missing_document_report.create') }}" enctype="multipart/form-data">                
                @csrf  
                <div class="row">                    
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-4 form-group mt-3">
                        <input type="submit" name="submit" value="Search" class="btn btn-success" >                     
                    </div>
                </div>              
            </form>
        </div>
      

        @if(isset($data['result_report']))
        @php
        $j = 1;
            if(isset($data['result_report'])){
                $result_report = $data['result_report'];
            }
        @endphp
        <div class="card">            
            <div class="table-responsive">
                {!! App\Helpers\get_school_details("$grade_id","$standard_id","$division_id") !!}
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>{{App\Helpers\get_string('grno','request')}}</th>
                            <th>{{App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{App\Helpers\get_string('standard','request')}}</th>
                            <th>{{App\Helpers\get_string('division','request')}}</th>
                               @foreach($data['docment_type_data'] as $key => $val)
                                    <th>{{$val['document_type']}}</th>
                               @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result_report as $stud_key => $stud_data)
                            <tr>
                                <td>{{$j++}}</td>
                                <td> {{$stud_data->enrollment_no}} </td>
                                <td> {{$stud_data->student_name}} </td>
                                <td> {{$stud_data->standard_name}} </td>
                                <td> {{$stud_data->division_name}} </td>
                                @php
                                if($stud_data->document_list != "")
                                {
                                    $document_list = explode(",",$stud_data->document_list);
                                }else
                                {
                                    $document_list = array();
                                }
                                
                                foreach($data['docment_type_data'] as $key => $val)
                                {
                                    if( in_array($val['id'],$document_list))
                                    {
                                        echo "<td style='color:green;font-size: 22px;'>&#10004;</td>";
                                    }
                                    else
                                    {
                                        echo "<td style='color:red;'>&#10060;</td>";
                                    }
                                }                                                            
                                @endphp
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>    
        @endif
    </div>
</div>

<script>
    function checkAll(ele,name) {
         var checkboxes = document.getElementsByClassName(name);
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
                title: 'Missing Document Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Missing Document Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Missing Document Report' }, 
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Missing Document Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details("$grade_id", "$standard_id", "$division_id") !!}`);
                }
            },
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

