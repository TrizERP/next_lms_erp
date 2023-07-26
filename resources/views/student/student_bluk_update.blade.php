@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Bulk Update</h4>
            </div>
        </div>
        <div class="card">
            <div class="row mb-2">     
        </div>  
        @if ($sessionData = Session::get('data'))
            @if($sessionData['status'] == 1)
                <div class="alert alert-success alert-block">
                    @else
                        <div class="alert alert-danger alert-block">
                            @endif
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @endif

                    <form action="{{ route('student_bulk_update.store') }}" id="submit_student_bulk_update_form" method="post">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-box table-bordered">
                            <thead>
                            <tr>
                                <th>Module Name</th>
                                <th style="text-align:left;">Check for Student Bluk Update Data
                                    <p style="color:red;">(No. of ACTIVE Students {{count($data['get_student_enrollments'])}})</p>
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>Can all students inactive</td>
                                <td>
                                    <input type="checkbox" id="student_bluk_update" value="" name="tables">
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-sm-12 form-group mt-3">
                        <center>
                            <input type="submit" name="submit" value="Student Bulk Update Data" class="btn btn-success">
                        </center>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('includes.footerJs')
    <script>
        /* $('#submit_student_bulk_update_form').submit(function () {
            var selected_tables = $("input[name='tables']:checked").length;
            if (selected_tables < 1) {
                alert("Please Select Atleast Table for Student Bluk Update.");
                return false;
            } else {
                return true;
            }
        }); */

        document.getElementById('submit_student_bulk_update_form').addEventListener('submit', function(event) {
            var checkbox = document.getElementById('student_bluk_update');

            if (!checkbox.checked) {
                alert('Please Select Atleast Table for Student Bluk Update.');
                event.preventDefault();
            }
        });

        /* $('input[name="tblstudent_enrollment"]:radio').change(function () {
            var selected_radio_value = $(this).attr("value");
            if (selected_radio_value == 'selected_students') {
                var tables_arr = new Array();
                $.each($("input[name='tables[]']:checked"), function () {
                    tables_arr.push($(this).val());
                });

            window.location.href = "{{ route('selected_student_view') }}"+"?tables="+tables_arr+"&tblstudent_enrollment_value="+selected_radio_value;
        }
    }); */

</script>
@include('includes.footer')
