@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Type Wise Report</h4> </div>
            </div>
        @php
            $grade_id = $standard_id = $division_id = $enrollment_no = $first_name = $last_name = $mobile_no = $uniqueid = $from_date = $to_date = $admission_year = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }

            if(isset($data['first_name']))
            {
                $first_name = $data['first_name'];
            }
            if(isset($data['last_name']))
            {
                $last_name = $data['last_name'];
            }
            if(isset($data['enrollment_no']))
            {
                $enrollment_no = $data['enrollment_no'];
            }
            if(isset($data['mobile_no']))
            {
                $mobile_no = $data['mobile_no'];
            }
            if(isset($data['uniqueid']))
            {
                $uniqueid = $data['uniqueid'];
            }
            if(isset($data['from_date']))
            {
                $from_date = $data['from_date'];
            }
            if(isset($data['to_date']))
            {
                $to_date = $data['to_date'];
            }
            if(isset($data['admission_year']))
            {
                $admission_year = $data['admission_year'];
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
                <form action="{{ route('fees_type_wise_report.create') }}" enctype="multipart/form-data" class="row">
                    @csrf
                    <div class="col-md-4 form-group">
                        <label>First Name</label>
                        <input type="text" id="first_name" value="{{$first_name}}" name="first_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Last Name</label>
                        <input type="text" id="last_name" value="{{$last_name}}" name="last_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Enrollment No</label>
                        <input type="text" id="enrollment_no" name="enrollment_no" value="{{$enrollment_no}}" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile No.</label>
                        <input type="text" id="mobile_no" value="{{$mobile_no}}" name="mobile_no" class="form-control">
                    </div>                    
                    <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('uniqueid','request')}}</label>
                        <input type="text" id="uniqueid" value="{{$uniqueid}}" name="uniqueid" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Admission Year</label>
                        <select id='admission_year' name="admission_year" class="form-control">
                            <option>--Select Admission Year--</option>
                            <option value="2017" @if($admission_year == 2017) selected="selected" @endif>2017</option>
                            <option value="2018" @if($admission_year == 2018) selected="selected" @endif>2018</option>
                            <option value="2019" @if($admission_year == 2019) selected="selected" @endif>2019</option>
                            <option value="2020" @if($admission_year == 2020) selected="selected" @endif>2020</option>
                            <option value="2021" @if($admission_year == 2021) selected="selected" @endif>2021</option>
                        </select>
                    </div>
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker">
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>

                </form>
            </div>
        @if(isset($data['fees_data']))
        @php
            if(isset($data['fees_data'])){
                $fees_data = $data['fees_data'];
            }
            
        @endphp
        <div class="card">
            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>{{App\Helpers\get_string('grno','request')}}</th>
                            <th>{{App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{App\Helpers\get_string('standard','request')}}</th>
                            <th>{{App\Helpers\get_string('division','request')}}</th>
                            <th>Receipt No.</th>
                            <th>{{App\Helpers\get_string('studentquota','request')}}</th>
                            <th>Batch</th>
                            <th>Receipt Date</th>
                            @if(isset($data['fees_heads']))
                                @foreach($data['fees_heads'] as $key => $val)
                                <th>{{$val['display_name']}}</th>
                                @endforeach
                            @endif 
                            <th>Total Fine</th>
                            <th>Total {{App\Helpers\get_string('discount','request')}}</th>
                            <th>Total</th>                                                           
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $j=1;
                    $final_grand_total = $total_fine = $total_disc = $total_amt=0;
                    if(isset($data['fees_heads']))
                    {
                        foreach($data['fees_heads'] as $key => $val)
                        {
                            $grand_total[$val['fees_title']] = 0;
                        }
                    }
                    @endphp
                                           
                    @if(isset($data['fees_data']))
                        @foreach($fees_data as $key => $fees_value) 
                        @php 
                        $total_paid = 0; 
                        @endphp                 
                        <tr>
                            <td>{{$j}}</td>
                            <td>{{$fees_value['enrollment_no']}}</td>
                            <td>{{$fees_value['student_name']}}</td>
                            <td>{{$fees_value['std_name']}}</td>
                            <td>{{$fees_value['div_name']}}</td>
                            <td>{{$fees_value['receipt_no']}}</td>
                            <td>{{$fees_value['stu_qouta']}}</td>
                            <td>{{$fees_value['student_batch_name']}}</td>
                            <td>{{$fees_value['receipt_date']}}</td>
                            @if(isset($data['fees_heads']))
                                @foreach($data['fees_heads'] as $k => $val)
                                    @php 
                                        $total_paid += $fees_value["total_".$val['fees_title']];
                                        $grand_total[$val['fees_title']] += $fees_value["total_".$val['fees_title']];
                                        
                                        if($fees_value["total_".$val['fees_title']] != "")
                                        {
                                            echo "<td>".$fees_value["total_".$val['fees_title']]."</td>";

                                        }
                                        else
                                        {
                                            echo "<td>0</td>";
                                        }                                        
                                    @endphp
                                    
                                @endforeach
                            @endif

                            @php
                                $total_fine += $fees_value['total_fine'];
                                $total_disc += $fees_value['tot_disc'];
                            @endphp
                            <td>{{$fees_value['total_fine']}}</td>                          
                            <td>{{$fees_value['tot_disc']}}</td>                          
                            <td>{{$fees_value['amount'] + $fees_value['total_fine'] + $fees_value['tot_disc']}}</td>                         
                        </tr>
                    @php
                    $j++;
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
                            <td>Total</td>
                            @if(isset($data['fees_heads']))
                                 @foreach($data['fees_heads'] as $key => $val)                                    
                                    <td>{{$grand_total[$val['fees_title']]}}</td>
                                    @php $final_grand_total += $grand_total[$val['fees_title']]; @endphp
                                 @endforeach

                            @endif 
                            <td>{{$total_fine}}</td>
                            <td>{{$total_disc}}</td>
                            <td>{{$final_grand_total + $total_fine + $total_disc}}</td> 
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
    //$("form").submit(function(){
      //  alert("Submitted");
       // $('#grade').attr('required', 'required');
//$('#standard').attr('required', 'required');
    //});
   
    function checkAll(ele) {
         var checkboxes = document.getElementsByTagName('input');
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
                title: 'Fees Type-wise Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Fees Type-wise Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Fees Type-wise Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Fees Type-wise Report'}, 
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
