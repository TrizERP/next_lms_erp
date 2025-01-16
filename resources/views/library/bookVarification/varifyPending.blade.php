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
            <div>
                <form action="{{route('verifiyPending_report.index')}}" class="row">
                    @csrf
                    <div class="col-md-6 form-group">
                        <label for="item_code"><b>Item Code</b></label>
                        <input type="text" class="form-control" name="item_code" id="item_code" @if(isset($data['searchedItem'])) value="{{$data['searchedItem']}}" @else placeholder="Search Item Status Name" @endif style="width:500px;">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="Academic Year"><b>Academic Year</b></label>
                        <select name="year" id="year" class="form-control">
                            <option value="">All</option>
                            @foreach($data['all_year'] as $key=>$value)
                            <option value="{{$value->syear}}" @if(isset($data['searchedYear']) && $data['searchedYear']==$value->syear) selected @endif>{{$value->syear}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="submit" value="Search" name="submit" class="btn btn-primary" style="margin-top:22px">
                    </div>
                </form>
            </div>

            @if(isset($data['bookData']) && !empty($data['bookData']))
                <div class="table-responsive">
                    <table id="example" class="table table-box table-bordered">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Syear</th>
                                <th>Item Code</th>
                                <th>Title</th>
                                <th class="text-left">Collection Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['bookData'] as $key => $value)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value->syear}}</td>
                                <td>{{$value->item_code}}</td>
                                <td>{{$value->book_title}}</td>
                                <td>{{$value->collection_type}}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
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
                    title: 'Book Verification Pending Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Book Verification Pending Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Book Verification Pending Report'},
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Book Verification Pending Report',
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