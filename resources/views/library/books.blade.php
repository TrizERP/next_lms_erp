@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Books</h4>
            </div>
        </div>

        <div class="card">
            <div class="col-md-2">
                <ul id="" class="nav nav-tabs justify-content-between" role="tablist">
                    <li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top"
                        title="Book List">
                        <a class="nav-link active" data-toggle="tab" href="#right-tab-2" role="tab"
                            aria-controls="right-tab-2" aria-selected="true">Book List</a>
                    </li>
                    <li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top"
                        title="Create Book">
                        <a class="nav-link" data-toggle="tab" href="#right-tab-1" role="tab"
                            aria-controls="right-tab-1" aria-selected="false">Create Book</a>
                    </li>
                </ul>
            </div>
            <div class="col-md-12 mt-2">
                <div class="tab-content">
                    <div class="tab-pane show active" id="right-tab-2" role="tabpanel">
                        <div class="row">
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
                                <div class="col-md-2">
                                    <a class="btn btn-danger delete-all"><i class="fa fa-trash"></i>
                                        Delete </a>
                                    <a class="btn btn-info print-barcode"><i class="fa fa-barcode"></i>
                                        Print Barcode</a>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <div class="table-responsive">
                                    <table id="tblBooks" class="table table-striped table-bordered" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th data-toggle="tooltip" title="Select All"><input type="checkbox"
                                                        name="" id="checkedAll"></th>
                                                <th data-toggle="tooltip" title="No">No</th>
                                                <th data-toggle="tooltip" title="Image">Image</th>
                                                <th data-toggle="tooltip" title="Title">Title</th>
                                                <th data-toggle="tooltip" title="Subject">Subject</th>
                                                <th data-toggle="tooltip" title="Sub Title">Sub Title</th>
                                                <th data-toggle="tooltip" title="Publisher Name">Publisher Name</th>
                                                <th data-toggle="tooltip" title="Publish Year">Publish Year</th>
                                                <th data-toggle="tooltip" title="Auther Name">Auther Name</th>
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
                        <form action="" id="frmBookAdd" method="post">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Title</label>
                                            <input type="text" name="title" id="title" class="form-control"
                                                placeholder="Enter Title">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Sub Title</label>
                                            <input type="text" name="sub_title" id="sub_title"
                                                class="form-control" placeholder="Enter Sub Title">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Material Resource Type</label>
                                            <select name="material_resource_type" id="material_resource_type"
                                                class="form-control">
                                                <option value="">--Select Resource Type--</option>
                                                <option value="book">Book</option>
                                                <option value="magazine">Magazine</option>
                                                <option value="reference">Reference</option>
                                                <option value="comic">Comic</option>
                                                <option value="class_book">Class book</option>
                                                <option value="newspaper">Newspaper</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Edition</label>
                                            <input type="text" name="edition" id="edition" class="form-control"
                                                placeholder="Enter Edition">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Tags</label>
                                            <input type="text" name="tags" id="tags" class="form-control"
                                                placeholder="Enter Tags">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">No Of Items</label>
                                            <input type="number" name="no_of_items" id="no_of_items"
                                                class="form-control" placeholder="Enter No Of Items">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Author Name</label>
                                            <input type="text" name="author_name" id="author_name"
                                                class="form-control" placeholder="Enter Author Name">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">ISBN/ISSN</label>
                                            <input type="text" name="isbn_issn" id="isbn_issn"
                                                class="form-control" placeholder="Enter ISBN/ISSN">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Classification</label>
                                            <input type="text" name="classification" id="classification"
                                                class="form-control" placeholder="Enter Classification">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Publisher Name</label>
                                            <input type="text" name="publisher_name" id="publisher_name"
                                                class="form-control" placeholder="Enter Publisher Name">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Publish Year</label>
                                            <input type="number" maxlength="4" pattern="([0-9]{4})"
                                                name="publish_year" id="publish_year" class="form-control"
                                                placeholder="YYYY">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Publishing Place</label>
                                            <input type="text" name="publish_place" id="publish_place"
                                                class="form-control" placeholder="Enter Publishing Place">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Book Size/ Number of page</label>
                                            <input type="number" type="any" name="collation" id="collation"
                                                class="form-control" placeholder="Enter Book Size/ Number of page">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Series Title</label>
                                            <input type="text" name="series_title" id="series_title"
                                                class="form-control" placeholder="Enter Series Title">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Call Number</label>
                                            <input type="number" type="any" name="call_number" id="call_number"
                                                class="form-control" placeholder="Enter Call Number">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Language</label>
                                            <input type="text" name="language" id="language"
                                                class="form-control" placeholder="Enter Language">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Source</label>
                                            <input type="text" name="source" id="source" class="form-control"
                                                placeholder="Enter Source">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Subject</label>
                                            <input type="text" name="subject" id="subject" class="form-control"
                                                placeholder="Enter Subject">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Price</label>
                                            <input type="number" step="any" name="price" id="price"
                                                class="form-control" placeholder="Enter Price">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Price Currency</label>
                                            <input type="text" name="price_currency" id="price_currency"
                                                class="form-control" placeholder="Enter Price Currency">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Notes</label>
                                            <textarea name="notes" id="notes" cols="30" rows="3" class="form-control"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Review</label>
                                            <textarea name="review" id="review" cols="30" rows="3" class="form-control"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Image</label>
                                            <input type="file" name="image" id="image" class="form-control"
                                                placeholder="Enter Image">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">File Attachment</label>
                                            <input type="file" name="file_att" id="file_att"
                                                class="form-control" placeholder="Enter File Attachment">
                                        </div>
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
    <div class="modal fade" id="mdlCirculation" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form class="form-group" id="frmCirculation" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <label for="">Student Enroll No</label>
                                <input type="text" name="enroll_no" id="enroll_no" placeholder="Enter Enroll No."
                                    class="form-control">
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary mt-4">Fetch Details</button>
                            </div>
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
        var table = $('#tblBooks').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('books.index') }}",
            columns: [{
                    data: 'checkbox',
                    name: 'checkbox',
                    orderable: false,
                    searchable: false
                }, {
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex'
                }, {
                    data: 'image',
                    name: 'image'
                },
                {
                    data: 'title',
                    name: 'title'
                },
                {
                    data: 'subject',
                    name: 'subject'
                },
                {
                    data: 'sub_title',
                    name: 'sub_title'
                },
                {
                    data: 'publisher_name',
                    name: 'publisher_name'
                },
                {
                    data: 'publish_year',
                    name: 'publish_year'
                },
                {
                    data: 'author_name',
                    name: 'author_name'
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

        $(document).on("submit", "#frmCirculation", function(e) {
            e.preventDefault();
            $('.error').remove()
            var url = "{{ route('books.show', ':id') }}";
            url = url.replace(':id', $('#enroll_no').val());
            var formData = new FormData($("#frmCirculation")[0]);
            /**Ajax code**/
            $.ajax({
                type: "get",
                url: url,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data) {
                    if (data.status) {
                        alert(data.message);
                        location.reload();
                    }
                    $('#tblLeaveType').DataTable().ajax.reload();
                },
                error: function(xhr) {
                    if (xhr.status == 422) {
                        var errors = JSON.parse(xhr.responseText);
                        $.each(errors.errors, function(i, error) {
                            $('#' + i).after(
                                '<span class="text-strong text-danger error text-capitalize">' +
                                error + '</span>')
                        })
                    }
                }
            });
        });

        $(document).on("submit", "#frmBookAdd", function(e) {
            e.preventDefault();
            $('.error').remove()
            var formData = new FormData($("#frmBookAdd")[0]);
            /**Ajax code**/
            $.ajax({
                type: "post",
                url: "{{ route('books.store') }}",
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data) {
                    if (data.status) {
                        alert(data.message);
                        location.reload();
                    }
                    $('#tblLeaveType').DataTable().ajax.reload();
                },
                error: function(xhr) {
                    if (xhr.status == 422) {
                        var errors = JSON.parse(xhr.responseText);
                        $.each(errors.errors, function(i, error) {
                            $('#' + i).after(
                                '<span class="text-strong text-danger error text-capitalize">' +
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
        $(document).on("click", ".circulation", function(e) {
            e.preventDefault();
            $('#mdlCirculation').modal('toggle');
        });
        $(document).on("click", ".btn-delete", function(e) {
            e.preventDefault();
            var ids = [];
            var id = $(this).data('id');
            ids.push(id);
        });
        $(document).on("click", ".print-barcode", function(e) {
            e.preventDefault();
            var ids = [];
            var id = $(this).data('id');
            ids.push(id);
            printBarcode(ids)
        });

        function printBarcode(ids) {
            var url = "{{ route('books.barcode', ':id') }}";
            url = url.replace(':id', ids);
            $.ajax({
                type: "get",
                url: url,
                data: {
                    id: ids
                },
                success: function(data) {
                    $('#tblBooks').DataTable().ajax.reload();
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
                        $('#tblBooks').DataTable().ajax.reload();
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
        $('#tblBooks').DataTable().ajax.url("?year=" + year).load();;
    }
</script>
@include('includes.footer')
