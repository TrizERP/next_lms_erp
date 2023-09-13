@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Headwise Pending Report</h4> </div>
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
                <form action="{{ route('fees_headwise_pending_report.create') }}" enctype="multipart/form-data" class="row">
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
                <table class="table table-striped border table-bordered" id="overall_head_pending" border="1" style="border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th rowspan=3 class="align-middle">Sr No.</th>
                            <th rowspan=3 class="align-middle">{{ App\Helpers\get_string('grno','request')}}</th>
                            <th rowspan=3 class="align-middle">{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th rowspan=3 class="align-middle">{{ App\Helpers\get_string('std/div','request')}}</th>
                            <th rowspan=3 class="align-middle">{{ App\Helpers\get_string('studentquota','request')}}</th>
                            <th rowspan=3 class="align-middle">Total Breakoff</th>
                            <th rowspan=3 class="align-middle">Total Discount</th>
                            @php
                            if(isset($data['bk_title_months_array']))
                            {
                                $count_colspan = $data['count_of_array'];
                                $count_colspan = $count_colspan + 1;
                                $colspan = 'colspan="'.$count_colspan.'" ';
                            @endphp 
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
                                @foreach($data['bk_title_months_array'] as $main_head => $child_head)
                                    @if(count($child_head) > 0)
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
                    $total_breakoff = $total_paid = $total_unpaid = $total_discount = 0;
                    $total_array = array();
                    @endphp
                                           
                    @if(isset($data['fees_data']))
                        @foreach($fees_data as $key => $fees_value)      
                        <tr>
                            <td>{{$j}}</td>                            
                            <td>{{$fees_value['enrollment']}}</td>
                            <td>{{$fees_value['name']}}</td>
                            <td>{{$fees_value['stddiv']}}</td>
                            <td>{{$fees_value['stu_quota']}}</td>
                            <td>{{$fees_value['-']['bk'] ?? 0}}</td>

                            @php
                            $total_paid += $fees_value['-']['paid'] ?? 0;
                            $total_breakoff += $fees_value['-']['bk'] ?? 0;
                            if(isset($fees_value['discount']))
                            {    
                                $total_discount += $fees_value['discount'];
                                $discount = $fees_value['discount'];
                            }else{
                                $discount = 0;
                            }
                            @endphp                       
                            <td>{{$discount}}</td>

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
                                            <td>@if(isset($total_array['UNPAID'])){{$total_array['UNPAID'][$main_fees_title[1]][$month_id]}}@else 0 @endif</td>
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
                    <button onclick="exportTableToExcel('overall_head_pending', 'Fees Overall Headwise Pending Report')" class="btn btn-success mt-2">Excel Export</button>
                </center>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
    // $('#grade').attr('required', true);
    // $('#standard').attr('required', true);
</script>
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
@include('includes.footer')
