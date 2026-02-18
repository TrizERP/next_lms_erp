@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">S3-NACH Excel Export</h4>
            </div>
        </div>
        @php
            $grade = $standard = $division = $month_id = '';
            if(isset($data['grade']))
            {
                $grade = $data['grade'];
            }
            if(isset($data['standard']))
            {
                $standard = $data['standard'];
            }
            if(isset($data['division']))
            {
                $division = $data['division'];
            }
            if(isset($data['month_id']))
            {
                $month_id = $data['month_id'];
            }

        @endphp
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('NACH_s3excel_export.create') }}">
                @csrf
                <div class="row">

                    {{ App\Helpers\SearchChain('3','single','grade,std,div',$grade,$standard,$division) }}

                    <div class="col-md-3">
                        <label>Month</label>
                        <select id="month_id" name="month_id" class="form-control" required>
                            <option value="">Select</option>
                            @foreach ($data['fee_month'] as $id => $val)
                                <option value="{{$id}}" @if($month_id == $id) selected @endif>{{$val}}</option>
                            @endforeach
                        </select>
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
                                <!--<thead>
                                <tr>
                                    <th data-toggle="tooltip" title="Sr.No.">Sr.No.</th>
                                    <th data-toggle="tooltip" title="ACH Transaction Code (2) M">ACH Transaction Code
                                        (2) M
                                    </th>
                                    <th data-toggle="tooltip" title="Control (9) O">Control (9) O</th>
                                    <th data-toggle="tooltip" title="Destination Account Type (2) O">Destination Account
                                        Type (2) O
                                    </th>
                                    <th data-toggle="tooltip" title="Ledger Folio Number (3) O">Ledger Folio Number (3)
                                        O
                                    </th>
                                    <th data-toggle="tooltip" title="Control (15) O">Control (15) O</th>
                                    <th data-toggle="tooltip" title="Beneficiary Account Holder's Name (40) M">
                                        Beneficiary Account Holder's Name (40) M
                                    </th>
                                    <th data-toggle="tooltip" title="Control (9) O">Control (9) O</th>
                                    <th data-toggle="tooltip" title="Control (7) O">Control (7) O</th>
                                    <th data-toggle="tooltip" title="User Name / Narration (20) O">User Name / Narration
                                        (20) O
                                    </th>
                                        <th data-toggle="tooltip" title="Control (13) O">Control (13) O</th>
                                        <th data-toggle="tooltip" title="Amount (13) M">Settlement Date (DDMMYYYY) (8) M</th>
                                        <th data-toggle="tooltip" title="Reserved (ACH Item Seq No.) (10) O">Reserved (ACH Item Seq No.) (10) O</th>
                                        <th data-toggle="tooltip" title="Reserved (Checksum) (10) O">Reserved (Checksum) (10) O</th>
                                        <th data-toggle="tooltip" title="Reserved (Flag for success / return) (1) O">Reserved (Flag for success / return) (1) O</th>
                                        <th data-toggle="tooltip" title="Reserved (Reason Code) (2) O">Reserved (Reason Code) (2) O</th>
                                        <th data-toggle="tooltip" title="Destination Bank IFSC / MICR / IIN (11) M">Destination Bank IFSC / MICR / IIN (11) M</th>
                                        <th data-toggle="tooltip" title="Beneficiary's Bank Account number (35) M">Beneficiary's Bank Account number (35) M</th>
                                        <th data-toggle="tooltip" title="Sponsor Bank IFSC / MICR / IIN (11) M">Sponsor Bank IFSC / MICR / IIN (11) M</th>
                                        <th data-toggle="tooltip" title="User Number (18) M">User Number (18) M</th>
                                        <th data-toggle="tooltip" title="Transaction Reference (30) M">Transaction Reference (30) M</th>
                                    <th data-toggle="tooltip" title="Product Type (3) M">Product Type (3) M</th>
                                    <th data-toggle="tooltip" title="Beneficiary Aadhaar Number (15) M for APBS">
                                        Beneficiary Aadhaar Number (15) M for APBS
                                    </th>
                                    <th data-toggle="tooltip" title="UMRN (20) M">UMRN (20) M</th>
                                </tr>
                                </thead>-->
                                <tbody>
                                @php
                                    $j=-1;
                                @endphp
                                @if(count($student_data) > 0)
                                    @foreach($student_data as $key => $studata)
                                        <tr>
                                            <td>{{$j}}</td>
                                            <td>{{$studata['ACH_TRANSACTION_CODE']}}</td>
                                            <td>{{$studata['CONTROL_1']}}</td>
                                            <td>{{$studata['DESTINATION_AC_TYPE']}}</td>
                                            <td>{{$studata['LEDGER_FOLIO_NUMBER']}}</td>
                                            <td>{{$studata['CONTROL_2']}}</td>
                                            <td>{{$studata['BENEFICIARY_AC_HOLDER_NAME']}}</td>
                                            <td>{{$studata['CONTROL_3']}}</td>
                                            <td>{{$studata['CONTROL_4']}}</td>
                                            <td>{{$studata['USER_NAME']}}</td>
                                            <td>{{$studata['CONTROL_5']}}</td>
                                            <td>{{$studata['AMOUNT']}}</td>
                                            <td>{{$studata['RESERVED_ACH_ITEM_SEQ_NO']}}</td>
                                            <td>{{$studata['RESERVED_CHECKSUM']}}</td>
                                            <td>{{$studata['RESERVED_FLAG_SUCCESS_RETURN']}}</td>
                                            <td>{{$studata['RESERVED_REASON_CODE']}}</td>
                                            <td>{{$studata['DESTINATION_BANK_IFSC_CODE']}}</td>
                                            <td>{{$studata['DESTINATION_BANK_AC_NUMBER']}}</td>
                                            <td>{{$studata['SPONSOR_BANK_IFSC_CODE']}}</td>
                                            <td>{{$studata['USER_NUMBER']}}</td>
                                            <td>{{$studata['TRANSACTION_REFERENCE']}}</td>
                                            <td>{{$studata['PRODUCT_TYPE']}}</td>
                                            <td>{{$studata['BENEFICIARY_ADHAAR_NUMBER']}}</td>
                                            <td>{{$studata['UMRN']}}</td>
                                        </tr>
                                            @php
                                            $j++;
                                            @endphp
                                        @endforeach
                                        <tr><td colspan="30">
                                            <center>
                                                <a class="btn btn-success" href="../{{$data['excelFile_path']}}" download>Export S3 Excel</a>
                                            </center>
                                        </td></tr>
                                    @else
                                        <tr><td colspan="30"><center>No Records</center></td></tr>
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

<script type="text/javascript">
    $(document).ready(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
@include('includes.footer')
