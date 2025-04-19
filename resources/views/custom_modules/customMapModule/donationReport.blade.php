@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Donation Collection Report</h4>
            </div>
        </div>
        <div class="card">
            <form method="GET" action="{{ route('donation_report.index') }}" class="row">
                <div class="col-md-4 mt-2">
                    <label for="from_date">From Date:</label>
                    <input type="text" class="form-control mydatepicker" id="from_date" name="from_date" value="{{ $data['from_date'] }}">
                </div>
                <div class="col-md-4 mt-2">
                    <label for="to_date">To Date:</label>
                    <input type="text" class="form-control mydatepicker" id="to_date" name="to_date" value="{{ $data['to_date'] }}">
                </div>
               
                <div class="col-md-4 mt-2">
                    <label for="name">Name</label>
                    <input type="text" class="form-control" name="full_name" list="donarLists" placeholder="Enter Full Name" autocomplete="off" value="{{ $data['full_name'] ?? '' }}">
                    <datalist id="donarLists">
                        @foreach($data['donarLists'] as $donar)
                            <option>{{ $donar->full_name }}</option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-md-4 mt-2">
                    <label for="mobile">Mobile Number</label>
                    <input type="text" class="form-control" pattern="[1-9]{1}[0-9]{9}" name="mobile_number" placeholder="Enter Mobile Number" autocomplete="off" value="{{ $data['mobile_number'] ?? '' }}">
                </div>
                <div class="col-md-12 mt-2">
                    <center>
                        <input type="submit" value="Search" class="btn btn-primary" name="Search" id="Search">
                    </center>
                </div>
            </form>
    </div>

        @if(isset($data['reportData']) && !empty($data['reportData']))
        <div class="card">
            <div class="row">
                <div class="white-box">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="example">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Donor Name</th>
                                    <th>Mobile</th>
                                    <th>PAN Number</th>
                                    <th>Paid Date</th>
                                    <th>Amount</th>
                                    <th>Payment Mode</th>
                                    <th>Cheque Details</th>
                                    <th>Bank Name</th>
                                    <th class="text-left">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['reportData'] as $key => $donation)
                                <tr>
                                    <td>{{ $key+1 }}</td>
                                    <td>{{ $donation->full_name }}</td>
                                    <td>{{ $donation->mobile_number }}</td>
                                    <td>{{ $donation->pan_number }}</td>
                                    <td>{{ \Carbon\Carbon::parse($donation->paid_date)->format('d-m-Y') }}</td>
                                    <td>{{ $donation->donation_amount }}</td>
                                    <td>{{ $donation->payment_mode }}</td>
                                    <td>{{ $donation->cheque_number }} / {{\Carbon\Carbon::parse($donation->cheque_date)->format('d-m-Y')}}</td>
                                    <td>{{ $donation->bank_name }}</td>
                                    <td>{{ $donation->remarks }}</td>
                                </tr>
                                @endforeach
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
<script>
    $(document).ready(function() {
        $('#to_date').change(function() {
            const fromDate = new Date($('#from_date').val());
            const toDate = new Date($(this).val());
            if (toDate < fromDate) {
                alert('To Date should be greater than or equal to From Date.');
                $(this).val(''); // Clear the invalid date
            }
        });
    });

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
                    title: 'Donation Collection report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Donation Collection report'},
                {extend: 'excel', text: ' EXCEL', title: 'Donation Collection report'},
                {extend: 'print', text: ' PRINT', title: 'Donation Collection report'},
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
@endsection
