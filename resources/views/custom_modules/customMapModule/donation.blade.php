@extends('layout')
@section('container')
<style>
    #donationTable th, #donationTable td {
        width: 25% !important;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Donation Collection</h4>
            </div>
        </div>

        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-{{ $sessionData['status'] == 1 ? 'success' : 'danger' }} alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif

            @if (isset($data['status']) && $data['status'] == 0)
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif

            <form action="{{ route('donation_collection.create') }}">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <label for="name">Name</label>
                        <input type="text" class="form-control" name="full_name" list="donarLists" placeholder="Enter Full Name" autocomplete="off" value="{{ $data['full_name'] ?? '' }}">
                        <datalist id="donarLists">
                            @foreach($data['donarLists'] as $donar)
                                <option>{{ $donar->full_name }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-6">
                        <label for="mobile">Mobile Number</label>
                        <input type="text" class="form-control" pattern="[1-9]{1}[0-9]{9}" name="mobile_number" placeholder="Enter Mobile Number" autocomplete="off" value="{{ $data['mobile_number'] ?? '' }}">
                    </div>
                </div>
                <div class="col-md-12 form-group mt-4 text-center">
                    <input type="submit" name="submit" value="Search" class="btn btn-success">
                </div>
            </form>
        </div>

        @if(!empty($data['donarData']) && isset($data['donarData']->id))
            <div class="card">
                @if(!empty($data['donationData']))
                    <div class="row">
                        <div class="col-md-12 text-right">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#donationDetailsModal">History <span class="mdi mdi-history"></span></button>
                        </div>
                    </div>
                @endif

                    <div class="row">
                        @foreach(['Name' => 'full_name', 'PAN Number' => 'pan_number', 'Address' => 'address', 'Mobile' => 'mobile_number'] as $label => $field)
                            <div class="col-md-3" style="margin: 10px 0;">
                                <div class="card border" style="padding: 10px !important;">
                                    <label><b>{{ $label }}:</b></label>
                                    <p class="m-0">{{ $data['donarData']->$field }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                <div class="card p-4">
                    <form action="{{ route('donation_collection.store') }}" method="post">
                        @csrf
                        <input type="hidden" name="donar_id" value="{{ $data['donarData']->id }}">
                        @foreach(['id', 'full_name', 'pan_number', 'address', 'mobile_number'] as $field)
                            <input type="hidden" name="{{ $field }}" value="{{ $data['donarData']->$field }}">
                        @endforeach

                        <table class="table table-striped" id="donationTable">
                            <tr>
                                <td>Donation Head</td>
                                <td>
                                    <select name="donation_head" class="form-control" required>
                                        <option value="">Select Donation Head</option>
                                        @foreach($data['donation_head'] as $key => $value)
                                            <option value="{{ $key }}">{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td></td>
                                <td></td>
                            <tr>
                                <td>Date of Receipt</td>
                                <td><input type="text" name="paid_date" class="form-control mydatepicker"  autocomplete="off" required></td>
                                <td>Amount</td>
                                <td><input type="number" name="amount" class="form-control" required placeholder="Enter amount"></td>
                            </tr>
                            <tr>
                                <td>Payment Mode</td>
                                <td>
                                    <select name="payment_mode" class="form-control" onchange="sh_bankDetail(this.value);" required>
                                        <option value="">Select Payment Mode</option>
                                        @foreach($data['paymentModes'] as $key => $value)
                                            <option value="{{ $key }}">{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>Remarks</td>
                                <td><textarea name="remarks" class="form-control" placeholder="Add Remarks"></textarea></td>
                            </tr>
                            <tr class="bankDetail">
                                <td>Bank Name</td>
                                <td>
                                    <select name="bank_name" class="form-control">
                                        <option value="">Select Bank Name</option>
                                        @foreach($data['bank_data'] ?? [] as $bank)
                                            <option value="{{ $bank['bank_name'] }}">{{ $bank['bank_name'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>Bank Branch</td>
                                <td><input type="text" name="bank_branch" class="form-control" placeholder="Enter Bank Branch" value="N/A"></td>
                            </tr>
                            <tr class="bankDetail">
                                <td>Cheque/DD No.</td>
                                <td><input type="text" name="cheque_no" class="form-control" placeholder="Enter Cheque/DD No."></td>
                                <td>Cheque/DD Date</td>
                                <td><input type="text" name="cheque_date" class="form-control mydatepicker" autocomplete="off"></td>
                            </tr>
                        </table>
                        <div class="text-center mt-4">
                            <input type="submit" value="Submit" class="btn btn-success">
                        </div>
                    </form>
                </div>
            </div>

        <div class="modal fade" id="donationDetailsModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document" style="max-width: 1200px;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Donation History</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        
                            <div class="row">
                                @foreach(['Name' => 'full_name', 'PAN Number' => 'pan_number', 'Address' => 'address', 'Mobile' => 'mobile_number'] as $label => $field)
                                    <div class="col-md-3" style="margin: 10px 0;">
                                        <div class="card border" style="padding: 10px !important;">
                                            <label><b>{{ $label }}:</b></label>
                                            <p class="m-0">{{ $data['donarData']->$field }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        <form action="{{route('donation_collection.destroy',[$data['donarData']->id])}}" method="post" onsubmit="validateForm(event)">
                        @csrf
                        @method('DELETE')
                        <h5><b>Collection Data</b></h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th><input id="ckbCheckAll" type="checkbox"> Sr No.</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Receipt No</th>
                                    <th>Payment Mode</th>
                                    <th>Remarks</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['donationData'] ?? [] as $index => $donation)
                                    <tr>
                                        <td><input type="checkbox" name="deleteData[]" value="{{$donation->id}}" class="ckbox1"> {{ $index + 1 }}</td>
                                        <td>{{ \Carbon\Carbon::parse($donation->paid_date)->format('d-m-Y') }}</td>
                                        <td>{{ $donation->donation_amount }}</td>
                                        <td>{{ $donation->reciept_no }}</td>
                                        <td>{{ $donation->payment_mode }}</td>
                                        <td>{{ $donation->remarks }}</td>
                                        <td>
                                            <span class="mdi mdi-printer" onclick="downloadReceipt('{{ $donation->id }}', '{{ $data['fees_config']->fees_receipt_template ?? 0 }}');" style="font-size: 26px; color:#df9b24;"></span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <center>
                                    <input type="submit" value="Cancel Donation" class="btn btn-danger mt-2" onclick="return confirm('Are you sure you want to delete this record?');">
                                </center>
                            </div>
                        </div>
                        </form>
                        @if(isset($data['cancellData']) && !empty($data['cancellData']))
                        <div class="row" style="padding:16px;">
                            <h5><b>Cancelled Data</b></h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Sr No.</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Receipt No</th>
                                        <th>Payment Mode</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['cancellData'] ?? [] as $index => $donation)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ \Carbon\Carbon::parse($donation->paid_date)->format('d-m-Y') }}</td>
                                            <td>{{ $donation->donation_amount }}</td>
                                            <td>{{ $donation->reciept_no }}</td>
                                            <td>{{ $donation->payment_mode }}</td>
                                            <td>{{ $donation->remarks }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif

                    </div>
                   
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@include('includes.footerJs')
<script>
    function downloadReceipt(receiptId, paperSize) {
        $.ajax({
            url: `/ajax_PDF_FeesReceipt?action=donation_receipt&receipt_id_html=${receiptId}&paper_size=${paperSize}`,
            success: function(result) {
                let newTab = window.open(result, '_blank');
                if (newTab) {
                    newTab.onload = function() {
                        newTab.print();
                    };
                } else {
                    alert("Pop-up blocked! Please allow pop-ups for this site.");
                }
            },
            error: function() {
                alert('Failed to get Receipt Data');
            }
        });
    }

    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            // console.log('clicked');
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });

    function validateForm(event){
        var checkboxes = document.querySelectorAll('input[name^="deleteData["]');
        var anyChecked = false;

        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                anyChecked = true;
            }
        });
        
        if (!anyChecked) {
            alert("Please select at least one checkbox");
            event.preventDefault();
            return false;
        }
    }

    function sh_bankDetail(selectedVal) {
        if (selectedVal == 'Cash') {
            $('.bankDetail').hide();
        } else {
            $('.bankDetail').show();
        }
    }
</script>
@include('includes.footer')
@endsection
