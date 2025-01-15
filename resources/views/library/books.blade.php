
{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@section('container')

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
                        title="Create Book" id="create-book">
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
                                <div class="col-md-3  pull-right" >
                                <label for="">Search Item</label>
                                <input type="text" class="form-control" placeholder="Enter item code" id="search_item" name="search_item" onkeyup="getItemCode(this.value);">
                                </div>
                                <div class="col-md-3 pull-right">
                                    <label for="">Status</label>
                                    <select id="bookFilter" class="form-control" name="bookFilter"
                                        onchange="getBooks(this.value);">
                                        <option value="">All</option>
                                        <option value="issued">Issued Books</option>
                                        <option value="due">Due Books</option>
                                        <option value="overdue">Over Due</option>
                                    </select>
                                </div>
                                <div class="col-md-3 pull-right">
                                    <label for="">Subject</label>
                                    <select id="subjectFilter" class="form-control" name="subjectFilter"
                                        onchange="getSubjects(this.value);">
                                        <option value="">All</option>
                                        @foreach ($subjects as $key => $value)
                                            @if (!empty($value))
                                                <option value="{{ $value }}">{{ $value }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 pull-right">
                                    <label for="">Publisher Name</label>
                                    <select id="publisherFilter" class="form-control" name="publisherFilter"
                                        onchange="getPublishers(this.value);">
                                        <option value="">All</option>
                                        @foreach ($publisher_names as $key => $value)
                                            @if (!empty($value))
                                                <option value="{{ $value }}">{{ $value }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 pull-right">
                                    <label for="">Author Name</label>
                                    <select id="authorFilter" class="form-control" name="authorFilter"
                                        onchange="getAuthors(this.value);">
                                        <option value="">All</option>
                                        @foreach ($author_names as $key => $value)
                                            @if (!empty($value))
                                                <option value="{{ $value }}">{{ $value }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <!-- 12-08-2024  start -->
                                <div class="col-md-3  pull-right" >
                                    <label for="">Search Classification Number</label>
                                    <input type="text" class="form-control" placeholder="Enter Classification Number" id="classification_number" name="classification_number" onkeyup="getClassification(this.value);">
                                </div>
                                <div class="col-md-3  pull-right" >
                                    <label for="">Search ISBN/ISSN</label>
                                    <input type="text" class="form-control" placeholder="Enter ISBN/ISSN" id="SearchIsbnIssn" name="isbn_issn" onkeyup="getIsbnIssn(this.value);">
                                </div>
                                <!-- 12-08-2024  end -->
                                <div class="col-md-4 mt-2" style="display:none">
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
                                                <th data-toggle="tooltip" title="item_codes">Item Code</th>
                                                <th data-toggle="tooltip" title="Title">Title</th>
                                                <th data-toggle="tooltip" title="Subject">Subject</th>
                                                <th data-toggle="tooltip" title="Sub Title">Sub Title</th>
                                                <th data-toggle="tooltip" title="Publisher Name">Publisher Name</th>
                                                <th data-toggle="tooltip" title="Publish Year">Publish Year</th>
                                                <th data-toggle="tooltip" title="Auther Name">Auther Name</th>
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
                                            <input type="number" name="no_of_items" id="no_of_items" class="form-control">
                                        </div>
                                    </div>
                                   <div class="col-md-4"  id="otherItemCOde">
                                        <div class="form-group">
                                            <label for="">Item Code</label>
                                            <input type="text" id="item_code_value" id="radioItem" class="form-control"  readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4" id="mmisItemCOde">
                                    <label class="control-label">Item Code<span style="color: red;"></span></label>
                                        <div class="radio-list">
                                            <label class="radio-inline p-0">
                                                <div class="radio radio-success">
                                                    <input type="radio" name="item_code_value" id="radioItem1" class="purchase item_code_value">
                                                    <label for="male">Purchase <br><span id="purchase"></span></label>
                                                </div>
                                            </label>
                                            <label class="radio-inline">
                                                <div class="radio radio-success">
                                                    <input type="radio" name="item_code_value"  id="radioItem2" class="donate item_code_value">
                                                    <label for="female">Donate <br><span id="donate"></span></label>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Author/Editor Name</label>
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
                                            <input type="number" type="any" name="pages" id="pages"
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
                                            <input type="text" type="any" name="call_number" id="call_number"
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
                                            <input type="file" name="image" id="image" class="form-control" accept="image/*"  placeholder="Enter Image">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">File Attachment</label>
                                            <input type="file" name="file_att" id="file_att" class="form-control" placeholder="Enter File Attachment">
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
                    <h5 class="modal-title" id="modalTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form class="form-group" id="frmCirculation" method="post">
                    <div class="modal-body">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <label for="">Student Enroll No</label>
                                <input type="hidden" name="bookId" id="bookId" value="">
                                <input type="text" name="enroll_no" id="enroll_no" placeholder="Enter Enroll No." class="form-control">
                            </div>
                            <div class="col-md-6">
                                <button type="button" class="btn btn-primary mt-4 fetch-stud">Fetch Details</button>
                            </div>
                        </div>
                        <div class="row divUserDetail"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="issue_book_check">Issue Book</button>
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
                },{
                    data: 'item_codes',
                    name: 'item_codes'
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
        $(document).on("click", "#create-book", function(e) {
            $('input[name="id"]').remove();
            // Empty all input fields
            $('input[type="text"]').val('');
            $('input[type="number"]').val('');   
            $('#no_span').remove();      
            $('#no_of_items').val(1);       
            $('#no_of_items').prop('readonly',false);
            $('#item_code_value').val('{{$nextItemCode}}');
            @if(session()->get('sub_institute_id')!=47)
                $('#mmisItemCOde').hide();
                $('#otherItemCOde').show();
                // added on 15-01-2025
                $('#title,#item_code_value,#no_of_items,#author_name,#isbn_issn,#classification,#publisher_name,#publish_year,#publish_place,#pages,#series_title,#call_number,#language,#source,#subject,#price,#price_currency,#notes,#review, #edition, #tags, #no_of_items').prop('required', true);
            @else
                $('#otherItemCOde').hide();
                $('#mmisItemCOde').show();
                // added on 15-01-2025
                $('#title,#no_of_items,input[name="item_code_value"]').prop('required', true);
            @endif
            $('#purchase').text('{{$nextItemCode}}');
            $('#donate').text('{{$DonateCode}}');
            $('.purchase').val('{{$nextItemCode}}');
            $('.donate').val('{{$DonateCode}}');

        //    $('#title,#item_code_value,#no_of_items,#author_name,#isbn_issn,#classification,#publisher_name,#publish_year,#publish_place,#pages,#series_title,#call_number,#language,#source,#subject,#price,#price_currency,#notes,#review, #edition, #tags, #no_of_items').prop('required', true);     // commented on 15-01-2025
                     
        })
        $(document).on("click", ".btn-edit", function(e) {
           
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
                    console.log(data);
                    if(data.data.length > 0){
                        var newInput = $('<input>').attr({
                            type: 'hidden',
                            name: 'id', // Set a unique name for the new input
                            class: 'form-control',
                            value: data.data[0].id
                        });

                        // Add the new input element after the existing input with id 'title'
                        $('#title').after(newInput);
                        $('#no_span').remove();                                       
                        $('#no_of_items').after(`<span id="no_span" style="color:red;font-size:12px">To Add new Item Code "No Of Items" must be greater then 0 <span>`);
                        $('#navLinkList').removeClass('active')
                        $('#navLinkCreate').addClass('active')
                        $('#right-tab-2').removeClass('active')
                        $('#right-tab-1').addClass('active')
                        $('#title').val(data.data[0].title);
                        $('#sub_title').val(data.data[0].sub_title);
                        $('#material_resource_type').val(data.data[0].material_resource_type);
                        $('#edition').val(data.data[0].edition);
                        $('#tags').val(data.data[0].tags);
                        $('#no_of_items').val(data.data[0].no_of_items);
                        $('#no_of_items').prop('readonly',true);
                        $('#item_code_value').val(data.data[0].item_codes);
                        @if(session()->get('sub_institute_id')==47) 
                        $('#mmisItemCOde').hide();
                        $('#otherItemCOde').show();
                        @endif
                        $('#author_name').val(data.data[0].author_name);
                        $('#isbn_issn').val(data.data[0].isbn_issn);
                        $('#classification').val(data.data[0].classification);
                        $('#publisher_name').val(data.data[0].publisher_name);
                        $('#publish_year').val(data.data[0].publish_year);
                        $('#publish_place').val(data.data[0].publish_place);
                        $('#pages').val(data.data[0].pages);
                        $('#series_title').val(data.data[0].series_title);
                        $('#call_number').val(data.data[0].call_number);
                        $('#language').val(data.data[0].language);
                        $('#source').val(data.data[0].source);
                        $('#subject').val(data.data[0].subject);
                        $('#price').val(data.data[0].price);
                        $('#price_currency').val(data.data[0].price_currency);
                        $('#notes').val(data.data[0].notes);
                        $('#review').val(data.data[0].review);
                        $('#image').val(data.data[0].image);
                        $('#file_att').val(data.data[0].file_att);
                    } else{
                        alert('Something went wrong');
                    }  
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
                        // console.log(data.book_id);
                        $('#mdlItemBook').modal('toggle');
                        $('.modal-backdrop').remove();
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
            url = url.replace(':id', $(this).data('id'));
            var enroll_no = $('#enroll_no').val();
            var book_id = $('#bookId').val();

            /**Ajax code**/
            $.ajax({
                type: "get",
                url: url,
                dataType: 'json',
                data: {
                    enroll_no: enroll_no,
                    book_id : book_id,
                },
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
        $(document).on("click", ".fetch-stud", function(e) {
            $('.error').remove()
            var url = "{{ route('books.show', ':id') }}";
            url = url.replace(':id', $('#enroll_no').val());
            var book_id = $('#bookId').val();
          
            /**Ajax code**/
            $.ajax({
                type: "get",
                url: url+'?book_id='+book_id,
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
            var id = $(this).data('id');
            var name = $(this).data('name');
            $('.divUserDetail').empty();
            $('#enroll_no').val('');            
            
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

    function getBooks(status) {
        $('#tblBooks').DataTable().ajax.url("?book_status=" + status).load();;
    }

    function getSubjects(subject) {
        $('#tblBooks').DataTable().ajax.url("?subject=" + subject).load();;
    }

    function getPublishers(publisher) {
        $('#tblBooks').DataTable().ajax.url("?publisher_name=" + publisher).load();;
    }
    function getItemCode(item) {
        $('#tblBooks').DataTable().ajax.url("?search_item=" + item).load();;
    }

    // 12-08-2024
    function getClassification(number) {
        $('#tblBooks').DataTable().ajax.url("?classification_no=" + number).load();;
    }
    function getIsbnIssn(number) {
        $('#tblBooks').DataTable().ajax.url("?isbn_issn=" + number).load();;
    }
    // 12-08-2024
    function getAuthors(author) {
        $('#tblBooks').DataTable().ajax.url("?author_name=" + author).load();;
    }
</script>
@include('includes.footer')
@endsection
