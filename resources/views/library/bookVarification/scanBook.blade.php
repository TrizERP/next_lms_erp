@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Book Verification</h4>
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
                <form action="{{route('scan_books.store')}}" class="col-md-12 mb-4" method="POST">
                    @csrf
                    <center>
                    <div style="width:100%;padding:20px;box-shadow:5px 5px 5px 5px #ddd;display:flex;justify-content:center">
                        <label for="item_code" style="padding:12px"><b>Item Code : </b></label>
                        <input type="text" class="form-control" name="item_code" id="item_code" @if(isset($data['searchedItem'])) value="{{$data['searchedItem']}}" @else placeholder="Search Item Status Name" @endif style="width:500px;" required>
                        <input type="submit" value="Scan Books" name="submit" class="btn btn-primary ml-4">
                    </div>
                    </center>
                </form>
            </div>

            @if(isset($data['bookData']) && !empty($data['bookData']))
            <div class="row">
                <div class="table-responsive">
                    <table id="example" class="table table-box table-bordered">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Item Code</th>
                                <th>Title</th>
                                <th>Collection Type</th>
                                <th>Scan Status</th>
                                <th class="text-left">Syear</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['bookData'] as $key => $value)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value['item_code']}}</td>
                                <td>{{$value['book_title']}}</td>
                                <td>{{$value['collection_type']}}</td>
                                <td>{{$value['scan_status']}}</td>
                                <td>{{$value['syear']}}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
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
</script>
@include('includes.footer')
@endsection