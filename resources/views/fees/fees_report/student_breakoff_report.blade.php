@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Student Breakoff Report</h4> </div>
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
                <form action="{{ route('show_stduent_breakoff_report') }}" enctype="multipart/form-data" class="row" method="post">
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

                    <!-- @if(isset($data['months']))
                        <div class="col-md-4 form-group">
                            <label>Months:</label>
                            <select name="month[]" class="form-control" multiple="multiple">
                                @foreach($data['months'] as $id => $value)
                                    <option value="{{$id}}" 
                                    @if(isset($data['month']) && in_array($id, $data['month']))
                                        SELECTED
                                    @endif
                                >{{$value}}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif -->
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
                <table class="table table-striped border table-bordered" id="student_breakoff_report" border="1" style="border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>{{ App\Helpers\get_string('grno','request')}}</th>
                            <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{ App\Helpers\get_string('std/div','request')}}</th>
                            <th>{{ App\Helpers\get_string('studentquota','request')}}</th>
                            <th>{{ App\Helpers\get_string('uniqueid','request')}}</th>
                            @if (isset($data['fees_titles']))
                                @foreach ($data['fees_titles'] as $key => $value)
                                    <th>{{ $value->display_name }}</th>
                                @endforeach
                            @endif
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $i=1;
                    $total_breakoff = 0;
                    $total_array = array();
                    $final_total_amount = 0;
                    @endphp

                    @if(isset($data['fees_data']))
                        @foreach($fees_data as $key => $fees_value)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>{{$fees_value['enrollment']}}</td>
                            <td>{{$fees_value['name']}}</td>
                            <td>{{$fees_value['stddiv']}}</td>
                            <td>{{$fees_value['stu_quota']}}</td>
                            <td>{{$fees_value['uniqueid']}}</td>
                            @php
                                $totalAmount = 0; // Initialize the total amount variable
                            @endphp
                            @foreach ($data['fees_titles'] as $value)
                                @php
                                    $fees_title = $value->fees_title;
                                    $display_name = $value->display_name;
                                    $amount = $fees_value['final_fee'][$display_name] ?? 0;

                                    // Add the current amount to the total amount
                                    $totalAmount += $amount;
                                @endphp
                                <td>{{$amount}}</td>
                            @endforeach
                            <td> {{ $totalAmount }}</td>
                            @php
                                $final_total_amount += $totalAmount; // Add the total for the current row to the final total
                            @endphp
                        </tr>
                        @endforeach
                        <tr class="font-weight-bold">
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
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
                            <td>{{ $final_total_amount }}</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
                <center>
                    <button onclick="exportTableToExcel('student_breakoff_report', 'Student Breakoff Report')" class="btn btn-success mt-2">Excel Export</button>
                </center>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdnjs.cloudflare.com/ajax/libs/TableExport/5.2.0/tableexport.min.js"></script>
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
