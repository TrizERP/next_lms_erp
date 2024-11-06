@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">{{$data['data']['table_name']}}</h4>
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
                    <a href="{{ route('custom-module.tables') }}" class="btn btn-info add-new"> Back </a>
                </div>
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('custom_module_crud.create', $data['data']['id']) }}"
                       class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Records </a>
                </div>

                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                @foreach($data['data']['columns'] as $column)
                                    <th>{{$column['column_name']}}</th>
                                @endforeach
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php
                                $j=1;
                            @endphp
                            @foreach($data['data']['view'] as $key => $value)
                                <tr>
                                    @foreach($data['data']['columns'] as $column)
                                        @if($column['column_name'] == 'Division')
                                            @foreach($data['data']['division'] as $division)
                                                @if ($division['id'] == $value[$column['column_name']])
                                                    <td>{{$division['name']}}</td>
                                                @endif
                                            @endforeach
                                        @elseif($column['column_name'] == 'Standard')
                                            @foreach($data['data']['standard'] as $standard)
                                                @if ($standard['id'] == $value[$column['column_name']])
                                                    <td>{{$standard['name']}}</td>
                                                @endif
                                            @endforeach
                                        @elseif ($column['column_name'] == 'image')

                                            <td><a href="{{asset('images/'.$value[$column['column_name']])}}"
                                                   target="_blank">link</a>
                                            </td>
                                        @else
                                            <td>{{$value[$column['column_name']]}}</td>
                                        @endif
                                    @endforeach
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ url('custom-module/create-view/' . $data['data']['id'] . '/update/' . $value->id) }}"
                                               class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                        </div>
                                        <form class="d-inline"
                                              action="{{ route('custom_module_crud.delete', $value->id)}}"
                                              method="post">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" value="{{$data['data']['table_name']}}"
                                                   name="table_name">
                                            <input type="hidden" value="{{$data['data']['id']}}" name="view_id">
                                            <button type="submit" class="btn btn-info btn-outline-danger"><i
                                                    class="ti-trash"></i></button>
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
