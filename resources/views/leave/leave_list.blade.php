@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">My Leave</h4>
            </div>
        </div>

        <div class="card">
            <div class="col-md-12 mt-2">
                <div class="col-lg-12 col-sm-3 col-xs-3 row">
                    <div class="col-md-3 pull-right">
                        <select id="cmbyear" class="form-control" name="cmbyear"
                        onchange="getYearwiseLeave(this.value);">
                            <option value="">Select Year</option>
                            <option value="2024">2024-2025</option>
                            <option value="2023">2023-2024</option>
                            <option value="2022">2022-2023</option>
                            <option value="2021">2021-2022</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="tblLeaves" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr class="raw0">
                                    <th data-toggle="tooltip" title="No">No</th>
                                    <th data-toggle="tooltip" title="From Date">From Date</th>
                                    <th data-toggle="tooltip" title="To Date">To Date</th>
                                    <th data-toggle="tooltip" title="No of Days">No of Days</th>
                                    <th data-toggle="tooltip" title="Leave Type">Leave Type</th>
                                    <th data-toggle="tooltip" title="Reason">Reason</th>
                                    <th data-toggle="tooltip" title="HOD's Comment">HOD's Comment</th>
                                    <th data-toggle="tooltip" title="HOD's Comment Date">HOD's Comment Date</th>
                                    <th data-toggle="tooltip" title="HR Remarks">HR Remarks</th>
                                    <th data-toggle="tooltip" title="HR Remark Date">HR Remark Date</th>
                                    <th data-toggle="tooltip" title="Approved By">Approved By</th>
                                    <th data-toggle="tooltip" title="Status">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>

                        </table>
                    </div>
                </div>
            </div>
            <!-- Tabs content -->
        </div>
    </div>
</div>
@include('includes.footerJs')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js"></script>
<script type="text/javascript">
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<script>
    $(document).ready(function() {
        var table = $('#tblLeaves').DataTable({
        });
    });
</script>
<script>
    function getYearwiseLeave(selectedYear) {
        // Make an Ajax request
        $.ajax({
            type: 'GET',
            url: '/get-leave',
            data: { year: selectedYear },
            success: function(data) {
                // Update the table body with the received data
                updateTableBody(data);
            },
            error: function(error) {
                console.error('Error fetching data:', error);
            }
        });
    }

    function updateTableBody(data) {
        var tableBody = $('#tblLeaves tbody');
        tableBody.empty(); // Clear existing rows

        if (data.length > 0) {
            $.each(data, function(index, item) {
                // Append a new row for each item in the data
                var row = '<tr>' +
                    '<td>' + (index + 1) + '</td>' +
                    '<td>' + item.from_date + '</td>' +
                    '<td>' + item.to_date + '</td>' +
                    '<td>' + item.day_type + '</td>' +
                    '<td>' + item.leave_type_name + '</td>' +
                    '<td>' + item.comment + '</td>' +
                    '<td>' + item.hod_comment + '</td>' +
                    '<td>' + item.hod_comment_date + '</td>' +
                    '<td>' + item.hr_remarks + '</td>' +
                    '<td>' + item.hr_remark_date + '</td>' +
                    '<td>' + item.approved_by + '</td>' +
                    '<td>' + item.status + '</td>' +
                    '</tr>';

                tableBody.append(row);
            });
        } else {
            // Display a message if there is no data for the selected year
            var noDataRow = '<tr><td colspan="12">No data available for the selected year.</td></tr>';
            tableBody.append(noDataRow);
        }
    }
</script>

@include('includes.footer')
