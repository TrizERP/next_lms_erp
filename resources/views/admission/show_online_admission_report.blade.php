@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Online Admission Report</h4>
            </div>
        </div>
        @if(isset($data['report_data']))
            @php
                if(isset($data['report_data'])){
                    $report_data = $data['report_data'];
                    $finalData = $data;
                }
            @endphp
            <div class="card">
                <form method="POST" enctype="multipart/form-data" action="{{ route('ajax_AdmissionConfirmReport') }}">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                {!! App\Helpers\get_school_details("","","") !!}
                                <table id="example" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                        <th>Sr.No.</th>
                                        <th>Syear</th>
                                        <th>Eligible Status</th>
                                        <th>Token</th>
                                        <th>Birth Certificate</th>
                                        <th>Student Name</th>
                                        <th>Admission Std</th>
                                        <th>DOB</th>
                                        <th>Age</th>
                                        <th>Student Quota</th>
                                        <th>Otp</th>
                                        <th>Mobile</th>
                                        <th>Address</th>
                                        <th>Father Name</th>
                                        <th>Email</th>
                                        <th>Father Aadhaar</th>
                                        <th>Mother Aadhaar</th>
                                        <th>Sibling Details</th>
                                        <th>Admission for one child/twins</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $j=1;

                                        @endphp
                                    @foreach($report_data as $key => $data)
                                        @php
                                    $disable = $style = '';
                                    if($data['eligible_status'] == 'Yes')
                                    {
                                        $disable = 'disabled';
                                        $style = 'style="background-color: #d5f2b1;"';
                                    }
                                    @endphp
                                    <tr @php echo $style; @endphp>
                                        <td><input id="{{$data['CHECKBOX']}}" value="{{$data['CHECKBOX']}}" name="students[]" type="checkbox" @php echo $disable; @endphp ></td>
                                        <td>{{$j}}</td>
                                        <td>{{$data['syear']}}</td>
                                        <td>{{$data['eligible_status']}}</td>
                                        <td><a target="blank" href="https://www.erp.triz.co.in/New_Admission/new_admission_receipt/{{$data['token']}}.pdf" style="color: #007bff;">{{$data['token']}}</a></td>
                                        <td><a target="blank" href="../storage/student_document/{{$data['birth_certificate']}}" style="color: #1976d2;">{{$data['birth_certificate']}}</a></td>
                                         <td>{{$data['child_name']}}</td>
                                        <td>{{$data['admission_std']}}</td>
                                        <td>{{date('d-m-Y',strtotime($data['date_of_birth']))}}</td>
                                        <td>{{$data['age']}}</td>
                                        <td>{{$data['student_quota']}}</td>
                                        <td>{{$data['otp']}}</td>
                                        <td>{{$data['mobile']}}</td>
                                        <td>{{$data['address']}}</td>
                                        <td>{{$data['father_name']}}</td>
                                        <td>{{$data['mail']}}</td>
                                        <td>{{$data['father_adhar']}}</td>
                                        <td>{{$data['mother_adhar']}}</td>
                                        <td>{{$data['sibling_details']}}</td>
                                        <td>{{$data['admission_for_child_twins']}}</td>
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
                                <input type="submit" name="submit" value="Submit" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
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
<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Online Admission Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Online Admission Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Online Admission Report'},
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Online Admission Report',
                    customize: function (win) {
                        $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
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
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>

@include('includes.footer')
