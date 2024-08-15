@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Manage {{$data['data']['name']}}</h4>
            </div>
        </div>
        <div class="card">
            <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
            @endif
            <form action="{{ route('custom_module_crud.store', $data['data']['id']) }}" enctype="multipart/form-data" method="post">
                @csrf

                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('custom_module_crud.index',$data['data']['id']) }}" class="btn btn-info add-new"> Back </a>
                </div>
                <div class="row mt-3">
                    @foreach($data['data']['columns'] as $column)
                        @if($column['column_name'] == 'id') @continue @endif

                        <div class="col-md-6 mt-2">
                            <label>{{$column['column_name']}} </label>
                            @if ($column['column_name'] == 'image')
                                <input type="file" id="{{$column['column_name']}}" {{$data['data']['view'][$column['column_name']] ? "hidden":"" }} name="{{$column['column_name']}}" class="form-control" value="{{$data['data']['view'][$column['column_name']]}}">
                                <input type="file" id="{{$column['column_name']}}" {{$data['data']['view'][$column['column_name']] ? "":"hidden" }} name="new_{{$column['column_name']}}" class="form-control" value="{{$data['data']['view'][$column['column_name']]}}">
                               @if ($data['data']['view']['id'] > 0)
                                <a href="{{asset('images/'.$data['data']['view'][$column['column_name']])}}" target="_blank">link</a>
                                @endif
                            @else
                                <input type="text" id="{{$column['column_name']}}" required name="{{$column['column_name']}}" class="form-control"
                                       value="{{$data['data']['view'][$column['column_name']]}}">
                            @endif
                            @error('column_name')
                            <span style="color: red">{{$message}}</span>
                            @enderror
                        </div>
                    @endforeach
                        <input type="text" hidden  name="view_id" value="{{$data['data']['view']['id']}}" class="btn btn-success">
                    {{--<input type="hidden" value="{{$data['column_id']}}" name="col_id">--}}
                </div>
                <div class="row mt-4">
                    <div class="form-group align-center">
                        <center>
                        <input type="submit" name="submit" id="Submit" value="Submit" class="btn btn-success">

                        </center>
                    </div>
                </div>
            </form>
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
