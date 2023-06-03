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
                            onchange="getyearwise_holiday(this.value);">
                            <option value="">Select Year</option>
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
            processing: true,
            serverSide: true,
            ajax: "{{ route('my-leave') }}",
            columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex'
                },
                {
                    data: 'from_date',
                    name: 'from_date'
                },
                {
                    data: 'to_date',
                    name: 'to_date'
                },
                {
                    data: 'days',
                    name: 'days'
                },
                {
                    data: 'leave_type',
                    name: 'leave_type'
                },
                {
                    data: 'comment',
                    name: 'comment'
                },
                {
                    data: 'hod_comment',
                    name: 'hod_comment'
                },
                {
                    data: 'hod_comment_date',
                    name: 'hod_comment_date'
                },
                {
                    data: 'hr_remarks',
                    name: 'hr_remarks'
                },
                {
                    data: 'hr_remark_date',
                    name: 'hr_remark_date'
                },
                {
                    data: 'approved_by',
                    name: 'approved_by'
                },
                {
                    data: 'status',
                    name: 'status'
                },
            ]
        });

        $(document).on("submit", "#frmLeaveType", function(e) {
            e.preventDefault();
            var formData = $("#frmLeaveType").serialize();
            /**Ajax code**/
            $.ajax({
                type: "post",
                url: "{{ route('leave-type.store') }}",
                data: formData,
                success: function(data) {
                    $('#addTypeMdl').modal('toggle');
                    $('#tblLeaves').DataTable().ajax.reload();
                },
                error: function(xhr) {
                    if (xhr.status == 422) {
                        var errors = JSON.parse(xhr.responseText);
                        $.each(errors.errors, function(i, error) {
                            $('#' + i).after(
                                '<span class="text-strong text-danger">' +
                                error + '</span>')
                        })
                    }
                }
            });
        });

        $(document).on("click", ".btn-edit", function(e) {
            e.preventDefault();
            $('.error').remove()
            var id = $(this).data('id');
            var url = "{{ route('leave-type.edit', ':id') }}";
            url = url.replace(':id', id);
            /**Ajax code**/
            $.ajax({
                type: "get",
                url: url,
                data: {
                    id: id
                },
                success: function(data) {
                    $('#addTypeMdl').modal('toggle');
                    $('#leave_type_name').val(data.data.leave_type);
                    $('#leave_id').val(data.data.id);
                },
                error: function(xhr) {
                    if (xhr.status == 422) {
                        var errors = JSON.parse(xhr.responseText);
                        $.each(errors.errors, function(i, error) {
                            $('#' + i).after(
                                '<span class="text-strong text-danger error">' +
                                error + '</span>')
                        })
                    }
                }
            });
        });
        $(document).on("click", ".btn-delete", function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var url = "{{ route('leave-type.destroy', ':id') }}";
            url = url.replace(':id', id);
            /**Ajax code**/
            if (confirm('Are you sure to delete leave type')) {
                $.ajax({
                    type: "delete",
                    url: url,
                    data: {
                        id: id
                    },
                    success: function(data) {
                        $('#tblLeaves').DataTable().ajax.reload();
                    },
                    error: function(xhr) {
                        if (xhr.status == 422) {
                            var errors = JSON.parse(xhr.responseText);
                            $.each(errors.errors, function(i, error) {
                                $('#' + i).after(
                                    '<span class="text-strong text-danger">' +
                                    error + '</span>')
                            })
                        }
                    }
                });
            }
        });
    });
</script>
@include('includes.footer')
