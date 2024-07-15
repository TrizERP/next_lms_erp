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
            @php 
                $currentYear = date('Y');
            @endphp
                <div class="col-lg-12 col-sm-3 col-xs-3 row">
                    <div class="col-md-3 pull-right">
                        <select id="cmbyear" class="form-control" name="cmbyear"
                        onchange="getYearwiseLeave(this.value);">
                            @foreach($data['allyears'] as $key=>$value)
                            <option value="{{$key}}">{{$value}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <form action="{{route('my_leave_update')}}" method="post">
                @csrf
                <div class="col-lg-12 col-sm-12 col-xs-12 mt-4">
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
                <div class="col-md-12">
                        <center>
                            <input type="submit" value="Save" id="save" class="btn btn-success hide">
                        </center>
                    </div>
                    </form>
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
        var CurrentYear = "{{$currentYear}}";
        getYearwiseLeave(CurrentYear);
    });
</script>
<script>
    function getYearwiseLeave(selectedYear) {
        // Make an Ajax request
        $('#cmbyear').val(selectedYear);
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
        $('#save').removeClass('show');
        $('#save').addClass('hide');
        var tableBody = $('#tblLeaves tbody');
        tableBody.empty(); // Clear existing rows

        if (data.length > 0) {
            $.each(data, function(index, item) {
                var comment  = (item.comment!==null) ? item.comment : '-';
                var hod_comment  = (item.hod_comment!==null) ? item.hod_comment : '-';
                var hod_comment_date  = (item.hod_comment_date!==null) ? item.hod_comment_date : '-';
                var hr_remarks  = (item.hr_remarks!==null) ? item.hr_remarks : '-';
                var hr_remark_date  = (item.hr_remark_date!==null) ? item.hr_remark_date : '-';
                var approved_by  = (item.approved_by!==null) ? item.approved_by : '-';
                var status  = (item.status!==null) ? item.status : '-';

                if(item.status=="pending"){
                    $('#save').removeClass('hide');
                    $('#save').addClass('show');
                    comment = '<input class="form-control" name="LeaveUpdate['+item.id+'][comment]">';
                    hod_comment = '<input class="form-control" name="LeaveUpdate['+item.id+'][hod_comment]">';
                    hr_remarks = '<input class="form-control" name="LeaveUpdate['+item.id+'][hr_remarks]">';
                    status = '<select class="form-control" name="LeaveUpdate['+item.id+'][status]"><option value="pending">Pending</option><option value="cancelled">Cancelled</option></select>';

                }
                // Append a new row for each item in the data
                var row = '<tr>' +
                    '<td>' + (index + 1) + '</td>' +
                    '<td>' + item.from_date + '</td>' +
                    '<td>' + item.to_date + '</td>' +
                    '<td>' + item.day_type + '</td>' +
                    '<td>' + item.leave_type_name + '</td>' +
                    '<td>' + comment + '</td>' +
                    '<td>' + hod_comment + '</td>' +
                    '<td>' + hod_comment_date + '</td>' +
                    '<td>' + hr_remarks + '</td>' +
                    '<td>' + hr_remark_date + '</td>' +
                    '<td>' + item.approved_by + '</td>' +
                    '<td>' + status + '</td>' +
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
