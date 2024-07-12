@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Payroll Type</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('payroll_type.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Payroll Type </a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Id</th>
                                <th>Type</th>
                                <th>Payroll Name</th>
                                <th>Amount Type</th>
                                <th>Percentage (%) / Flat</th>
                                <th>Status</th>
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
                                    <td>{{$data->payroll_type == 1 ? 'Allowance' : 'Deduction'}}</td>
                                    <td>{{$data->payroll_name}}</td>
                                    <td>{{$data->amount_type == 1 ? 'Flat' :'Percentage'}}</td>
                                    <td>{{$data->payroll_percentage ?? '-'}}</td>
                                    <td>{{$data->status == 1 ? 'Enable':'Disable'}}</td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ url('payroll-type/create/'.$data->id)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                        </div>
                                        <form class="d-inline" action="{{ route('payroll_type.destroy', $data->id)}}" method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                                        </form>
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
