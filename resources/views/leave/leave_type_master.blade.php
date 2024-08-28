@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Leave Type Master</h4>
            </div>
        </div>

        <div class="card">
        @if ($sessionData = Session::get('data'))
            @if($sessionData->status_code == 1)
                <div class="alert alert-success alert-block">
            @else
                <div class="alert alert-danger alert-block">
            @endif
                <button type="button" class="close" data-dismiss="alert">Ã—</button>
                <strong>{{ $sessionData->message }}</strong>
            </div>
        @endif
            <div class="col-md-2">
                <ul id="" class="nav nav-tabs justify-content-between" role="tablist">
                    <li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top"
                        title="Assign Leave">
                        <a class="nav-link active" data-toggle="tab" href="#right-tab-1" role="tab"
                            aria-controls="right-tab-1" aria-selected="false">Assign Leave</a>
                    </li>
                    <li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top"
                        title="Leave Type">
                        <a class="nav-link" data-toggle="tab" href="#right-tab-2" role="tab"
                            aria-controls="right-tab-2" aria-selected="true">Leave Type</a>
                    </li>
                </ul>
            </div>
            <div class="col-md-12 mt-2">
                <div class="tab-content">
                    <div class="tab-pane show active" id="right-tab-1" role="tabpanel">
                        <div class="col-md-4">
                            <form action="" id="frmAssignLeave" method="post">
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="">Assign Leave</label>
                                        <select name="assign_leave" id="assign_leave" class="form-control">
                                            <option value="">Select Assign Leave</option>
                                            <option value="1">Employee Wise</option>
                                            <option value="2" selected="selected">Designation Wise</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="">Leave Carried forward</label>
                                        <select name="leave_forward" class="form-control">
                                            <option value="">Select Leave Carried Forward By</option>
                                            <option value="1">By Month</option>
                                            <option value="2" selected="selected">By Year</option>
                                        </select>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary">Save changes</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="tab-pane show" id="right-tab-2" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                @if (!empty($data['message']))
                                    <div class="alert alert-success alert-block">
                                        <button type="button" class="close" data-dismiss="alert">×</button>
                                        <strong>{{ $data['message'] }}</strong>
                                    </div>
                                @endif
                            </div>
                            <div class="col-lg-3 col-sm-3 col-xs-3">
                                <a href="{{ route('add_visitor_master.create') }}" data-toggle="modal"
                                    data-target="#addTypeMdl" class="btn btn-info add-new" onclick="addButton()"><i class="fa fa-plus"></i>
                                    Add Leave Type</a>
                            </div>
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <div class="table-responsive">
                                    <table id="tblLeaveType" class="table table-striped table-bordered"
                                        style="width:100%">
                                        <thead>
                                            <tr>
                                                <th data-toggle="tooltip" title="No">No</th>
                                                <th data-toggle="tooltip" title="Leave Type Id">Leave Type Id</th>
                                                <th data-toggle="tooltip" title="Leave Type">Leave Type</th>
                                                <th data-toggle="tooltip" title="Sort Order">Sort Order</th>
                                                <th data-toggle="tooltip" title="Status">Status</th>
                                                <th data-toggle="tooltip" title="Action" class="text-left">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>

                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Tabs content -->
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="addTypeMdl" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Leave Type</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" id="frmLeaveType" method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="">Leave Type Name</label>
                        <input type="hidden" name="leave_id" id="leave_id" value="">
                        <input type="text" name="leave_type_name" id="leave_type_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">In-Active</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" class="form-control" @if(isset($data['maxSortOrder'])) value="{{$data['maxSortOrder']}}" @endif>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </div>
            </form>
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
        var table = $('#tblLeaveType').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('leave-type.index') }}",
            columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex'
                },
                {
                    data: 'leave_type_id',
                    name: 'leave_type_id'
                },
                {
                    data: 'leave_type',
                    name: 'leave_type'
                },
                {
                    data: 'sort_order',
                    name: 'sort_order'
                },
                {
                    data: 'status',
                    name: 'status'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: true,
                    searchable: true
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
                    alert(data.message);
                    $('#addTypeMdl').modal('toggle');
                    $('#tblLeaveType').DataTable().ajax.reload();
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
                    $('#sort_order').val(data.data.sort_order);
                    $('#status').val(data.data.status);
                    $('#leave_id').val(data.data.id);
                    $('#sort_order').prop('readonly',false);
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
                        alert(data.message);
                        $('#tblLeaveType').DataTable().ajax.reload();
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

    function addButton(){
        $('#sort_order').prop('readonly',true);
        $('#leave_type_name').empty();
        $('#sort_order').empty();
        $('#leave_id').empty();
    }
</script>
@include('includes.footer')
