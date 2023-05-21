@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Holiday Master</h4>
            </div>
        </div>

        <div class="card">
            <div class="col-md-2">
                <ul id="" class="nav nav-tabs justify-content-between" role="tablist">
                    <li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top" title="Holiday">
                        <a class="nav-link active" data-toggle="tab" href="#right-tab-2" role="tab"
                            aria-controls="right-tab-2" aria-selected="true">Holiday</a>
                    </li>
                    <li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top"
                        title="Days Off">
                        <a class="nav-link" data-toggle="tab" href="#right-tab-1" role="tab"
                            aria-controls="right-tab-1" aria-selected="false">Days Off</a>
                    </li>
                </ul>
            </div>
            <div class="col-md-12 mt-2">
                <div class="tab-content">
                    <div class="tab-pane show active" id="right-tab-2" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                @if (!empty($data['message']))
                                    <div class="alert alert-success alert-block">
                                        <button type="button" class="close" data-dismiss="alert">×</button>
                                        <strong>{{ $data['message'] }}</strong>
                                    </div>
                                @endif
                            </div>
                            <div class="col-lg-12 col-sm-3 col-xs-3 row">
                                <div class="col-md-2">
                                    <a data-toggle="modal" data-target="#addHoliday" class="btn btn-info add-new"><i
                                            class="fa fa-plus"></i>
                                        Add Holiday</a>
                                    <a class="btn btn-danger delete-all"><i class="fa fa-trash"></i>
                                        Delete </a>
                                </div>
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
                                    <table id="tblHoliday" class="table table-striped table-bordered"
                                        style="width:100%">
                                        <thead>
                                            <tr>
                                                <th data-toggle="tooltip" title="Select All"><input type="checkbox"
                                                        name="" id="checkedAll"></th>
                                                <th data-toggle="tooltip" title="No">No</th>
                                                <th data-toggle="tooltip" title="Name of Holiday">Name of Holiday</th>
                                                <th data-toggle="tooltip" title="Date">Date</th>
                                                <th data-toggle="tooltip" title="Type">Type</th>
                                                <th data-toggle="tooltip" title="Department">Department</th>
                                                <th data-toggle="tooltip" title="Action">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane show" id="right-tab-1" role="tabpanel">
                        <form action="" id="frmWeekDays" method="post">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="">Monday</label>
                                        <select name="monday" id="monday" class="form-control">
                                            <option value="full"
                                                {{ $weekdays['monday'] == 'full' ? 'selected' : '' }}>
                                                Full Day</option>
                                            <option value="half"
                                                {{ $weekdays['monday'] == 'half' ? 'selected' : '' }}>
                                                Half Day</option>
                                            <option value="weekend"
                                                {{ $weekdays['monday'] == 'weekend' ? 'selected' : '' }}>Weekend
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="">Tuesday</label>
                                        <select name="tuesday" id="tuesday" class="form-control">
                                            <option value="full"
                                                {{ $weekdays['tuesday'] == 'full' ? 'selected' : '' }}>
                                                Full Day</option>
                                            <option value="half"
                                                {{ $weekdays['tuesday'] == 'half' ? 'selected' : '' }}>
                                                Half Day</option>
                                            <option value="weekend"
                                                {{ $weekdays['tuesday'] == 'weekend' ? 'selected' : '' }}>Weekend
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="">Wednesday</label>
                                        <select name="wednesday" id="wednesday" class="form-control">
                                            <option value="full"
                                                {{ $weekdays['wednesday'] == 'full' ? 'selected' : '' }}>
                                                Full Day</option>
                                            <option value="half"
                                                {{ $weekdays['wednesday'] == 'half' ? 'selected' : '' }}>
                                                Half Day</option>
                                            <option value="weekend"
                                                {{ $weekdays['wednesday'] == 'weekend' ? 'selected' : '' }}>Weekend
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="">Thursday</label>
                                        <select name="thursday" id="thursday" class="form-control">
                                            <option value="full"
                                                {{ $weekdays['thursday'] == 'full' ? 'selected' : '' }}>
                                                Full Day</option>
                                            <option value="half"
                                                {{ $weekdays['thursday'] == 'half' ? 'selected' : '' }}>
                                                Half Day</option>
                                            <option value="weekend"
                                                {{ $weekdays['thursday'] == 'weekend' ? 'selected' : '' }}>Weekend
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="">Friday</label>
                                        <select name="friday" id="friday" class="form-control">
                                            <option value="full"
                                                {{ $weekdays['friday'] == 'full' ? 'selected' : '' }}>
                                                Full Day</option>
                                            <option value="half"
                                                {{ $weekdays['friday'] == 'half' ? 'selected' : '' }}>
                                                Half Day</option>
                                            <option value="weekend"
                                                {{ $weekdays['friday'] == 'weekend' ? 'selected' : '' }}>Weekend
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="">Saturday</label>
                                        <select name="saturday" id="saturday" class="form-control">
                                            <option value="full"
                                                {{ $weekdays['saturday'] == 'full' ? 'selected' : '' }}>
                                                Full Day</option>
                                            <option value="half"
                                                {{ $weekdays['saturday'] == 'half' ? 'selected' : '' }}>
                                                Half Day</option>
                                            <option value="weekend"
                                                {{ $weekdays['saturday'] == 'weekend' ? 'selected' : '' }}>Weekend
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="">Sunday</label>
                                        <select name="sunday" id="sunday" class="form-control">
                                            <option value="full"
                                                {{ $weekdays['sunday'] == 'full' ? 'selected' : '' }}>
                                                Full Day</option>
                                            <option value="half"
                                                {{ $weekdays['sunday'] == 'half' ? 'selected' : '' }}>
                                                Half Day</option>
                                            <option value="weekend"
                                                {{ $weekdays['sunday'] == 'weekend' ? 'selected' : '' }}>Weekend
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">Save changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Tabs content -->
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="addHoliday" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Holiday</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" id="frmHoliday" method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="">Holiday Name</label>
                        <input type="hidden" name="holiday_id" id="holiday_id" value="">
                        <input type="text" name="holiday_name" id="holiday_name">
                    </div>
                    <div class="form-group">
                        <label for="">From Date</label>
                        <input class="form-control" type="date" name="from_date" id="from_date">
                    </div>
                    <div class="form-group">
                        <label for="">To Date</label>
                        <input class="form-control" type="date" name="to_date" id="to_date">
                    </div>
                    <div class="form-group">
                        <label for="">Day Type</label>
                        <select name="day_Type" id="day_Type" class="form-control">
                            <option value="full">
                                Full Day</option>
                            <option value="half">
                                Half Day</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="">Department</label>
                        <select name="department[]" id="department" class="form-control" multiple>
                            @foreach ($departments as $id => $dpt)
                                <option value="{{ $id }}">{{ $dpt }}</option>
                            @endforeach
                        </select>
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
        var table = $('#tblHoliday').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('holiday.index') }}",
            columns: [{
                    data: 'checkbox',
                    name: 'checkbox',
                    orderable: false,
                    searchable: false
                }, {
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex'
                },
                {
                    data: 'holiday_name',
                    name: 'holiday_name'
                },
                {
                    data: 'from_date',
                    name: 'from_date'
                },
                {
                    data: 'day_type',
                    name: 'day_type'
                },
                {
                    data: 'department_name',
                    name: 'department_name'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                },
            ]
        });

        $("#checkedAll").change(function() {
            if (this.checked) {
                $(".checkSingle").each(function() {
                    this.checked = true;
                });
            } else {
                $(".checkSingle").each(function() {
                    this.checked = false;
                });
            }
        });

        $(document).on("change", ".checkSingle", function(e) {
            if ($(this).is(":checked")) {
                var isAllChecked = 0;

                $(".checkSingle").each(function() {
                    if (!this.checked)
                        isAllChecked = 1;
                });

                if (isAllChecked == 0) {
                    $("#checkedAll").prop("checked", true);
                }
            } else {
                $("#checkedAll").prop("checked", false);
            }
        });

        $(document).on("submit", "#frmHoliday", function(e) {
            e.preventDefault();
            $('.error').remove()
            var formData = $("#frmHoliday").serialize();
            /**Ajax code**/
            $.ajax({
                type: "post",
                url: "{{ route('holiday.store') }}",
                data: formData,
                success: function(data) {
                    $('#addHoliday').modal('toggle');
                    $('#tblLeaveType').DataTable().ajax.reload();
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

        $(document).on("submit", "#frmWeekDays", function(e) {
            e.preventDefault();
            $('.error').remove()
            var formData = $("#frmWeekDays").serialize();
            /**Ajax code**/
            $.ajax({
                type: "post",
                url: "{{ route('holiday.weekdays') }}",
                data: formData,
                success: function(data) {
                    location.reload();
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

        $(document).on("click", ".delete-all", function(e) {
            var ids = []
            $(".checkSingle").each(function() {
                if (this.checked) {
                    ids.push($(this).attr('id'));
                }
            });
            deleteHoliday(ids)
        });
        $(document).on("click", ".btn-delete", function(e) {
            e.preventDefault();
            var ids = [];
            var id = $(this).data('id');
            ids.push(id);
        });

        function deleteHoliday(ids) {
            if (confirm('Are you sure to delete holiday')) {
                var url = "{{ route('holiday.destroy', ':id') }}";
                url = url.replace(':id', ids);
                $.ajax({
                    type: "delete",
                    url: url,
                    data: {
                        id: ids
                    },
                    success: function(data) {
                        $('#tblHoliday').DataTable().ajax.reload();
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
        }
    });

    function getyearwise_holiday(year) {
        $('#tblHoliday').DataTable().ajax.url("?year=" + year).load();;
    }
</script>
@include('includes.footer')
