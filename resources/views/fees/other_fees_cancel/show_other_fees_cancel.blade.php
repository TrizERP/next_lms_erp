@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style type="text/css">
    #overlay {
        position: fixed; /* Sit on top of the page content */
        display: none; /* Hidden by default */
        width: 100%; /* Full width (cover the whole page) */
        height: 100%; /* Full height (cover the whole page) */
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5); /* Black background with opacity */
        z-index: 2; /* Specify a stack order in case you're using a different order for other elements */
        cursor: pointer; /* Add a pointer on hover */
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Other Fees Cancel</h4>
            </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = $enrollment_no = $first_name = $last_name = $mobile_no = $uniqueid = $from_date = '';
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
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('other_fees_cancel.create') }}">
                @csrf
                <div class="row">
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-4 form-group">
                        <label for="other_fees_title">Other Fees Title(Head)</label>
                        <select name="other_fees_title" id="other_fees_title" class="form-control" required="required">
                            <option value="">Select Other Fees Title</option>
                            @foreach($data['other_fees_title'] as $key => $value)
                                <option value="{{$value['id']}}"
                                        @if(isset($data['other_fees_title_selected']))
                                        @if($data['other_fees_title_selected'] == $value['id'])
                                        selected='selected'
                                    @endif
                                @endif
                                >{{$value['display_name']}}</option>
                            @endforeach
                        </select>
                    </div>
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
                    <div class="col-sm-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
        @if(isset($data['student_data']))
            @php
                if(isset($data['student_data'])){
                    $student_data = $data['student_data'];
                    $finalData = $data;
                }
            @endphp

            <div class="card">
                <form method="POST" action="{{ route('other_fees_cancel.store') }}" id="submit_form">
                    @csrf
                    <div class="row mt-5">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table class="table table-box table-bordered">
                                    <thead>
                                    <tr>
                                        <th><input id="checkall" name="checkall" onchange="checkAll(this);"
                                                   type="checkbox"></th>
                                        <th>Sr.No.</th>
                                        <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                                        <th>{{ App\Helpers\get_string('grno','request')}}</th>
                                        <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                        <th>{{ App\Helpers\get_string('division','request')}}</th>
                                        <th>Mobile</th>
                                        <th>{{ App\Helpers\get_string('studentquota','request')}}</th>
                                        <th>Other Fees Head</th>
                                        <th>Paid Amount</th>
                                        <th>Receipt No</th>
                                        <th>Date of Cancel
                                            <input type="checkbox" value="Y" name="chkbx_date" id="chkbx_date" onclick="fill_all_date_of_cancel(this);"/>
                                            <br/>
                                            <INPUT type="text" name="txtbx_date" id="txtbx_date" value="@php echo date('d-m-Y'); @endphp"/>
                                        </th>
                                        <th>Reason of Cancel
                                            <input type="checkbox" value="Y" name="chkbx_reason" id="chkbx_reason"
                                                   onclick="fill_all_reason_of_cancel(this);"/>
                                            <br/>
                                            <INPUT type="text" name="txtbx_reason" id="txtbx_reason" value=""/>
                                        </th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $j=1;
                                        @endphp
                                    @foreach($student_data as $key => $data)
                                    <tr>
                                        <td><input id="students" value="{{$data['id']}}" name="students[]" type="checkbox"></td>
                                        <td>{{$j}}</td>
                                        <td>{{$data['stu_name']}}</td>
                                        <td>{{$data['enrollment_no']}}</td>
                                        <td>{{$data['std_name']}}</td>
                                        <td>{{$data['div_name']}}</td>
                                        <td>{{$data['mobile']}}</td>
                                        <td>{{$data['stu_quota']}}</td>
                                        <td>{{$data['display_name']}}</td>
                                        <td>{{$data['deduction_amount']}}</td>
                                        <td>
                                            <button type="button" class="btn btn-info float-right" data-toggle="modal" onclick="javascript:add_data({{$data['id']}},{{$data['student_id']}},'{{$data['receipt_id']}}');">{{$data['receipt_id']}}</button>
                                            <input type="hidden" name="fees_html_{{$data['id']}}" id="fees_html_{{$data['id']}}" value="{{$data['paid_fees_html']}}">
                                        </td>
                                        <td>
                                            <input type="text" id="date_of_cancel[{{$data['id']}}]" name="date_of_cancel[{{$data['id']}}]"
                                                   class="form-control mydatepicker cls_txtbx_date_of_cancel"
                                                   autocomplete="off">
                                        </td>
                                        <td>
                                            <input type="text" id="reason_of_cancel[{{$data['id']}}]" name="reason_of_cancel[{{$data['id']}}]"
                                                   class="form-control cls_txtbx_reason_of_cancel" autocomplete="off">
                                        </td>
                                    </tr>
                                    @php
                                        $j++;
                                    @endphp
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="hidden" name="division_id"
                                       @if(isset($finalData['division_id'])) value="{{$finalData['division_id']}}" @endif>
                                <input type="hidden" name="standard_id"
                                       @if(isset($finalData['standard_id'])) value="{{$finalData['standard_id']}}" @endif>
                                <input type="hidden" name="other_fees_title"
                                       @if(isset($finalData['other_fees_title_selected'])) value="{{$finalData['other_fees_title_selected']}}" @endif>
                                <input type="submit" name="submit" value="Submit" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>


