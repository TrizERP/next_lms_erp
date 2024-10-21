@extends('layout')
@section('container')
<style>
    .tdwidth{
        width:8% !important;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Update Admission</h4> </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <span class="d-inline-block mb-2" tabindex="0" data-toggle="tooltip" title="Once student is enrolled in System it cannot be edited & deleted. ">
                      <button class="btn btn-danger" style="pointer-events: none;" type="button" disabled="">Note</button>
                    </span>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('admission_registration_v1.store') }}" enctype="multipart/form-data" method="post"  id="myForm" onsubmit="return validateForm()">
                @csrf
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox" onclick="checkAll('ckbox1')">  </th>
                                    <th>Enq.No</th>
                                    <th>Name</th>
                                    <th>DOB</th>
                                    <th>MOB</th>
                                    <!--<th>MOB</th>-->
                                    <th>Email</th>
                                    <th>Class No</th>
                                    <th>SIB</th>
                                    <th>H.N.(1,2,3)</th>
                                    <th>H.N.Remakrs</th>
                                    <th>Activity</th>
                                    <th>P.INT.</th>
                                    <th>P.INT. Date</th>
                                    <th>P.INT. Time</th>
                                    <th>P.Attandance</th>
                                    <th>P.Remarks</th>
                                    <th>CONF.</th>
                                    <th>CONF. Date</th>
                                    <!-- <th>CONF. Time</th> -->
                                    <th>PAID</th>
                                    <th class="text-center">Transport Fees</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                                @foreach($data['data'] as $key => $value)
                                <tr>
                                <td><input type="checkbox" name="students[{{$value['id']}}]" class="ckbox1"></td>
                                    <td>{{$value['enquiry_no']}}</td>
                                    <td>{{$value['full_name']}}</td>
                                    <td>{{$value['date_of_birth']}}</td>
                                    <td>
                                        {{$value['mobile']}}
                                        <input type="hidden" name="students[{{$value['id']}}][mobile]" class="mobile form-control" disabled="true" value="{{$value['mobile']}}" autocomplete="off">
                                    </td>
                                    <!--<td>{{$value['mobile2']}}</td>-->
                                    <td>{{$value['email']}}
                                    <input type="hidden" name="students[{{$value['id']}}][email]" class="email form-control" disabled="true" value="{{$value['email']}}" autocomplete="off">
                                    </td>
                                    <td>{{$value['std_name']}}</td>
                                    @php 
                                        $siblingsData = [];
                                        $siblings_id = explode(',', $value['siblings']);
                                        if (!empty($siblings_id) && isset($value['siblings']) && !empty($value['siblings'])) {
                                            foreach ($siblings_id as $sk => $sv) {
                                                $studentData = App\Helpers\SearchStudent("", "", "", "", "", "", "", "", "", "", $sv);
                                                $siblingsData[] = $studentData[0] ?? '';
                                            }
                                        }
                                    @endphp
                                    <td>
                                        @if(!empty($siblingsData))
                                        @foreach($siblingsData as $skey=>$svalue)
                                        {{$skey+1}}. {{$svalue['first_name'].' '.$svalue['middle_name'].' '.$svalue['last_name']}}<br>
                                        @endforeach
                                        @endif
                                    </td>
                                    <td class="tdwidth">
                                        <select name="students[{{$value['id']}}][hn]" class="hn form-control" disabled="true">
                                            <option value="">select</option>
                                            @foreach($data['hnArr'] as $key => $hnValue) 
                                                <option value="{{$hnValue}}" @if(isset($value['h_n']) && $value['h_n']==$hnValue) selected @endif>{{$hnValue}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="hidden" name="students[{{$value['id']}}][enquiry_no]" class="enquiry_no form-control" disabled="true" value="{{$value['enquiry_no']}}" autocomplete="off">

                                        <input type="hidden" name="students[{{$value['id']}}][admission_standard]" class="admission_standard form-control" disabled="true" value="{{$value['admission_standard']}}" autocomplete="off">

                                        <input type="text" name="students[{{$value['id']}}][hn_remarks]" class="hn_remarks form-control" disabled="true" placeholder="Enter the Remarks" autocomplete="off"  @if(isset($value['h_n_remarks'])) value="{{$value['h_n_remarks']}}" @endif>
                                    </td>
                                    <td>
                                        <input type="text" name="students[{{$value['id']}}][activity]" class="activity form-control" disabled="true" placeholder="Enter the Marks" autocomplete="off" @if(isset($value['activity'])) value="{{$value['activity']}}" @endif>
                                    </td>
                                    <td class="tdwidth">  
                                        <select name="students[{{$value['id']}}][pint]" class="pint form-control" disabled="true">
                                            <option value="">select</option>
                                            @foreach($data['pIntArr'] as $key => $pintValue)
                                                <option value="{{$pintValue}}" @if(isset($value['p_int']) && $value['p_int']==$pintValue) selected @endif>{{$pintValue}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="students[{{$value['id']}}][pint_date]" class="pint_date form-control mydatepicker" disabled="true" autocomplete="off" @if(isset($value['p_int_date'])) value="{{$value['p_int_date']}}" @endif>
                                    </td>
                                    <td>
                                        <input type="time" name="students[{{$value['id']}}][pint_time]" class="pint_time form-control" disabled="true" autocomplete="off" @if(isset($value['p_int_time'])) value="{{$value['p_int_time']}}" @endif>
                                    </td>
                                    <!-- // 15-10-2024 -->
                                    <td>
                                        <input type="text" name="students[{{$value['id']}}][p_int_attandance]" class="p_int_attandance form-control" disabled="true" autocomplete="off" @if(isset($value['p_int_attandance'])) value="{{$value['p_int_attandance']}}" @endif>
                                    </td>
                                    <td>
                                        <textarea name="students[{{$value['id']}}][p_int_remark]" class="p_int_remark form-control" disabled="true" autocomplete="off" >@if(isset($value['p_int_remark'])){{$value['p_int_remark']}}@endif</textarea>
                                    </td>
                                    <!-- // 15-10-2024 -->

                                    <td class="tdwidth">  <select name="students[{{$value['id']}}][conf]" class="conf form-control" disabled="true">
                                            <option value="">select</option>
                                            @foreach($data['confArr'] as $key => $cvalue)
                                            <option value="{{$cvalue}}" @if(isset($value['confi']) && $value['confi']==$cvalue) selected @endif>{{$cvalue}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="students[{{$value['id']}}][conf_date]" class="conf_date form-control mydatepicker" disabled="true" autocomplete="off" @if(isset($value['confi_date'])) value="{{$value['confi_date']}}" @endif>
                                    </td>
                                    {{-- <td>
                                        <input type="time" name="students[{{$value['id']}}][conf_time]" class="conf_time form-control" disabled="true" autocomplete="off" @if(isset($value['confi_time'])) value="{{$value['confi_time']}}" @endif>
                                    </td>  --}}
                                    <td class="tdwidth">  
                                        <select name="students[{{$value['id']}}][paid]" class="paid form-control" disabled="true">
                                            <option value="">select</option>
                                            @foreach($data['yesNo'] as $key => $yesNoValue)
                                                <option value="{{$yesNoValue}}" @if(isset($value['paid']) && $value['paid']==$yesNoValue) selected @endif>{{$yesNoValue}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="tdwidth">  
                                        <select name="students[{{$value['id']}}][transport]" class="transport form-control" disabled="true">
                                            <option value="">select</option>
                                            @foreach($data['yesNo'] as $key => $yesNoValue)
                                                <option value="{{$yesNoValue}}" @if(isset($value['transport_fees']) && $value['transport_fees']==$yesNoValue) selected @endif>{{$yesNoValue}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                @php
                            $j++;
                            @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-12">
                        <center>
                            <input type="submit" value="Save" class="btn btn-primary">
                        </center>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
 $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [-1,100, 500, 1000],
                ['Show All','100', '500', '1000']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Admission Registration Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Admission Registration Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Admission Registration Report'},
                {
                    extend: 'print', 
                    text: ' PRINT', 
                    title: 'Admission Registration Report',
                    customize: function (win) {
                        $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                        $('#all_values').addClass('flex-on-print');
                    }
                },
                'pageLength'
            ],
        });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search(this.value)
                        .draw();
                }
            });
        });
    });

</script>
<script>
    function validateForm() {
    var checkboxes = document.querySelectorAll('input[name^="students["]');
    var anyChecked = false;
    
    checkboxes.forEach(function(checkbox) {
        if (checkbox.checked) {
            anyChecked = true;
        }
    });
    
    if (!anyChecked) {
        alert("Please select at least one checkbox");
        return false; 
    }
    
    return true;
}
function checkAll(chkName){
    $('.'+chkName).each(function() {
            $(this).prop('checked', !$(this).prop('checked'));
        });
   }

$(function () {

    $(".ckbox1").on("click", function () {
        var row = $(this).closest('tr');
        var fields = ['hn', 'hn_remarks', 'activity', 'pint', 'conf', 'paid', 'transport','enquiry_no','pint_date','pint_time','conf_date','conf_time','mobile','email','admission_standard','p_int_remark','p_int_attandance'];

        // Iterate over each field and toggle the disabled property
        fields.forEach(function(field) {
            row.find('.' + field).prop('disabled', function (i, v) {
                return !v;
            });
        });
    });

});
</script>
@include('includes.footer')
@endsection
