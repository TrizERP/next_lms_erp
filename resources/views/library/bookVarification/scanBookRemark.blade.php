@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Book Verification Remarks</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    @if($sessionData['status'] == "1")
                        <div class="alert alert-success alert-block">
                    @else
                        <div class="alert alert-danger alert-block">
                            @endif
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @endif
            </div>
            <div class="row">
                <form action="{{route('scan_books_remarks.index')}}" class="col-md-12 mb-4">
                    @csrf
                    <center>
                    <div style="width:100%;padding:20px;box-shadow:5px 5px 5px 5px #ddd;display:flex;justify-content:center">
                        <label for="item_code" style="padding:12px"><b>Item Code : </b></label>
                        <input type="text" class="form-control" name="item_code" id="item_code" @if(isset($data['searchedItem'])) value="{{$data['searchedItem']}}" @else placeholder="Search Item Status Name" @endif style="width:500px;" required>
                        <input type="submit" value="Search" name="submit" class="btn btn-primary ml-4">
                    </div>
                    </center>
                </form>
            </div>

            @if(isset($data['bookData']) && !empty($data['bookData']))
            <form class="row" action="{{route('scan_books_remarks.store')}}" method="POST" onsubmit="return validateForm()">
            @csrf
                <div class="table-responsive">
                    <table id="example" class="table table-box table-bordered">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Item Code</th>
                                <th>Title</th>
                                <th>Collection Type</th>
                                <th>Remarks</th>
                                <th class="text-left">Item Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['bookData'] as $key => $value)
                            <tr>
                                <td><input type="checkbox" name="checked[{{$value['id']}}]" class="ckbox1" value="1"> 
                                {{$key+1}}</td>
                                <td>{{$value['item_code']}}</td>
                                <td>{{$value['book_title']}}</td>
                                <td>{{$value['collection_type']}}</td>
                                <td>
                                    <textarea name="remarks[{{$value['id']}}]" id="item_remarks" class="form-control item_remarks resizableVertical" rows="3" cols="10" disabled="true">{{ ($value['remarks']) ? $value['remarks'] : ''}}</textarea>
                                </td>
                                <td>
                                    <div class="form-group">
                                        <select name="item_status[{{$value['id']}}]" id="item_status" class="form-control item_status" disabled="true">
                                            <option value="">Select Status</option>
                                            @foreach($data['statusTypes'] as $k=>$v)
                                            <option value="{{$v['id']}}" @if($value['item_status_id']==$v['id']) selected @endif>{{$v['item_status_name']}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="col-md-12">
                    <center>
                        <input type="submit" value="Submit" class="btn btn-success mt-4">
                    </center>
                </div>
            </form>
            @endif
        </div>

    </div>
</div>



@include('includes.footerJs')
<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Item Verification Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Item Verification Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Item Verification Report'},
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Item Verification Report',
                },
                'pageLength'
            ],
        });
        //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search(this.value)
                        .draw();
                }
            });
        });
    });

    $(function () {

        var $tblChkBox = $("input:checkbox");
 
        $(".ckbox1").on("click", function () {
            var row = $(this).closest('tr');
            var item_status = row.find('.item_status')
            var item_remarks = row.find('.item_remarks');
             
            item_status.prop('disabled', function (i, v) {
                return !v;
            });
            item_remarks.prop('disabled', function (i, v) {
                return !v;
            });
        });

        });

        function validateForm() {
        var checkboxes = $("input:checkbox");
        var anyChecked = false;
        
        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                anyChecked = true;
            }
        });
        
        if (!anyChecked) {
            alert("Please select at least one checkbox");
            return false; 
        }
        
        return true;
    }

</script>
@include('includes.footer')
@endsection