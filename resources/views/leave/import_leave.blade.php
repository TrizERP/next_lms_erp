@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Leave Import</h4>
            </div>
        </div>

        <div class="card">
            <div class="col-md-12 mt-2">
                <div class="col-md-4">
                    <form action="" id="frmImportLeave" method="post" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="">Upload File</label>
                                <input type="file" name="upload_file" id="upload_file" class="form-control">
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Save</button>
                                <a href="{{asset('excel_upload/Sample_Leave_Data.xlsx')}}" download class="btn btn-primary">Download Sample XLS File</a>
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

        $(document).on("submit", "#frmImportLeave", function(e) {
            e.preventDefault();
            $('.error').remove()
            var formData = new FormData($("#frmImportLeave")[0]);
            /**Ajax code**/
            $.ajax({
                type: "post",
                url: "{{ route('import-leave') }}",
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data) {
                    alert('Leave Imported Successfully')
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
