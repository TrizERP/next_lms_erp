@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('add_school.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <br><br><br>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <table id="example" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>School Name</th>
                                <th>Short Code</th>
                                <th>Contact Person</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th>Receipt Header</th>
                                <th>Receipt Address</th>
                                <th>Fee Email</th>
                                <th>Receipt Contact</th>
                                <th>Sort Order</th>
                                <th>Logo</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['data'] as $key => $data)
                            <tr>    
                                <td>{{$data->SchoolName}}</td>
                                <td>{{$data->ShortCode}}</td>
                                <td>{{$data->ContactPerson}}</td>
                                <td>{{$data->Mobile}}</td>
                                <td>{{$data->Email}}</td>                 
                                <td>{{$data->ReceiptHeader}}</td>                 
                                <td>{{$data->ReceiptAddress}}</td>                 
                                <td>{{$data->FeeEmail}}</td>                 
                                <td>{{$data->ReceiptContact}}</td>                 
                                <td>{{$data->SortOrder}}</td>                 
                                <td>{{$data->Logo}}</td>     
                                <td>
                                    <a href="{{ route('add_school.edit',$data->Id)}}" class="btn btn-info btn-outline btn-circle btn m-r-5">
                                        <i class="ti-pencil-alt"></i>
                                    </a>
                                    <form action="{{ route('add_school.destroy', $data->Id)}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-info btn-outline btn-circle btn m-r-5" type="submit"><i class="ti-trash"></i></button>
                                    </form>
                                </td> 
                            </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({
        "scrollX": true
    });
});

</script>
@include('includes.footer')
