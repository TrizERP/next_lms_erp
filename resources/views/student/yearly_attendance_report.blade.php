@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Yearly Attendance Report</h4>
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
                        <form action="{{ route('show_yearly_student_attendance') }}" enctype="multipart/form-data"method="post">
                        @csrf
                            <div class="row">
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                            <!-- from month -->
                                <div class="col-md-4 form-group">
                                    <label>From Date</label>
                                    <input type="text" id="from_date" name="from_date"
                                           value="@if(isset($data['from_date'])){{$data['from_date']}}@endif"
                                           class="form-control mydatepicker" autocomplete="off" required>
                                </div>

                                <!-- to month -->

                                <div class="col-md-4 form-group">
                                    <label>To Date</label>
                                    <input type="text" id="to_date" name="to_date"
                                           value="@if(isset($data['to_date'])){{$data['to_date']}}@endif"
                                           class="form-control mydatepicker" autocomplete="off"  required>

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
                        @php
                            echo App\Helpers\get_school_details($grade_id,$standard_id,$division_id);
                            echo '<br><center><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">From Date : '.date('d-m-Y',strtotime($data['from_date'])) .' - </span><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">To Date : '.date('d-m-Y',strtotime($data['to_date'])) .'</span></center><br>';
                        @endphp    
                            <div class="table-responsive" id="printPage">
                                <div class="my-4" id="head-table"></div>
                                
                                <table class="table table-bordered table-center" border=1>
                                    <!-- <h2 id="head-table"></h2> -->
                                    @php $month_name = [1 => "Jan", 2 => "Feb", 3 => "Mar", 4 => "Apr", 5 => "May", 6 => "June", 7 => "July", 8 => "Aug", 9 => "Sept", 10 => "Oct", 11 => "Nov", 12 => "Dec"]; 
                                    @endphp
                                    <thead id="another">
                                    <tr id="heads"></tr>
                                    <!-- first heading -->
                                    <tr>
                                        <th>Sr No</th>
                                        <th>{{App\Helpers\get_string('grno','request')}}</th>
                                        <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                    <!-- <th>{{ session()->get('sub_institute_id') }}</th> -->
                                        @foreach($data['month'] as $key => $i)
                                            <th>{{$month_name[$i]}}</th>
                                        @endforeach
                                        <th class="text-left">Total School Year Day</th>
                                        @php $working_day = 0; @endphp
                                    </tr>
                                    <tr>
                                        <th>Sr No</th>
                                        <th>{{App\Helpers\get_string('grno','request')}}</th>
                                        <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                        @if(isset($data['month']))
                                        @foreach($data['month'] as $key => $i)
                                            <th style="text-align:center">
                                                    @if(isset($data['working_day'][$i]))
                                                    @php $working_day += $data['working_day'][$i]; @endphp
                                                        {{ $data['working_day'][$i] }}
                                                    @else
                                                        0
                                                    @endif
                                                </th>
                                        @endforeach
                                        @endif
                                        <th style="text-align:center">{{$working_day}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <!-- second heading  -->
                                    @foreach($student_data as $key => $value)
                                        <tr>
                                            @php
                                            $totalAttandance = 0;
                                            $totalP = 0;
                                            $totalA = 0;
                                            @endphp
                                            <td style="text-align:center">{{$j++}}</td>
                                            <td style="text-align:center">{{$value['enrollment_no']}}</td>
                                            <td class="px-6">{{$value['first_name']." ".$value['middle_name']." ".$value['last_name']}}</td>
                                            @foreach($data['month'] as $kkk=>$ii)
                                                <td style="text-align:center">
                                                    @if(isset($data['attendance_data'][$value['id']][$ii]))
                                                        {{$data['attendance_data'][$value['id']][$ii]}}
                                                        @php $totalAttandance += $data['attendance_data'][$value['id']][$ii]; @endphp
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td style="text-align:center">{{$totalAttandance}}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <button class="btn btn-success" onclick="PrintDiv('printPage');">Print</button>
                            </center>
                        </div>
                    @endif
        </div>
    </div>

    @include('includes.footerJs')
    <script>
        $(document).ready(function () {

            var g = document.getElementById("grade");
            var grade = g.options[g.selectedIndex].text;

            var s = document.getElementById("standard");
            var standard = s.options[s.selectedIndex].text;

            var d = document.getElementById("division");
            var division = d.options[d.selectedIndex].text;

            $('#head-table').html('<div style="border:none !important;text-align:center;font-weight:700 !important" colspan="18"><h4>Academic Section : ' + grade + ' | Standard : ' + standard + ' | Division : ' + division + '</h4></div>');

            $('#grade').attr('required', true);
            $('#standard').attr('required', true);
            $('#division').attr('required', true);
        });

        function PrintDiv(divName) {
            var divToPrint = document.getElementById(divName);
            var popupWin = window.open('', '_blank', 'width=300,height=300');
            popupWin.document.open();
            popupWin.document.write('<html>');
            popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
            popupWin.document.close();
        }
    </script>
@include('includes.footer')
