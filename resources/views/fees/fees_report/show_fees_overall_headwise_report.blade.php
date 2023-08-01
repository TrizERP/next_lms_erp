@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Overall Headwise Report</h4> </div>
            </div>
        @php
            $grade_id = $standard_id = $division_id = $enrollment_no = $first_name = $last_name = $mobile_no = $uniqueid = $from_date = $to_date = '';

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
                <form action="{{ route('fees_overall_headwise_report.create') }}" enctype="multipart/form-data" class="row">
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
                        <label>{{ App\Helpers\get_string('uniqueid','request')}}</label>
                        <input type="text" id="enrollment_no" name="enrollment_no" value="{{$enrollment_no}}" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile No.</label>
                        <input type="text" id="mobile_no" value="{{$mobile_no}}" name="mobile_no" class="form-control">
                    </div>                    
                    <div class="col-md-4 form-group">
                        <label>{{ App\Helpers\get_string('grno','request')}}</label>
                        <input type="text" id="uniqueid" value="{{$uniqueid}}" name="uniqueid" class="form-control">
                    </div>
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                    @if(isset($data['months']))
                        <div class="col-md-4 form-group">
                            <label>Months:</label>
                            <select name="month[]" class="form-control" required="required" multiple="multiple">
                                @foreach($data['months'] as $key => $value)
                                    <option value="{{$key}}" @if(isset($data['month']))
                                    @if(in_array($key,$data['month']))
                                        SELECTED
                                    @endif
                                    @endif>{{$value}}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off" >
                    </div>
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off" required="required">
                    </div>

                    @if(isset($data['fees_heads']))
                        <div class="col-md-4 form-group ml-0 mr-0">
                            <label>Fees Heads:</label>
                            <select name="fees_head[]" class="form-control" required="required" multiple="multiple">
                                @foreach($data['fees_heads'] as $key => $value)
                                    <option value="{{$key}}" @if(isset($data['fees_head']))
                                    @if(in_array($key,$data['fees_head']))
                                        SELECTED
                                    @endif
                                    @endif>{{$value}}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

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
                <table class="table table-striped border table-bordered" id="overall_head" border="1" style="border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th rowspan=3 class="align-middle">Sr No.</th>
                            <th rowspan=3 class="align-middle">{{ App\Helpers\get_string('grno','request')}}</th>
                            <th rowspan=3 class="align-middle">{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th rowspan=3 class="align-middle">{{ App\Helpers\get_string('std/div','request')}}</th>
                            <th rowspan=3 class="align-middle">{{ App\Helpers\get_string('studentquota','request')}}</th>
                            <th rowspan=3 class="align-middle">Status</th>
                            <th rowspan=3 class="align-middle">Total Breakoff</th>
                            <th rowspan=3 class="align-middle">Total Discount</th>
                            @php
                            if(isset($data['bk_title_months_array']))
                            {
                                $count_colspan = $data['count_of_array'];   
                                $count_colspan = $count_colspan + 1;
                                $colspan = 'colspan="'.$count_colspan.'" ';
                            @endphp 
                                <th @php echo $colspan; @endphp class="text-center">Received</th>
                                <th @php echo $colspan; @endphp class="text-center">Pending</th>
                            @php 
                            }
                            @endphp                                                    
                        </tr>
                        <tr>    
                            @if(isset($data['bk_title_months_array']))

                                @foreach($data['bk_title_months_array'] as $main_head => $child_head)
                                    @if(count($child_head) > 0)                                    
                                        @php
                                            $main_fees_title = explode('/',$main_head);
                                            $count_colspan_inner = count($child_head);
                                            $colspan_inner = 'colspan="'.$count_colspan_inner.'" ';
                                        @endphp
                                        <th @php echo $colspan_inner; @endphp class="text-center">@if($main_fees_title[0]=='') {{$main_fees_title[1]}} @else {{$main_fees_title[0]}} @endif</th>
                                    @else
                                        <th rowspan=2>{{$main_fees_title[0]}}</th>    
                                    @endif
                                @endforeach
                                <th rowspan=2 class="align-middle">Total Received</th>

                                @foreach($data['bk_title_months_array'] as $main_head => $child_head)
                                    @if(count($child_head) > 0)  
                                        @php
                                            $main_fees_title = explode('/',$main_head);
                                            $count_colspan_inner = count($child_head);
                                            $colspan_inner = 'colspan="'.$count_colspan_inner.'" ';
                                        @endphp                                  
                                        <th @php echo $colspan_inner; @endphp class="text-center">{{$main_fees_title[0]}}</th>
                                    @else
                                        <th rowspan=2>{{$main_fees_title[0]}}</th>    
                                    @endif
                                @endforeach
                                <th rowspan=2 class="align-middle">Total Pending</th>
                            @endif
                        </tr>
                        <tr>
                            @if(isset($data['bk_title_months_array']))
							<!-- for Recieved fees -->
                                @foreach($data['bk_title_months_array'] as $main_head => $child_head)
                                    @if(is_array($child_head))
                                        @foreach($child_head as $child_key => $child_val)
                                            <th>{{$child_val}}0</th>   
                                        @endforeach
                                    @endif
                                @endforeach
								<!-- for panding fees -->
								@foreach($data['bk_title_months_array'] as $main_head => $child_head)
                                    @if(is_array($child_head))
                                        @foreach($child_head as $child_key => $child_val)
                                            <th>{{$child_val}}</th>   
                                        @endforeach
                                    @endif
                                @endforeach
                            @endif
                        </tr>

                    </thead>
                    <tbody>
                    @php
                    $j=1;
                    $total_breakoff = $total_paid = $total_unpaid = $total_discount =  $discount = $status = 0;
                    $total_array = array();
                    @endphp
                                           
                    @if(isset($data['fees_data']))
                        @foreach($fees_data as $key => $fees_value) 
                            @php
                            
							if(isset($fees_value['-'])){
                            $total_paid += $fees_value['-']['paid'] ?? 0;
                            $total_breakoff += $fees_value['-']['bk'] ?? 0;
                            if(isset($fees_value['discount']))
                            {    
                                $total_discount += $fees_value['discount'];
                                $discount = $fees_value['discount'];
                            }else{
                                $discount = 0;
                            }
                            $status = $fees_value['-']['paid'] - $discount;
							}
                            @endphp                    
                        <tr>
                            <td>{{$j}}</td>
                            <td>{{$fees_value['enrollment']}}</td>
                            <td>{{$fees_value['name']}}</td>
                            <td>{{$fees_value['stddiv']}}</td>
                            <td>{{$fees_value['stu_quota']}}</td>
                            <!--<td>@if($status != 0) In-Active @else Active @endif</td>-->
                            <td>{{$fees_value['student_status']}}</td>
                            <td>{{$fees_value['-']['bk'] ?? 0}}</td>

                                            
                            <td>{{$discount}}</td>

                            @if(isset($data['bk_title_months_array']))
                                @foreach($data['bk_title_months_array'] as $main_head => $child_head)
                                    @php
                                        $main_fees_title = explode('/',$main_head);
                                    @endphp
                                    @if(count($child_head) > 0)
                                        @foreach($child_head as $month_id => $child_val) 
                                            @if(isset($fees_value['paid_fees'][$main_fees_title[1]][$month_id]))
                                                @php
                                                    $temp = 0;
                                                    if(isset($total_array['PAID'][$main_fees_title[1]][$month_id])){
                                                        $temp = $total_array['PAID'][$main_fees_title[1]][$month_id];
                                                    }

                                                    $new_var = $temp + $fees_value['paid_fees'][$main_fees_title[1]][$month_id];

                                                    $total_array['PAID'][$main_fees_title[1]][$month_id] = $new_var;

                                                @endphp
                                                <td>{{$fees_value['paid_fees'][$main_fees_title[1]][$month_id]}}</td>
                                            @else
                                                 @php
                                                    $temp = 0;
                                                    if(isset($total_array['PAID'][$main_fees_title[1]][$month_id])){
                                                        $temp = $total_array['PAID'][$main_fees_title[1]][$month_id];
                                                    }

                                                    $new_var = $temp + 0;

                                                    $total_array['PAID'][$main_fees_title[1]][$month_id] = $new_var;

                                                @endphp
                                                <td>0</td> 
                                            @endif      
                                        @endforeach
                                    @endif 
                                @endforeach
                            @endif 

                           

                           <td>{{ isset($fees_value['-']['paid']) ? ($fees_value['-']['paid'] - $discount) : 0 }}</td>

                            @if(isset($data['bk_title_months_array']))
                                @foreach($data['bk_title_months_array'] as $main_head => $child_head)
                                    @php
                                        $main_fees_title = explode('/',$main_head);
                                    @endphp
                                    @if(count($child_head) > 0)
                                        @foreach($child_head as $month_id => $child_val) 
                                            @if(isset($fees_value['unpaid_fees'][$main_fees_title[1]][$month_id]))
                                                @php
                                                    $temp_unpaid = 0;
                                                    if(isset($total_array['UNPAID'][$main_fees_title[1]][$month_id])){
                                                        $temp_unpaid = $total_array['UNPAID'][$main_fees_title[1]][$month_id];
                                                    }

                                                    $new_var_unpaid = $temp_unpaid + $fees_value['unpaid_fees'][$main_fees_title[1]][$month_id];

                                                    $total_array['UNPAID'][$main_fees_title[1]][$month_id] = $new_var_unpaid;
                                                    
                                                @endphp
                                                <td>{{$fees_value['unpaid_fees'][$main_fees_title[1]][$month_id]}}</td>
                                            @else
                                               @php
                                                    $temp_unpaid = 0;
                                                    if(isset($total_array['UNPAID'][$main_fees_title[1]][$month_id])){
                                                        $temp_unpaid = $total_array['UNPAID'][$main_fees_title[1]][$month_id];
                                                    }

                                                    $new_var_unpaid = $temp_unpaid + 0;

                                                    $total_array['UNPAID'][$main_fees_title[1]][$month_id] = $new_var_unpaid;
                                                    
                                                @endphp
                                                <td>0</td> 
                                            @endif      
                                        @endforeach
                                    @endif 
                                @endforeach
                            @endif

                           
                            <td>{{$fees_value['-']['remain'] ?? 0}}</td>  
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
                            <td>{{$total_discount}}</td>

                            @if(isset($data['bk_title_months_array']))
                                @foreach($data['bk_title_months_array'] as $main_head => $child_head)
                                    @php
                                        $main_fees_title = explode('/',$main_head);
                                    @endphp                                   
                                    @if(count($child_head) > 0)
                                        @foreach($child_head as $month_id => $child_val)                                           
                                            <td>{{$total_array['PAID'][$main_fees_title[1]][$month_id] ?? 0}}</td>                                            
                                        @endforeach
                                    @endif 
                                @endforeach
                            @endif

                            <td>{{$total_paid}}</td>

                            @if(isset($data['bk_title_months_array']))
                                @foreach($data['bk_title_months_array'] as $main_head => $child_head)  
                                    @php
                                        $main_fees_title = explode('/',$main_head);
                                    @endphp                                 
                                    @if(count($child_head) > 0)
                                        @foreach($child_head as $month_id => $child_val)                                            
                                            <td>{{$total_array['UNPAID'][$main_fees_title[1]][$month_id] ?? 0}}</td>                                            
                                        @endforeach
                                    @endif 
                                @endforeach
                            @endif

                            <td>{{$total_unpaid}}</td>
                        </tr>                       
                        
                    @endif
                    </tbody>
                </table>
                <center>                    
                    <button onclick="exportTableToExcel('overall_head', 'Fees Overall Headwise Report')" class="btn btn-success mt-2">Excel Export</button>
                </center>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
    
    function exportTableToExcel(tableID, filename = '')
    {
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
        
        // Specify file name
        filename = filename?filename+'.xls':'excel_data.xls';
        
        // Create download link element
        downloadLink = document.createElement("a");
        
        document.body.appendChild(downloadLink);
        
        if(navigator.msSaveOrOpenBlob){
            var blob = new Blob(['\ufeff', tableHTML], {
                type: dataType
            });
            navigator.msSaveOrOpenBlob( blob, filename);
        }else{
            // Create a link to the file
            downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        
            // Setting the file name
            downloadLink.download = filename;
            
            //triggering the function
            downloadLink.click();
        }
    }

</script>
<script>
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
<script type="text/javascript">
    $('#grade').attr('required',false);
    $('#standard').attr('required',false);
</script>
@include('includes.footer')
