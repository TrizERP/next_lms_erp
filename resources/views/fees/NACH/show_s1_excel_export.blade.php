@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">S1-NACH Excel Export</h4>
            </div>
        </div>
        @php
            $from_date = $to_date = '';
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

            @if ($sessionData = $data)
                <div
                    class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('NACH_s1excel_export.create') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}"
                               class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group ml-0">
                        <label>To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}"
                               class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-sm-4 form-group mt-4">
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
                }
            @endphp

            <div class="card">
                <div class="row mt-5">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table class="table table-box table-bordered">
                                <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>Student ID</th>
                                    <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                                    <th>{{ App\Helpers\get_string('grno','request')}}</th>
                                    <th>Mobile</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Account Holder Name</th>
                                    <th>Account Number</th>
                                    <th>Bank Name</th>
                                    <th>IFSC</th>
                                    <th>Account Type</th>
                                    <th>UMRN</th>
                                </tr>
                                </thead>
                                <tbody>
                                @php
                                    $j=1;
                                @endphp
                                @if(count($student_data) > 0)
                                    @foreach($student_data as $key => $studata)
                                        <tr>
                                            <td>{{$j}}</td>
                                            <td>{{$studata['student_id']}}</td>
                                            <td>{{$studata['student_name']}}</td>
                                            <td>{{$studata['enrollment_no']}}</td>
                                            <td>{{$studata['mobile']}}</td>
                                            <td>{{$studata['payment_method']}}</td>
                                            <td>{{$studata['registration_date']}}</td>
                                            <td>{{$studata['ac_holder_name']}}</td>
                                            <td>{{$studata['ac_number']}}</td>
                                            <td>{{$studata['bank_name']}}</td>
                                            <td>{{$studata['ifsc_code']}}</td>
                                            <td>{{$studata['ac_type']}}</td>
                                            <td>{{$studata['UMRN']}}</td>
                                        </tr>
                                        @php
                                            $j++;
                                            @endphp
                                        @endforeach
                                        <tr><td colspan="20">
                                            <center>
                                                <a class="btn btn-success" href="../{{$data['excelFile_path']}}" download>Export S1 Excel</a>
                                            </center>
                                        </td></tr>
                                    @else
                                        <tr><td colspan="20"><center>No Records</center></td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        @endif
    </div>
</div>


@include('includes.footerJs')
@include('includes.footer')
