@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Form Builder</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @if ($sessionData = Session::get('data'))
                        <div class="alert alert-success alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                        @endif
                    </div>
                    <div class="col-lg-3 col-sm-3 col-xs-3">
                        <a href="{{ route('formbuild.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i>Add New Form Builder</a>
                    </div>
                    <br><br><br>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>Form Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                                $j=1;
                            @endphp

                            @if(isset($formBuils))
                                @foreach($formBuils as $key => $val)
                                    <tr>
                                        <td>{{$j}}</td>
                                        <td>{{$val['form_name']}}</td>
                                        <td>
                                            <div class="d-inline">
                                                <a class="btn btn-info btn-outline"
                                                   href="{{ route('formbuild.edit',['id'=>$val['id']]) }}">
                                                    <i class="ti-pencil-alt"></i>
                                                </a>
                                                <a class="btn btn-info btn-outline"
                                                   href="{{ route('view_form', $val['id']) }}">
                                                    <i class="mdi mdi-eye"></i>
                                                </a>
                                                <form action="{{ route('formbuild.delete', $val['id'])}}" method="get"
                                                      class="d-inline">
                                                    @method('DELETE')
                                                    @csrf
                                                    <button onclick="return confirmDelete();" type="submit"
                                                            class="btn btn-outline-danger"><i class="mdi mdi-close"></i>
                                                    </button>
                                                </form>
                                            </div>
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
