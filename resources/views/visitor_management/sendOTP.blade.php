@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
    .otp-inputs {
    display: flex;
    justify-content: center;
    gap: 10px;
}

.otp-input {
    width: 40px;
    height: 40px;
    text-align: center;
    font-size: 18px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Send OTP</h4>
            </div>
        </div>
    
        <div class="card">
        @if(isset($data['error']))
        <div class="alert alert-danger" role="alert">
           Something went wrong! Please go through proccess again.
        </div>
        @endif
            @if(isset($data['studentData']) &&!empty($data['studentData']))
            
                    <div class="table-responsive mt-20 tz-report-table">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                <th></th>
                                <th>{{ App\Helpers\get_string('studentname') }}</th>
                                <th>{{ App\Helpers\get_string('grno') }}</th>
                                <th>Father Name</th>
                                <th>Mother Name</th>
                                <th>{{ App\Helpers\get_string('std/div') }}</th>
                                <th>Mobile</th>
                                <th>Gender</th>
                                <th class="text-left">Address</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($data['studentData'] as $key=>$value)
                                <tr>
                                    <td><button class="btn btn-outline-success" data-toggle="modal" data-target="#exampleModal_{{$value['id']}}" onclick="sendotp({{$value['id']}},{{$value['mobile']}})">Send OTP</button></td>
                                    <td>{{$value['first_name']." ".$value['middle_name']." ".$value['last_name']}}</td>
                                    <td>{{$value['enrollment_no']}}</td>
                                    <td>{{$value['father_name'] ?? '-'}}</td>
                                    <td>{{$value['mother_name'] ?? '-'}}</td>
                                    <td>{{$value['standard_name'].'/'.$value['division_name']}}</td>
                                    <td>{{$value['mobile']}}</td>
                                    <td>{{$value['gender']}}</td>
                                    <td>{{$value['address']}}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
            
                @endif
        </div>
    </div>

    <!-- error message  -->
    @if(isset($data['error']))
    <!-- <div class="modal show" id="popup" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="iconbox text-center" style="height:60px;padding:16px">
                    <span class="mdi mdi-close-circle" style="font-size:4rem;color:red"></span>
                </div> 
                <div class="messagebox text-center">
                    <h6></b>Something went wrong!</b></h6>
                    <p>Seems like you missed something please</p>
                    <p>go through the steps again.</p>
                </div>
            </div>
            </div>
        </div>
    </div>
    <script>
       $(window).on('load', function() {
        $('#popup').modal('show');
    });
    </script> -->
    @endif
    <!-- error message end  -->
    @foreach($data['studentData'] as $key=>$value)
    <!-- Modal -->
    <div class="modal fade" id="exampleModal_{{$value['id']}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Varify OTP</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{route('confirmOtp')}}" method="post" class="row">
                    @csrf
                    <div class="col-md-12 form-group">
                        <label for="person_name">Person Name</label>
                        <input type="text" name="person_name" class="form-control">
                    </div>
                    <div class="col-md-12 form-group">
                        <label for="relation">Relation with Student</label>
                        <input type="text" name="relation" class="form-control">
                    </div>
                    <div class="col-md-12 form-group">
                      <center>
                            <p>A code has been sent to ******{{substr($value['mobile'],-4)}}</p>
                            <p>OTP <span id="hidden_otp">******</span> <span class="mdi mdi-eye-off-outline" id="toggleOTP"></span></p>
                            <input type="hidden" name="otp" id="optVal">
                            <input type="hidden" name="student_id" value="{{$value['id']}}">
                            <input type="hidden" name="student_name" value="{{$data['student_name']}}">
                            <input type="hidden" name="mobile" value="{{$data['mobile']}}">
                      </center>
                    </div>
                    <div class="col-md-12">
                        <div class="otp-inputs">
                            <input type="text" maxlength="1" class="otp-input" id="otp-1" name="enteredOTP[]" required autocomplete="off">
                            <input type="text" maxlength="1" class="otp-input" id="otp-2" name="enteredOTP[]" required autocomplete="off">
                            <input type="text" maxlength="1" class="otp-input" id="otp-3" name="enteredOTP[]" required autocomplete="off">
                            <input type="text" maxlength="1" class="otp-input" id="otp-4" name="enteredOTP[]" required autocomplete="off">
                            <input type="text" maxlength="1" class="otp-input" id="otp-5" name="enteredOTP[]" required autocomplete="off">
                            <input type="text" maxlength="1" class="otp-input" id="otp-6" name="enteredOTP[]" required autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-12 form-group mt-4">
                      <center>
                        <input type="submit" value="Verify OTP" class="btn btn-success" name="verify_otp">
                      </center>
                    </div>
                </form>
            </div>
            </div>
        </div>
    </div>
    @endforeach
    
    @include('includes.footerJs')
    <script>
        // opt display 
    $(document).ready(function() {
        $('.modal').on('click', '#toggleOTP', function() {
            var $modal = $(this).closest('.modal');
            
            var $hiddenOtpField = $modal.find('#hidden_otp');
            var $otpInputField = $modal.find('#optVal');
            
            if ($hiddenOtpField.text() === '******') {
                $hiddenOtpField.text($otpInputField.val());
                $(this).removeClass('mdi-eye-off-outline').addClass('mdi-eye');
            } else {
                $hiddenOtpField.text('******');
                $(this).removeClass('mdi-eye').addClass('mdi-eye-off-outline');
            }
        });

        // jump otp input 
        const otpInputs = $('.otp-input');
    
        otpInputs.each(function(index) {
            $(this).on('keyup', function(e) {
                const value = $(this).val();
                
                if (value.length === 1) {
                    if (index < otpInputs.length - 1) {
                        otpInputs.eq(index + 1).focus();
                    }
                } else if (value.length === 0 && index > 0) {
                    otpInputs.eq(index - 1).focus();
                }
            });
            
            $(this).on('keydown', function(e) {
                if (e.key === 'Backspace' && $(this).val() === '' && index > 0) {
                    otpInputs.eq(index - 1).focus();
                }
            });
        });
    });

    function sendotp(student_id,mobile){
        $.ajax({
            url : "{{route('sendOtpVisitor')}}",
            data : {student_id:student_id,mobile:mobile},
            type : "GET",
            success: function(result){
                alert(result['message']);
                $('#optVal').val(result['otp']);
                console.log(result);
            }
        })
    }

        $(document).ready(function () {
            var table = $('#example').DataTable({
                ordering: false,
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdfHtml5',
                        title: 'Student Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Student Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Student Report'},
                    {extend: 'print', text: ' PRINT', title: 'Student Report'},
                    'pageLength'
                ],
            });
            //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

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
@include('includes.footer')
