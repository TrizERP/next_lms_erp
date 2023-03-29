@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Vaccination</h4>
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
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('student_vaccination.create') }}" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add New Student Vaccination 
                    </a>
                </div>                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>Student Name</th>
                                    <th>Doctor Name</th>
                                    <th>Doctor Contact</th>
                                    <th>Date</th>
                                    <th>Vaccination Type</th>
                                    <th>Note</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                            @if(isset($data['data']))
                                @foreach($data['data'] as $pkey => $pvalue)
                                <tr>    
                                    <td>{{$j}}</td>
                                    <td>{{$pvalue['student_name']}}</td>
                                    <td>{{$pvalue['doctor_name']}}</td>  
                                    <td>{{$pvalue['doctor_contact']}}</td> 
                                    <td>{{$pvalue['date']}}</td> 
                                    <td>{{$pvalue['vaccination_type']}}</td> 
                                    <td>{{$pvalue['note']}}</td> 
                                    <td>
                                        <div class="d-inline">                                            
                                            <a href="{{ route('student_vaccination.edit',$pvalue['id'])}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('student_vaccination.destroy', $pvalue['id'])}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                                        </form>
                                    </td>  
                                </tr>
                            @php
                            $j++;
                            @endphp
                                @endforeach
                            @endif
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
