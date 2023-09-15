@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Overall Report</h4> </div>
            </div>
        @php
            $grade_id = $standard_id = $division_id = $enrollment_no = $first_name = $last_name = $mobile_no = $uniqueid = '';

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
                <form action="{{ route('show_fees_overall_report') }}" enctype="multipart/form-data" class="row" method="post">
                    {{ method_field("POST") }}
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
                        <label>{{ App\Helpers\get_string('grno','request')}}</label>
                        <input type="text" id="enrollment_no" name="enrollment_no" value="{{$enrollment_no}}" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile No.</label>
                        <input type="text" id="mobile_no" value="{{$mobile_no}}" name="mobile_no" class="form-control">
                    </div>                    
                    <div class="col-md-4 form-group">
                        <label>{{ App\Helpers\get_string('uniqueid','request')}}</label>
                        <input type="text" id="uniqueid" value="{{$uniqueid}}" name="uniqueid" class="form-control">
                    </div>
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
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
                            <th>{{ App\Helpers\get_string('grno','request')}}</th>
                            <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{ App\Helpers\get_string('std/div','request')}}</th>
                            <th>Mobile No.</th>
                            <th>{{ App\Helpers\get_string('uniqueid','request')}}</th>
                            <th style="background-color:#7befef;">Total Breakoff</th>
                            @if(isset($data['month_arr']))
                                 @foreach($data['month_arr'] as $month_id => $month_val)
                                    <th>{{$month_val}} Paid</th>
                                 @endforeach
                            <th>Total Fine</th>     
                            <th>Total Discount</th>     
                            <th style="background-color:#7befef;">Total Paid</th>     
                                 @foreach($data['month_arr'] as $month_id => $month_val)
                                    <th>{{$month_val}} Un-Paid</th>
                                 @endforeach
                            <th style="background-color:#7befef;">Total Unpaid</th>     
                            @endif                                                          
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $j=1;
                    $total_breakoff = $total_paid = $total_unpaid = $total_fine = $total_discount = 0;
                    foreach($data['month_arr'] as $month_id => $month_val)                        
                    {
                        $var = "amount_paid_".$month_id;
                        $$var = 0;
                        $var1 = "amount_unpaid_".$month_id;
                        $$var1 = 0;
                    }

                    @endphp
                                           
                    @if(isset($data['fees_data']))
                        @foreach($fees_data as $key => $fees_value)                  
                        <tr>
                            <td>{{$j}}</td>
                            <td>{{$fees_value['enrollment']}}</td>
                            <td>{{$fees_value['name']}}</td>
                            <td>{{$fees_value['stddiv']}}</td>
                            <td>{{$fees_value['mobile']}}</td>
                            <td>{{$fees_value['uniqueid']}}</td>
                            <td style="background-color:#7befef;">{{$fees_value['-']['bk'] ?? 0 }}</td>
                            @foreach($data['month_arr'] as $month_id => $month_val)
                                @php
                                if(isset($fees_value[$month_id]['paid']))
                                {
                                    echo "<td>".$fees_value[$month_id]['paid']."</td>";
                                    $var = "amount_paid_".$month_id;
                                    $$var += $fees_value[$month_id]['paid'];                                    
                                }
                                else
                                {
                                    echo "<td>0</td>";
                                }                                                                                                   
                                @endphp
                            @endforeach 
                            @php
                            $total_paid += $fees_value['-']['paid'] ?? 0;
                            $total_breakoff += $fees_value['-']['bk'] ?? 0;
                            if(isset($fees_value['fine']))
                            {
                                $total_fine += $fees_value['fine'];
                                $fine = $fees_value['fine'];
                            }else{
                                $fine = 0;
                            }
                            if(isset($fees_value['discount']))
                            {    
                                $total_discount += $fees_value['discount'];
                                $discount = $fees_value['discount'];
                            }else{
                                $discount = 0;
                            }
                            @endphp
                            <td>{{$fine}}</td>                          
                            <td>{{$discount}}</td>                          
                            <td style="background-color:#7befef;">{{$fees_value['-']['paid'] ?? 0 }}</td>                          
                            @foreach($data['month_arr'] as $month_id => $month_val)
                                @php
                                if(isset($fees_value[$month_id]['remain']))
                                {
                                    echo "<td>".$fees_value[$month_id]['remain']."</td>";
                                    $var1 = "amount_unpaid_".$month_id;
                                    $$var1 += $fees_value[$month_id]['remain'];                                    
                                }
                                else
                                {
                                    echo "<td>0</td>";
                                }                                                                                                   
                                @endphp                                                        
                            @endforeach
                            <td style="background-color:#7befef;">{{$fees_value['-']['remain'] ?? 0}}</td>  
                            @php
                            $total_unpaid += $fees_value['-']['remain'] ?? 0;
                            @endphp                            
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
                            <td>Total</td>
                            <td>{{$total_breakoff}}</td>
                            @foreach($data['month_arr'] as $month_id => $month_val)
                            @php
                                $var = "amount_paid_".$month_id;
                                echo "<td>".$$var."</td>";                                    
                            @endphp
                            @endforeach
                            <td>{{$total_fine}}</td>
                            <td>{{$total_discount}}</td>
                            <td>{{$total_paid}}</td>
                            @foreach($data['month_arr'] as $month_id => $month_val)
                            @php
                                $var1 = "amount_unpaid_".$month_id;
                                echo "<td>".$$var1."</td>";                                    
                            @endphp
                            @endforeach
                            <td>{{$total_unpaid}}</td>
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
    // Setup - add a text input to each footer cell    

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
                title: 'Fees Overall Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Fees Overall Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Fees Overall Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Fees Overall Report' },             
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

        //$("#grade").attr('required', true);
        //$("#standard").attr('required', true);

} );
</script>
@include('includes.footer')
