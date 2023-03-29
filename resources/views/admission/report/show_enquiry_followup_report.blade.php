@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Enquiry Followup Report</h4>
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
                    <form action="{{ route('admission_enquiry_followup_report') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf
                        <div class="row">
                            <div class="col-md-4">
                                <label>From Date </label>
                                <input type="text" id='from_date' required name="from_date" @if(isset($data['from_date'])) value="{{$data['from_date']}}" @endif class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label>To Date </label>
                                <input type="text" id='to_date' required name="to_date" @if(isset($data['to_date'])) value="{{$data['to_date']}}" @endif class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label>Follow-up Status </label>
                                <select id='follow_up_status' name="follow_up_status" class="form-control">
                                    <option value=""> Select Status </option>
                                    <option value="Followed" @if(isset($data['follow_up_status'])) @if($data['follow_up_status'] == "Followed") selected="selected" @endif @endif>Followed</option>
                                    <option value="Unfollowed" @if(isset($data['Unfollowed'])) @if($data['status'] == "Unfollowed") selected="selected" @endif @endif>Unfollowed</option>
                                </select>
                            </div>
                            <div class="col-md-12">                            
                                <center>
                                    <input type="submit" name="report" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>    
                    </form>    

        </div>
        
        <div class="card">            
            @if(isset($data['data']))
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>Enquiry No</th>
                                    <th>Enquiry Date</th>
                                    <th>Student Name</th>
                                    <th>Father Name</th>
                                    <th>Previous School Name</th>
                                    <th>Admission Standard</th>
                                    <th>Address</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th>Source of Enquiry</th>
                                    <th>Follow Up Date</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $counted_rowspan_arr = array();
                                $i = 1;
                                foreach($data['data'] as $key => $value) 
                                {
                                    if(!in_array($value['enquiry_id'], $counted_rowspan_arr))
                                    {
                                        $counted_rowspan_arr[] = $value['enquiry_id'];
                                        $get_count = DB::select("SELECT count(*) as rowspan_tot,enquiry_id 
                                                                FROM follow_up fu
                                                                WHERE DATE_FORMAT(fu.created_on,'%Y-%m-%d') 
                                                                BETWEEN '" . $data['from_date'] . "' AND '" . $data['to_date'] . "'
                                                                AND fu.enquiry_id = '".$value['enquiry_id']."'
                                                                group by fu.enquiry_id");                        
                                        $row_span = $get_count[0]->rowspan_tot;
                                        if($row_span > 1){
                                            $row_span_new = $row_span + 1;
                                        }else{
                                            $row_span_new = $row_span;
                                        }                                                            
                                        $rowspan_text = " rowspan=".$row_span_new;
                                        
                                @endphp        
                                        <tr>
                                            <td {{$rowspan_text}}>{{$i++}}</td>
                                            <td {{$rowspan_text}}>{{$value['enquiry_no']}}</td>
                                            <td {{$rowspan_text}}>{{$value['enquiry_date']}}</td>
                                            <td {{$rowspan_text}}>{{$value['student_name']}}</td>
                                            <td {{$rowspan_text}}>{{$value['father_name']}}</td>
                                            <td {{$rowspan_text}}>{{$value['previous_school_name']}}</td>
                                            <td {{$rowspan_text}}>{{$value['admission_std']}}</td>
                                            <td {{$rowspan_text}}>{{$value['address']}}</td>
                                            <td {{$rowspan_text}}>{{$value['mobile']}}</td>
                                            <td {{$rowspan_text}}>{{$value['email']}}</td>
                                            <td {{$rowspan_text}}>{{$value['source_of_enquiry']}}</td>
                                            <td>{{$value['follow_up_date']}}</td>
                                            <td>{{$value['followup_remark']}}</td>
                                        </tr>
                                @php        
                                    }
                                    else
                                    {
                                @endphp 
                                        <tr>
                                            <td>{{$value['follow_up_date']}}</td>
                                            <td>{{$value['followup_remark']}}</td>
                                        <tr> 
                                @php       
                                    }                                    
                                }  
                                @endphp 
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
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
                title: 'Enquiry Followup Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Enquiry Followup Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Enquiry Followup Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Enquiry Followup Report'}, 
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
