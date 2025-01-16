@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Library Item Varification Status</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    @if($sessionData['status'] == 1)
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

                <div class="sttabs tabs-style-linemove triz-verTab bg-white style2 text-center" style="width:100%">
                        <!-- tabs list starts -->
                        <center>
                        <ul class="nav nav-tabs tab-title mb-4">
                            <li class="nav-item"><a href="#section-linemove-1" class="nav-link active" aria-selected="true" data-toggle="tab"><span>Item Status</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-2" class="nav-link" aria-selected="false" data-toggle="tab"><span>Add Item Status</span></a></li>
                        </ul>
                        <!-- tabs list ends -->

                        <!-- tabs sections start -->
                        <div class="tab-content">
                            <!-- tab 1 start  -->
                            <div class="tab-pane p-3 active" id="section-linemove-1" role="tabpanel">
                                <!-- search div -->
                                <form action="{{route('item_verification_status.index')}}" class="col-md-12 mb-4" style="width:100%;">
                                    <center>
                                    <div style="width:100%;padding:20px;box-shadow:5px 5px 5px 5px #ddd;display:flex;justify-content:center">
                                        <input type="text" class="form-control" name="item_status_name" id="item_status_name" @if(isset($data['searchedItem'])) value="{{$data['searchedItem']}}" @else placeholder="Search Item Status Name" @endif style="width:500px;">
                                        <input type="submit" value="Search" name="submit" class="btn btn-primary ml-4">
                                    </div>
                                    </center>
                                </form>
                                <br>
                                <!-- TABLE DIV START  -->
                                <div class="table-responsive">
                                    <table id="example" class="table table-box table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Sr No</th>
                                                <th>Item Status Name</th>
                                                <th>No. Loan</th>
                                                <th>Last Updated At</th>
                                                <th class="text-left">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($data['all_items'] as $key=>$value)
                                                <tr>
                                                    <td>{{$key+1}}</td>
                                                    <td>{{$value['item_status_name']}}</td>
                                                    <td>{{ ($value['no_loan']==1) ? 'YES' : 'NO' }}</td>
                                                    <td>{{ (isset($value['updated_at'])) ? date('d-m-Y h:i A', strtotime($value['updated_at'])) : '-' }}</td>
                                                    <td>
                                                        <div class="d-inline">
                                                            <a data-toggle="modal" data-target="#exampleModal_{{$value['id']}}" class="btn btn-info btn-outline">
                                                                <i class="ti-pencil-alt"></i>
                                                            </a>
                                                        </div>
                                                        <form action="{{ route('item_verification_status.destroy', $value['id'])}}" method="post" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger">
                                                                <i class="ti-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <!-- TABLE DIV END  -->
                            </div>
                            <!-- tab 1 end  -->
                            <!-- tab 2 start  -->
                            <div class="tab-pane p-3" id="section-linemove-2" role="tabpanel">
                                <form action="{{route('item_verification_status.store')}}" class="row" method="POST">
                                    @csrf 
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>
                                                <label for="item_status_name"><b>Item Status Name</b></label>
                                            </th>
                                            <td>
                                                <input type="text" class="form-control" name="item_status_name" id="item_status_name" placeholder="Enter Item Status Name" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                <label for="rules"><b>Rules</b></label>
                                            </th>
                                            <td>
                                                <input type="checkbox" name="no_loan" id="no_loan" value="1" checked> 
                                                <label for="no_loan"><b>No Loan Tansaction</b></label>
                                            </td>
                                        </tr>
                                    </table>
                                    <div class="col-md-12">
                                        <center>
                                            <input type="submit" value="Submit" name="submit" class="btn btn-success mt-2">
                                        </center>
                                    </div>
                                </form>
                            </div>
                            <!-- tab 2 end  -->
                        </div>
                        </center>
                        <!-- tab section end  -->
                    </div>

                </div>

               </div>
            </div>
        </div>
    </div>
</div>

@foreach($data['all_items'] as $key=>$value)
<!-- Modal -->
<div class="modal fade" id="exampleModal_{{$value['id']}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:1000px !important">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Item Status</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
            <form action="{{route('item_verification_status.update',[$value['id']])}}" class="row" method="POST">
                {{ method_field("PUT") }}
                @csrf 
                <table class="table table-bordered">
                    <tr>
                        <th>
                            <label for="item_status_name"><b>Item Status Name</b></label>
                        </th>
                        <td>
                            <input type="text" class="form-control" name="item_status_name" id="item_status_name" value="{{$value['item_status_name']}}" required>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="rules"><b>Rules</b></label>
                        </th>
                        <td>
                            <input type="checkbox" name="no_loan" id="no_loan" value="1" {{ $value['no_loan'] == 1 ? 'checked' : '' }}>
                            <label for="no_loan"><b>No Loan Tansaction</b></label>
                        </td>
                    </tr>
                </table>
                <div class="col-md-12">
                    <center>
                        <input type="submit" value="Submit" name="submit" class="btn btn-success mt-2">
                    </center>
                </div>
            </form>
      </div>
    </div>
  </div>
</div>
@endforeach

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
                    title: 'Item Verification Status Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Item Verification Status Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Item Verification Status Report'},
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Item Verification Status Report',
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