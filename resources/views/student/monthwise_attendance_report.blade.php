@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Monthwise Attendance Report</h4>
            </div>
        </div>
        @php
            $grade_id = $standard_id = $division_id = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
                $getInstitutes = session()->get('getInstitutes');
                 $academicYears = session()->get('academicYears');

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
                        <form action="{{ route('show_monthwise_student_attendance_report') }}"
                              enctype="multipart/form-data" method="post">
                            <div class="row">

                                {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                                <div class="col-md-4 form-group">
                                    <label>Year</label>
                                    <select class="form-control" name="year" id="year">
                                        <option value="">Select Year</option>

                                        @if(count($academicYears) > 0)
                                            @foreach($academicYears as $kay => $vay)
                                                <option value="{{$vay->syear}}"
                                                        @if(isset($data['year'])) @if($data['year'] == $vay->syear) selected="selected" @endif @endif>{{$vay->syear}}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Month</label>
                                    <select name="month" class="form-control" required="required">
                                        <option value="">Select Month</option>
                                        <option value="1"
                                                @if(isset($data['month'])) @if($data['month'] == '1') selected="selected" @endif @endif>
                                            January
                                        </option>
                                        <option value="2"
                                                @if(isset($data['month'])) @if($data['month'] == '2') selected="selected" @endif @endif>
                                            February
                                        </option>
                                        <option value="3"
                                                @if(isset($data['month'])) @if($data['month'] == '3') selected="selected" @endif @endif>
                                            March
                                        </option>
                                        <option value="4"
                                                @if(isset($data['month'])) @if($data['month'] == '4') selected="selected" @endif @endif>
                                            April
                                        </option>
                                        <option value="5"
                                                @if(isset($data['month'])) @if($data['month'] == '5') selected="selected" @endif @endif>
                                            May
                                        </option>
                                        <option value="6"
                                                @if(isset($data['month'])) @if($data['month'] == '6') selected="selected" @endif @endif>
                                            June
                                        </option>
                                        <option value="7"
                                                @if(isset($data['month'])) @if($data['month'] == '7') selected="selected" @endif @endif>
                                            July
                                        </option>
                                        <option value="8"
                                                @if(isset($data['month'])) @if($data['month'] == '8') selected="selected" @endif @endif>
                                            August
                                        </option>
                                        <option value="9"
                                                @if(isset($data['month'])) @if($data['month'] == '9') selected="selected" @endif @endif>
                                            September
                                        </option>
                                        <option value="10"
                                                @if(isset($data['month'])) @if($data['month'] == '10') selected="selected" @endif @endif>
                                            October
                                        </option>
                                        <option value="11"
                                                @if(isset($data['month'])) @if($data['month'] == '11') selected="selected" @endif @endif>
                                            November
                                        </option>
                                        <option value="12"
                                                @if(isset($data['month'])) @if($data['month'] == '12') selected="selected" @endif @endif>
                                            December
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-12 form-group">
                                    <center>
                                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                                    </center>
                                </div>
                            </div>
                        </form>
                    </div>

                    @if(isset($data['student_data']))
                        @php
                            $j = 1;
                                if(isset($data['student_data'])){
                                    $student_data = $data['student_data'];
                                }
                        @endphp
                        <div class="card">
                            <div class="table-responsive">
                                <table id="example" class="table display" style="border:none !important">
                                    <!-- <h2 id="head-table"></h2> -->
                                    <thead>
                                    <tr id="head-table" style="border:none !important"></tr>
                                    <tr id="heads">
                                        <th>Sr No</th>
                                        <th>{{App\Helpers\get_string('grno','request')}}</th>
                                        <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                        @for($i=1;$i<=$data['to_date'];$i++)
                                            <th>{{$i}}</th>
                                        @endfor
                                        <th>Total Working Days</th>
                                        <th>Total Presant</th>
                                        <th>Total Absent</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($student_data as $key => $value)
                                        <tr>
                                            <?php
                                            $totalWorkingDays = 0;
                                            $totalP = 0;
                                            $totalA = 0;
                                            ?>
                                            <td>{{$j++}}</td>
                                            <td>{{$value['enrollment_no']}}</td>
                                            <td>{{$value['first_name']." ".$value['middle_name']." ".$value['last_name']}}</td>
                                            @for($i=1;$i<=$data['to_date'];$i++)
                                                <td>
                                                    @if(isset($data['attendance_data'][$value['id']][$i]))
                                                        {{$data['attendance_data'][$value['id']][$i]}}
                                                        <?php
                                                        if ($data['attendance_data'][$value['id']][$i] == 'A') {
                                                            $totalA++;
                                                        } else {
                                                            $totalP++;
                                                        }

                                                        $totalWorkingDays++;
                                                        ?>
                                                    @else
                                                        @if(in_array($i,$data['sundays']))
                                                            S
                                                        @elseif(in_array($i,$data['holidays']))
                                                            H
                                                        @else
                                                            -
                                                            <?php
                                                            $totalWorkingDays++;
                                                            ?>
                                                        @endif
                                                    @endif
                                                </td>
                                            @endfor
                                            <td>{{$totalWorkingDays}}</td>
                                            <td>{{$totalP}}</td>
                                            <td>{{$totalA}}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
        </div>
    </div>

    @include('includes.footerJs')
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
                        title: 'Monthwise Attendance Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Monthwise Attendance Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Monthwise Attendance Report'},
                    {extend: 'print', text: ' PRINT', title: 'Monthwise Attendance Report'},
                    'pageLength'
                ],
            });
            var g = document.getElementById("grade");
            var grade = g.options[g.selectedIndex].text;

            var s = document.getElementById("standard");
            var standard = s.options[s.selectedIndex].text;

            var d = document.getElementById("division");
            var division = d.options[d.selectedIndex].text;
            $('#example thead #head-table').html('<th style="border:none !important;text-align:center;font-weight:700 !important" colspan="18"><h4>Academic Section : ' + grade + ' | Standard : ' + standard + ' | Division : ' + division + '</h4></th>');
            $('#example thead #heads').clone(true).appendTo('#example thead');
            $('#example thead #heads:eq(1) th').each(function (i) {
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

            $('#grade').attr('required', true);
            $('#standard').attr('required', true);
            $('#division').attr('required', true);

        });
    </script>
@include('includes.footer')
