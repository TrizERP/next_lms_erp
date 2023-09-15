@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Other Fees Cancel Report</h4> </div>
            </div>
        @php
            $grade_id = $standard_id = $division_id = $enrollment_no = $from_date = $to_date = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
                $from_date = $data['from_date'];
                $to_date = $data['to_date'];
            }
            if(isset($data['from_date'])){                
                $from_date = $data['from_date'];
                $to_date = $data['to_date'];
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
                <form action="{{ route('otherNew_cancel_fees_report.create') }}" enctype="multipart/form-data" class="row">                    
                    @csrf                                    
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Other Fees Head</label>
                        <select id="otherfeeshead" name="otherfeeshead"class="form-control">
                            <option value="">Select</option>
                            @if(isset($data['feesOtherHead_data']))
                                @foreach($data['feesOtherHead_data'] as $key => $val)
                                    @php 
                                    $selected = "";
                                    if(isset($data['otherfeeshead']) && $data['otherfeeshead'] == $val['id'])
                                    {
                                        $selected = "selected";
                                    }
                                    @endphp
                                    <option {{$selected}} value={{$val['id']}}>{{$val['display_name']}}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>

                </form>
            </div>
        @if(isset($data['other_feesData']))
        @php
            if(isset($data['other_feesData'])){
                $other_feesData = $data['other_feesData'];
            }
            
        @endphp
        <div class="card">
            <div class="table-responsive">
                <table id="example" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>{{ App\Helpers\get_string('grno','request')}}</th>
                            <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{ App\Helpers\get_string('standard','request')}}</th>
                            <th>{{ App\Helpers\get_string('division','request')}}</th>
                            <th>Mobile No.</th>                                                               
                            <th>Fees Head</th>                               
                            <th>Receipt No.</th>                               
                            <th>Remark</th>                                                           
                            <th>Cancelled Date</th>                               
                            <th>Cancelled By</th>                               
                            <th>Amount</th>                                                               
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $j=1; 
                    $grand_total = 0;
                    if(isset($data['other_fee_title']))
                    {
                        foreach($data['other_fee_title'] as $key => $otherhead)
                        {
                            $grand_total[$otherhead->fees_title] = 0;
                        }
                    }                    
                    @endphp
                                           
                    @if(isset($data['other_feesData']))
                        @foreach($other_feesData as $key => $fees_value)                  
                        @php $student_total = 0; @endphp
                        <tr>
                            <td>{{$j}}</td>
                            <td>{{$fees_value['enrollment_no']}}</td>
                            <td>{{$fees_value['student_name']}}</td>
                            <td>{{$fees_value['standard_name']}}</td>
                            <td>{{$fees_value['division_name']}}</td>
                            <td>{{$fees_value['mobile']}}</td>                            
                            <td>{{$fees_value['fees_head']}}</td>                                                                                
                            <td>{{$fees_value['receipt_id']}}</td>                                                                                
                            <td>
                                @if($fees_value['cancellation_remarks'] != "")
                                {{$fees_value['cancellation_remarks']}}
                                @else - 
                                @endif</td>                                                                                                                                                                                    
                            <td>{{date('d-m-Y',strtotime($fees_value['cancellation_date']))}}</td>                                                                                
                            <td>{{$fees_value['cancelled_by']}}</td>                                                                                                                                                                                    
                            <td>{{$fees_value['cancellation_amount']}}</td>                                                                                                                                                                                    
                        </tr>
                        @php
                        $j++;                       
                        $grand_total += $fees_value['cancellation_amount'];                       
                        @endphp
                        @endforeach
                        <tr class="font-weight-bold">
                            <td>{{$j++}}</td>
                            <td></td>
                            <td></td>
                            <td></td>                           
                            <td></td>                           
                            <td></td>                           
                            <td></td>                                                                       
                            <td></td>                           
                            <td></td>                           
                            <td></td>                           
                            <td>Total</td>                                                        
                            <td>{{$grand_total}}</td>                             
                        </tr>                       
                        
                    @endif
                    </tbody>
                </table>
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
                title: 'Other Fees Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Other Fees Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Other Fees Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Other Fees Report'}, 
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
