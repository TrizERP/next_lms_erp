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
            <div class="col-md-4">
                <ul id="" class="nav nav-tabs justify-content-between" role="tablist">
                    <li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top"
                        title="Publisher List">
                        <a class="nav-link active" data-toggle="tab" href="#right-tab-1" role="tab"
                            aria-controls="right-tab-2" aria-selected="true">Publisher List</a>
                    </li>
                    <li class="nav-item" role="presentation" data-toggle="tooltip" data-placement="top"
                        title="Author List">
                        <a class="nav-link" data-toggle="tab" href="#right-tab-2" role="tab"
                            aria-controls="right-tab-2" aria-selected="true">Author/Editor List</a>
                    </li>
                </ul>
            </div>
            <div class="col-md-12 mt-2">
                <div class="tab-content">
                    <div class="tab-pane show active" id="right-tab-1" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <div class="table-responsive">
                                    <table id="tblBooks" class="table table-striped table-bordered" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th data-toggle="tooltip" title="Id">Id</th>
                                                <th data-toggle="tooltip" title="Publisher Name">Publisher Name</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($publisher_names as $key => $publisher)
                                                <tr>
                                                    <td>{{ $key }}</td>
                                                    <td>{{ $publisher }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane show" id="right-tab-2" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <div class="table-responsive">
                                    <table id="tblBooks" class="table table-striped table-bordered" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th data-toggle="tooltip" title="Id">Id</th>
                                                <th data-toggle="tooltip" title="Author Name">Author Name</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($author_names as $key => $author)
                                                <tr>
                                                    <td>{{ $key }}</td>
                                                    <td>{{ $author }}</td>
                                                </tr>
                                            @endforeach
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
    <div class="modal fade" id="mdlCirculation" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form class="form-group" id="frmCirculation" method="post">
                    <div class="modal-body">
                        @csrf
                        <input type="hidden" name="bookId" id="bookId" value="">
                        <div class="row div_enroll_no">
                            <div class="col-md-4">
                                <label for="">Student Enroll No</label>
                                <input type="text" name="enroll_no" id="enroll_no" placeholder="Enter Enroll No."
                                    class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="">Item Code</label>
                                <input type="text" name="issue_item_code" id="issue_item_code"
                                    placeholder="Enter Item Code" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary mt-4 fetch-stud">Fetch
                                    Details</button>
                            </div>
                            <div class="row divUserDetail"></div>
                        </div>

                    </div>
                    <div class="row div_item_code">
                        <div class="col-md-6">
                            <label for="">Item Code</label>
                            <input type="text" name="item_code" id="item_code" placeholder="Enter Item Code"
                                class="form-control">
                        </div>
                        <div class="col-md-6 mt-3">
                            <button type="button" class="btn btn-danger return-book" data-id="">Return</button>
                        </div>
                    </div>
                    <div class="modal-footer div_enroll_no">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Issue Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="mdlViewBarcode" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"> Barcode of Book</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="mdlViewBarcode"></div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="mdlItemBook" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalItemTitle"> Items of Book</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="mdlItemBook"></div>
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
        // var table = $('#tblBooks').DataTable({
        //     processing: true,
        //     serverSide: true,
        //     ajax: "{{ route('books.index') }}",
        //     columns: [{
        //             data: 'checkbox',
        //             name: 'checkbox',
        //             orderable: false,
        //             searchable: false
        //         }, {
        //             data: 'DT_RowIndex',
        //             name: 'DT_RowIndex'
        //         }, {
        //             data: 'item_code',
        //             name: 'item_code'
        //         }, {
        //             data: 'image',
        //             name: 'image'
        //         },
        //         {
        //             data: 'title',
        //             name: 'title'
        //         },
        //         {
        //             data: 'subject',
        //             name: 'subject'
        //         },
        //         {
        //             data: 'sub_title',
        //             name: 'sub_title'
        //         },
        //         {
        //             data: 'publisher_name',
        //             name: 'publisher_name'
        //         },
        //         {
        //             data: 'publish_year',
        //             name: 'publish_year'
        //         },
        //         {
        //             data: 'author_name',
        //             name: 'author_name'
        //         },
        //         {
        //             data: 'action',
        //             name: 'action',
        //             orderable: false,
        //             searchable: false
        //         },
        //     ]
        // });

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
            var url = "{{ route('books.issue') }}";
            var formData = new FormData($("#frmCirculation")[0]);
            /**Ajax code**/
            $.ajax({
                type: "post",
                url: url,
                dataType: 'json',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data) {
                    alert(data.messsage)
                    $('.divUserDetail').html(data.data);
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

        $(document).on("click", ".btn-edit", function(e) {
            $('#navLinkList').removeClass('active')
            $('#navLinkCreate').addClass('active')
            $('#right-tab-2').removeClass('active')
            $('#right-tab-1').addClass('active')
            var id = $(this).data('id')
            var url = "{{ route('books.edit', ':id') }}";
            var url = url.replace(':id', id);
            $.ajax({
                type: "get",
                url: url,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data) {
                    $('#id').val(data.data.id);
                    $('#title').val(data.data.title);
                    $('#sub_title').val(data.data.sub_title);
                    $('#material_resource_type').val(data.data.material_resource_type);
                    $('#edition').val(data.data.edition);
                    $('#tags').val(data.data.tags);
                    $('#no_of_items').val(data.data.no_of_items);
                    $('#author_name').val(data.data.author_name);
                    $('#isbn_issn').val(data.data.isbn_issn);
                    $('#classification').val(data.data.classification);
                    $('#publisher_name').val(data.data.publisher_name);
                    $('#publish_year').val(data.data.publish_year);
                    $('#publish_place').val(data.data.publish_place);
                    $('#collation').val(data.data.collation);
                    $('#series_title').val(data.data.series_title);
                    $('#call_number').val(data.data.call_number);
                    $('#language').val(data.data.language);
                    $('#source').val(data.data.source);
                    $('#subject').val(data.data.subject);
                    $('#price').val(data.data.price);
                    $('#price_currency').val(data.data.price_currency);
                    $('#notes').val(data.data.notes);
                    $('#review').val(data.data.review);
                    $('#image').val(data.data.image);
                    $('#file_att').val(data.data.file_att);
                    console.log(data);
                },
                error: function(xhr) {
                    console.log(xhr);
                }
            });
        });

        function deleteItem(id) {
            if (confirm('Are you sure to delete item')) {
                var url = "{{ route('books.items.destroy', ':id') }}";
                url = url.replace(':id', id);
                $.ajax({
                    type: "delete",
                    url: url,
                    data: {
                        id: id
                    },
                    success: function(data) {
                        console.log(data.book_id);
                        $('#mdlItemBook').modal('toggle');
                        showItemByBook(data.book_id)
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
        $(document).on("click", ".return-book", function(e) {
            $('.error').remove()
            var url = "{{ route('books.return', ':id') }}";
            var enroll_no = $('#enroll_no').val()
            var item_code = $('#item_code').val()
            if ($(this).data('id') != '') {
                url = url.replace(':id', $(this).data('id'));
            } else {
                url = url.replace(':id', item_code);
            }
            /**Ajax code**/
            $.ajax({
                type: "get",
                url: url,
                dataType: 'json',
                data: {
                    enroll_no: enroll_no,
                    item_code: item_code
                },
                success: function(data) {
                    alert(data.message)
                    $('#mdlCirculation').modal('toggle');
                    $('.divUserDetail').html(data.data);
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
        $(document).on("click", ".fetch-stud", function(e) {
            $('.error').remove()
            var url = "{{ route('books.show', ':id') }}";
            url = url.replace(':id', $('#enroll_no').val());
            url += "?item_code=" + $('#item_code').val()
            // var item_code = $('#item_code').val()
            /**Ajax code**/
            $.ajax({
                type: "get",
                url: url,
                dataType: 'json',
                success: function(data) {
                    $('.divUserDetail').html(data.data);
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
            deleteBook(ids)
        });

        $(document).on("click", ".delete-item", function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            deleteItem(id);
        });
        $(document).on("click", ".printBarcode", function(e) {
            var ids = []
            $(".checkSingle").each(function() {
                if (this.checked) {
                    ids.push($(this).attr('id'));
                }
            });
            printBarcode(ids)
        });
        $(document).on("click", ".circulation", function(e) {
            e.preventDefault();
            $('.divUserDetail').html()
            var id = $(this).data('id');
            var name = $(this).data('name');
            $('.div_enroll_no').show();
            $('.div_item_code').hide();
            $('#modalTitle').text(name);
            $('#bookId').val(id);
            $('#mdlCirculation').modal('toggle');
        });
        $(document).on("click", ".quick_retur", function(e) {
            e.preventDefault();
            $('.divUserDetail').html()
            var id = $(this).data('id');
            var name = $(this).data('name');
            $('.div_enroll_no').hide();
            $('.div_item_code').show();
            $('#modalTitle').text(name);
            $('#bookId').val(id);
            $('#mdlCirculation').modal('toggle');
        });
        $(document).on("click", ".btn-delete", function(e) {
            e.preventDefault();
            var ids = [];
            var id = $(this).data('id');
            ids.push(id);
            deleteBook(ids)
        });
        $(document).on("click", ".btn-library-item", function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            showItemByBook(id)
        });
        $(document).on("click", ".print-barcode", function(e) {
            e.preventDefault();
            var ids = [];
            var id = $(this).data('id');
            ids.push(id);
            printBarcode(ids)
        });

        function showItemByBook(id) {
            var url = "{{ route('books.item', ':id') }}";
            url = url.replace(':id', id);
            $.ajax({
                type: "get",
                url: url,
                data: {
                    id: id
                },
                success: function(data) {
                    console.log(data.data);
                    $('.mdlItemBook').html(data.data);
                    $('#mdlItemBook').modal('toggle');
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

        function deleteBook(ids) {
            if (confirm('Are you sure to delete holiday')) {
                var url = "{{ route('books.destroy', ':id') }}";
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

    function getSubjects(subject) {
        $('#tblBooks').DataTable().ajax.url("?subject=" + subject).load();;
    }

    function getPublishers(publisher) {
        $('#tblBooks').DataTable().ajax.url("?publisher_name=" + publisher).load();;
    }

    function getAuthors(author) {
        $('#tblBooks').DataTable().ajax.url("?author_name=" + author).load();;
    }
</script>
@include('includes.footer')
