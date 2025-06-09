@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Lost & Damage</h4>
            </div>
        </div>

        <div class="card">
        @if(!empty($data['message']))
            @if(!empty($data['status_code']) && $data['status_code'] == 1)
                <div class="alert alert-success alert-block">
            @else
                <div class="alert alert-danger alert-block">
            @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif
        <div class="row">
        <form action="{{route('Lost_and_Damage.create')}}" method="GET" class="ml-4">
        <label for="" style="font-size:1rem"><b>Item code : </b></label>
            <div class="d-flex">
                <div class="form-group mr-2">                
                    <input type="text" class="form-control" name="item_code" id="item_code" @if(isset($data['item_code'])) value="{{$data['item_code']}}" @else placeholder="Enter Item Code" @endif>
                </div>
                
                <div class="form-group mr-2">
                    <select class="form-control" name="status" id="status">
                        <option value="">Item Status</option>
                        <!-- <option value="lost" @if(isset($data['status']) && $data['status'] == 'lost') selected @endif>Lost</option>
                        <option value="damaged" @if(isset($data['status']) && $data['status'] == 'damaged') selected @endif>Damaged</option> -->
                        @foreach($data['item_status_arr'] as $key => $value)
                            <option value="{{ $key }}" @if(isset($data['item_status']) && $data['item_status'] == $key) selected @endif>{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <input type="submit" value="Search" class="btn btn-primary ml-2">
                </div>
            </div>
        </form>
        </div>
        <!-- row ends  -->
        </div>
        <!-- card ends  -->
        @if(!empty($data['bookdata']))
        <div class="card">
            <div class="table-responsive">
            <table id="example" class="table table-box table-bordered">
            <thead>
                <th>Sr No.</th>
                <th>Item Code</th>
                <th>Title</th>
                <th>Collection Type</th>                    
                <th>Remarks</th>
                <th>Item Status</th>
            </thead>
            <tbody>
            @foreach($data['bookdata'] as $index => $book)
                <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $book->item_code ?? '' }}</td>
                <td>{{ $book->title ?? '' }}</td>
                <td>{{ $book->collection_type ?? '' }}</td>
                <td>{{ $book->remarks ?? '' }}</td>
                <td>{{ $book->item_status_name ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
            </table>
            </div>
            <!-- TABLE DIV ENDS -->
        </div>  
        @endif
        
        <!-- card end  -->
    </div>
</div>        
<!-- container ends -->
@include('includes.footerJs')
<script>
    $(document).ready(function() {
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
                    title: 'Fees Monthly Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Fees Monthly Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Fees Monthly Report'},
                {extend: 'print', text: ' PRINT', title: 'Fees Monthly Report'},
                'pageLength'
            ],
        });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } ); 
    });
</script>
@include('includes.footer')