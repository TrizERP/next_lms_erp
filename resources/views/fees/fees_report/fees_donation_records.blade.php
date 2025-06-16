@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">80G Donation Records</h4> </div>
            </div>
		@if(!empty($records) && $records->count() > 0)
                <div class="card">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>Pre Acknowledgement Number</th>
                                    <th>Permanent Account Number</th>
                                    <th>Unique Identification Number</th>
                                    <th>Section 80G</th>
                                    <th>Unique Registration Number (URN)</th>
                                    <th>Date of Issuance of Unique Registration Number</th>
                                    <th>Name of donor</th>
                                    <th>Address of donor</th>
                                    <th>Others</th>
                                    <th>Mode</th>
                                    <th>Amount of donation (Indian rupees)</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                @foreach($records as $row)
                                <tr>
                                    <td>{{$j}}</td>
                                    <td>{{ $row->receipt_no }}</td>
                                    <td>Permanent Account Number</td>
                                    <td>{{ $row->pan_card }}</td>
                                    <td>Section 80G</td>
                                    <td></td>
                                    <td>{{ $row->receiptdate }}</td>
                                    <td>{{ $row->first_name }} {{ $row->middle_name }} {{ $row->last_name }}</td>
                                    <td>{{ $row->address }}</td>
                                    <td>Others</td>
                                    <td>{{ $row->payment_mode }}</td>
                                    <td>{{ $row->amount }}</td>
                                </tr>
                                    @php
                                    $j++;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    </div>
                </div>
        @else
            <div class="alert alert-danger">No records found.</div>
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
                {extend: 'csv', text: ' CSV', title: '80G Donation Records'},
                {extend: 'excel', text: ' EXCEL', title: '80G Donation Records'},
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
