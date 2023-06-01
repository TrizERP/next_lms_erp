@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Leave Apply</h4>
            </div>
        </div>

        <div class="card">
            <div class="col-md-12 mt-2">
                <div class="col-md-4">
                    <form action="" id="frmApplyLeave" method="post">
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="">Type Leave</label>
                                <select name="type_leave" id="type_leave" class="form-control">
                                    <option value="">Select Type Leave</option>
                                    <option value="employee">Employee</option>
                                    <option value="self">Self</option>
                                </select>
                            </div>
                            <div class="form-group div-emp d-none">
                                <label for="">Department</label>
                                <select name="department_id" id="department_id" class="form-control">
                                    <option value="">Select Department</option>
                                    @foreach ($departments as $id => $department)
                                        <option value="{{ $id }}">{{ $department }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group div-emp d-none">
                                <label for="">Employee</label>
                                <select name="employee_id" id="employee_id" class="form-control">
                                    <option value="">Select Employee</option>
                                    @foreach ($users as $key => $row)
                                        <option value="{{ $row->id }}">{{ $row->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="">Leave Type</label>
                                <select name="leave_type" id="leave_type" class="form-control">
                                    <option value="">Select Leave Type</option>
                                    @foreach ($leave_types as $key => $row)
                                        <option value="{{ $row->id }}">{{ $row->leave_type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="">Day Type</label>
                                <select name="day_type" id="day_type" class="form-control">
                                    <option value="">Select Day Type</option>
                                    <option value="full">Full</option>
                                    <option value="half">Half</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="">From Date</label>
                                <input type="date" name="from_date" id="from_date" class="form-control">
                            </div>
                            <div class="form-group to_date">
                                <label for="">To Date</label>
                                <input type="date" name="to_date" id="to_date" class="form-control">
                            </div>
                            <div class="form-group slot">
                                <label for="">Slot</label>
                                <select name="slot" id="slot" class="form-control">
                                    <option value="">Select Slot</option>
                                    <option value="first_half">First half</option>
                                    <option value="second_half">Second Half</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="">Comment</label>
                                <textarea name="comment" id="comment" cols="30" rows="10" class="form-control"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
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

        $(document).on("submit", "#frmApplyLeave", function(e) {
            e.preventDefault();
            $('.error').remove()
            var formData = $("#frmApplyLeave").serialize();
            /**Ajax code**/
            $.ajax({
                type: "post",
                url: "{{ route('leave-apply.store') }}",
                data: formData,
                success: function(data) {
                    location.reload()
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

        $(document).on("change", "#type_leave", function(e) {
            if ($(this).val() == 'self') {
                $('.div-emp').addClass('d-none');
            } else {
                $('.div-emp').removeClass('d-none');
            }
        });

        $(document).on("change", "#day_type", function(e) {
            if ($(this).val() == 'half') {
                $('.to_date').addClass('d-none');
                $('.slot').removeClass('d-none');
            } else {
                $('.to_date').removeClass('d-none');
                $('.slot').addClass('d-none');
            }
        });

        $(document).on("click", ".btn-edit", function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var url = "{{ route('leave-apply.edit', ':id') }}";
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
                                '<span class="text-strong text-danger">' +
                                error + '</span>')
                        })
                    }
                }
            });
        });
        $(document).on("click", ".btn-delete", function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var url = "{{ route('leave-apply.destroy', ':id') }}";
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
</script>
@include('includes.footer')
