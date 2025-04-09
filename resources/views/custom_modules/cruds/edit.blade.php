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
            <form action="{{ route('custom_module_crud.store', $data['data']['id']) }}" enctype="multipart/form-data"
                  method="post">
                @csrf

                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('custom_module_crud.index',$data['data']['id']) }}" class="btn btn-info add-new">
                        Back </a>
                </div>
                <div class="row mt-3">
                    @foreach($data['data']['columns'] as $column)
                            <?php
                            $fieldVal = json_decode($column['field_value'], true);
                            ?>
                        @if($column['column_name'] == 'id')
                            @continue
                        @endif
                        <div class="col-md-6 mt-2">
                            <label>{{ ucwords(str_replace('_', ' ', $column['column_name'])) }}</label>
                            @if($column['field_type'] == 'File')
                                <input type="file" id="{{$column['column_name']}}"
                                       {{$data['data']['view'][$column['column_name']] ? "hidden":"" }} name="{{$column['column_name']}}"
                                       class="form-control" value="{{$data['data']['view'][$column['column_name']]}}">
                                <input type="file" id="{{$column['column_name']}}"
                                       {{$data['data']['view'][$column['column_name']] ? "":"hidden" }} name="new_{{$column['column_name']}}"
                                       class="form-control" value="{{$data['data']['view'][$column['column_name']]}}">
                                @if ($data['data']['view']['id'] > 0)
                                    <a href="{{asset('images/'.$data['data']['view'][$column['column_name']])}}"
                                       target="_blank">View File</a>
                                @endif
                            @elseif($column['field_type'] == 'checkbox')
                                @foreach($fieldVal as $val)
                                        <?php
                                        $checkboxVal = json_decode($data['data']['view'][$column['column_name']]);
                                        ?>
                                    @if(is_array($checkboxVal) && in_array($val, $checkboxVal))
                                        <span style="display: block"> {{$val}} <input type="checkbox" checked
                                                                                      name="{{$column['column_name']}}[]"
                                                                                      value="{{$val}}"></span>
                                    @else
                                        <span style="display: block"> {{$val}} <input type="checkbox"
                                                                                      name="{{$column['column_name']}}[]"
                                                                                      value="{{$val}}"></span>
                                    @endif
                                @endforeach
                            @elseif($column['field_type'] == 'drop-down')
                                <select class="form-control" id="{{$column['column_name']}}"
                                        name="{{$column['column_name']}}">0
                                    <option value=""
                                    >Select {{ucwords(str_replace('_', ' ', $column['column_name']))}}</option>
                                    @foreach($fieldVal as $val)
                                        @if ($data['data']['view'][$column['column_name']] == $val)
                                            <option value="{{$val}}" selected
                                            >{{$val}}</option>
                                        @else
                                            <option value="{{$val}}"
                                            >{{$val}}</option>
                                        @endif
                                    @endforeach

                                </select>

                            @elseif($column['field_type'] == 'radio-button')
                                @foreach($fieldVal as $val)
                                    @if ($data['data']['view'][$column['column_name']] == $val)
                                    <span style="display: block"> {{$val}} <input type="radio" checked
                                                                                  name="{{$column['column_name']}}"
                                                                                  value="{{$val}}"></span>
                                    @else
                                        <span style="display: block"> {{$val}} <input type="radio"
                                                                                      name="{{$column['column_name']}}"
                                                                                      value="{{$val}}"></span>
                                    @endif
                                @endforeach

                            @elseif($column['column_name'] == 'academic_section')
                                <select class="form-control" id="{{$column['column_name']}}"
                                        name="{{$column['column_name']}}">
                                    @foreach($data['data']['academic_section'] as $academic_section)
                                        @if ($data['data']['view'][$column['column_name']] == $academic_section['id'])
                                            <option value="{{$academic_section['id']}}"
                                                    selected>{{$academic_section['title']}}
                                                - {{$academic_section['short_name']}}
                                                - {{$academic_section['medium']}}</option>
                                        @else
                                            <option value="{{$academic_section['id']}}">{{$academic_section['title']}}
                                                - {{$academic_section['short_name']}}
                                                - {{$academic_section['medium']}}</option>
                                        @endif
                                    @endforeach

                                </select>
                            @elseif($column['column_name'] == 'Division')
                                <select class="form-control" id="{{$column['column_name']}}"
                                        name="{{$column['column_name']}}">
                                    @foreach($data['data']['division'] as $division)
                                        @if ($data['data']['view'][$column['column_name']] == $division['id'])
                                            <option value="{{$division['id']}}" selected>{{$division['name']}}</option>
                                        @else
                                            <option value="{{$division['id']}}">{{$division['name']}}</option>
                                        @endif
                                    @endforeach

                                </select>
                            @elseif($column['column_name'] == 'Standard')
                                <select class="form-control" id="{{$column['column_name']}}"
                                        name="{{$column['column_name']}}">
                                    @foreach($data['data']['standard'] as $standard)
                                        @if ($data['data']['view'][$column['column_name']] == $standard['id'])
                                            <option value="{{$standard['id']}}" selected>{{$standard['name']}}</option>
                                        @else
                                            <option value="{{$standard['id']}}">{{$standard['name']}}</option>
                                        @endif
                                    @endforeach
                                </select>
                            @elseif ($column['field_type'] == "date")
                                <input type="text" id="{{$column['column_name']}}"
                                       name="{{$column['column_name']}}" class="form-control mydatepicker"
                                       value="{{$data['data']['view'][$column['column_name']]}}">
                            @elseif ($column['field_type'] == "text-area")
                                <textarea id="{{$column['column_name']}}"
                                       name="{{$column['column_name']}}" class="form-control resizableVertical">{{$data['data']['view'][$column['column_name']]}}</textarea>
                            @else
                                <input type="text" id="{{$column['column_name']}}"
                                       name="{{$column['column_name']}}" class="form-control"
                                       value="{{$data['data']['view'][$column['column_name']]}}">
                            @endif
                            @error($column['column_name'])
                            <div class="error" style="color: red">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
                    <input type="text" hidden name="view_id" value="{{$data['data']['view']['id']}}"
                           class="btn btn-success">
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
