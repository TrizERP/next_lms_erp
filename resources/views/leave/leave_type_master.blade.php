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
                    <a href="{{ route('add_visitor_master.create') }}" data-toggle="modal" data-target="#addTypeMdl"
                        class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Leave Type</a>
                </div>

                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th data-toggle="tooltip" title="Leave Type Id">Leave Type Id</th>
                                    <th data-toggle="tooltip" title="Leave Type">Leave Type</th>
                                    <th data-toggle="tooltip" title="Action">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- @foreach ($leaves as $leave)
                                    <td>{{ $leave->leave_type_id }}</td>
                                    <td>{{ $leave->leave_type }}</td>
                                    <td><button class="btn btn-warning btn-edit"><i class="fa fa-pencil"></i></button>
                                    </td>
                                @endforeach --}}
                            </tbody>

                        </table>
                    </div>
                </div>

            </div>
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
            <div class="modal-body">
                <form action="" id="frmLeaveType" method="post">
                    <div class="form-group">
                        <label for="">Leave Type Name</label>
                        <input type="text" name="leave_type_name" id="leave_type_name">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')

<script type="text/javascript">
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<script>
    $(document).ready(function() {
        var table = $('#example').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('leave.index') }}",
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
                    data: 'action',
                    name: 'action',
                    orderable: true,
                    searchable: true
                },
            ]
        });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function(i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function() {
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
