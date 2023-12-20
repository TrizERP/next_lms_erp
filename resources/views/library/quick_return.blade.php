@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Quick Return</h4>
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
        <form action="{{route('quick_return.create')}}" method="POST">
        <label for="" style="font-size:1rem"><b>Item Id : </b></label>
            <div class="d-flex">
                <div class="form-group">                
                <input type="text" class="form-control" name="item_code" id="item_code" @if(isset($data['item_code'])) value="{{$data['item_code']}}" @else placeholder="Enter Item Id" @endif>
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

        <div class="card">
        <div>   
        <label for="student details"><b>Student's Who Returned Book :</b></label>
        </div>
            <div class="table-responsive">
                <table id="example" class="table table-box table-bordered">
                <thead>
                    <th>Sr No.</th>
                    <th>{{ App\Helpers\get_string('studentname')}}</th>
                    <th>{{ App\Helpers\get_string('std/div')}}</th>
                    <th>Enrollment No</th>                    
                    <th>Mobile</th>
                    <th>Item Code</th>
                    <th>Book Name</th>
                    <th>Issued Date</th>
                    <th>Due Date</th>  
                    <th>Publisher Name</th>                    
                    <th class="text-left">Author Name</th>
                </thead>

                <tbody>
                @if(!empty($data['circulation_data']))
                @php $i=1; @endphp
                    @foreach($data['circulation_data'] as $key=>$value)
                    <tr>
                    <td>{{$i++}}</td>
                    <td>{{$value->student_name}}</td>
                    <td>{{$value->standard ." / ".$value->division}}</td>
                    <td>{{$value->enrollment_no}}</td>      
                    <td>{{$value->mobile}}</td>                    
                    <td>{{$value->item_code}}</td>                    
                    <td>{{$value->book_name}}</td>                    
                    <td>{{ \Carbon\Carbon::parse($value->issued_date)->format('d-m-Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($value->due_date)->format('d-m-Y') }}</td>                    
                    <td>{{$value->publisher_name}}</td>                    
                    <td>{{$value->author_name}}</td>                    
                    </tr>
                    @endforeach
               
                @endif
                </tbody>
                </table>
            </div>
            <!-- TABLE DIV ENDS -->
        </div>  
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