<!--Modal: Add ChapterModal-->
<div id="printThis">
    <div class="modal fade right modal-scrolling" id="ChapterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
        <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document" style="min-width: 75%;">
            <!--Content-->
            <div class="modal-content">
                <!--Header-->
                <div class="modal-header">
                    <h5 class="modal-title" id="heading">Re-Print Receipt</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">x</span>
                    </button>
                </div>
                <!--Body-->
                <div class="modal-body">
                    <div class="row">
                        <div class="panel-body" style="min-width: 100% !important;">
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <input type="hidden" name="action" id="action" value="other_fees_re_receipt">
                                <input type="hidden" name="student_id" id="student_id" value="">
                                <input type="hidden" name="receipt_id_html" id="receipt_id_html" value="">
                                <input type="hidden" name="paper_size" id="paper_size" value="A5">
                                <div id="reprint_receipt_html">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Footer-->
                <div class="modal-footer" style="display: block !important;">
                    <div id="overlay" style="display:none;">
                        <center><p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page,
                                while the process is going on.</p><img
                                src="http://dev.triz.co.in/admin_dep/images/loader.gif"></center>
                    </div>
                    <center>
                        <button id="ajax_PDF" type="button" class="btn btn-primary">Print Receipt</button>
                    </center>
                </div>
            </div>
            <!--/.Content-->
        </div>
    </div>
</div>
<!--Modal: Add ChapterModal-->

@include('includes.footerJs')
<script>

    // document.getElementById("btnPrint").onclick = function ()
    // {
    //         PrintDiv("reprint_receipt_html");
    // }

    // function PrintDiv(divName) {
    //     var divToPrint = document.getElementById(divName);
    //     var popupWin = window.open('', '_blank', 'width=300,height=300');
    //     popupWin.document.open();
    //     popupWin.document.write('<html>');
    //     popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
    //     popupWin.document.close();
    // }

    function add_data(fees_collect_id,student_id,receipt_no) {
        var fees_content = $('#fees_html_' + fees_collect_id).val();
        // alert(fees_content);
        $('#reprint_receipt_html').html(fees_content);
        $('#student_id').val(student_id);
        $('#receipt_id_html').val(receipt_no);
        $('#ChapterModal').modal('show');

    }

    $('#submit_form').submit(function () {
        var selected_stud = $("input[name='students[]']:checked").length;
        if (selected_stud == 0) {
            alert("Please Select Atleast One Student");
            return false;
        } else {
            return true;
        }
    });

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

    function fill_all_date_of_cancel(element) {
        element = document.getElementById('chkbx_date');
        txtbx_date_element = document.getElementById('txtbx_date');

        if (element.checked == true) {
            all_aod_txtbxs = document.getElementsByClassName('cls_txtbx_date_of_cancel');
            for (var i = 0; i < all_aod_txtbxs.length; i++) {
                all_aod_txtbxs.item(i).value = txtbx_date_element.value;
            }
        } else {
            all_aod_txtbxs = document.getElementsByClassName('cls_txtbx_date_of_cancel');
            for (var i = 0; i < all_aod_txtbxs.length; i++) {
                all_aod_txtbxs.item(i).value = '';
            }
        }
    }

    function fill_all_reason_of_cancel(element) {
        element = document.getElementById('chkbx_reason');
        txtbx_reason_element = document.getElementById('txtbx_reason');

        if (element.checked == true) {
            all_aod_txtbxs = document.getElementsByClassName('cls_txtbx_reason_of_cancel');
            for (var i = 0; i < all_aod_txtbxs.length; i++) {
                all_aod_txtbxs.item(i).value = txtbx_reason_element.value;
            }
        } else {
            all_aod_txtbxs = document.getElementsByClassName('cls_txtbx_reason_of_cancel');
            for (var i = 0; i < all_aod_txtbxs.length; i++) {
                all_aod_txtbxs.item(i).value = '';
            }
        }
    }
</script>
@include('includes.footer')
