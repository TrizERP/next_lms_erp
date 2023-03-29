@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Manage Institute</h4> 
            </div>
        </div>
        <div class="card">               
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="col-lg-3 col-sm-3 col-xs-3">
                <a href="{{ route('manage_institute.create') }}" class="btn btn-info add-new mb-3">
                    <i class="fa fa-plus"></i> Add Institute
                </a>
            </div>
            <div class="row">                
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr.No.</th>
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
                                    <th>Cheque Return Charges</th>                                    
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                                @foreach($data['data'] as $key => $data)
                                <tr> 
                                    <td>{{$j}}</td>
                                    <td>{{$data['SchoolName']}}</td>
                                    <td>{{$data['ShortCode']}}</td>
                                    <td>{{$data['ContactPerson']}}</td>  
                                    <td>{{$data['Mobile']}}</td> 
                                    <td>{{$data['Email']}}</td> 
                                    <td>{{$data['ReceiptHeader']}}</td> 
                                    <td>{{$data['ReceiptAddress']}}</td> 
                                    <td>{{$data['FeeEmail']}}</td> 
                                    <td>{{$data['ReceiptContact']}}</td> 
                                    <td>{{$data['SortOrder']}}</td> 
                                    <td><a target="blank" href="/admin_dep/images/{{$data['Logo']}}">{{$data['Logo']}}</a></td>
                                    <td>{{$data['cheque_return_charges']}}</td> 
                                    <td>                                        
                                        <div class="d-flex align-items-center justify-content-end">
                                            <a href="{{ route('manage_institute.edit',$data['Id'])}}" class="btn btn-outline-success mr-1">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                            <form action="{{ route('manage_institute.destroy', $data['Id'])}}" class="d-inline" method="post">
                                            @csrf
                                            @method('DELETE')
                                                <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>     
                                </tr>
                                @php
                                $j++;
                                @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>    
        </div>      
    </div>
</div>

@include('includes.footerJs')
<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
