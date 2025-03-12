@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Table Columns</h4>
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
            {{--  <form action="{{ route('custom_module_table.store') }}"  method="post">--}}
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('custom-module.tables') }}" class="btn btn-info add-new"> Back </a>
                </div>
                <div class="col-md-3">
                    <label>Table Name </label>
                    <input type="text" id='table_name' required name="table_name" class="form-control"
                           value="{{$data['data']['table_name']}}" disabled>
                    @error('table_name')
                    <span style="color: red">{{$message}}</span>
                    @enderror
                </div>
                <input type="hidden" name="id" value="{{$data['data']['id']}}">
            </div>
            {{--    </form>--}}
            <form action="{{ route('custom_module_table_column.store', $data['data']['id']) }}" method="post">
                @csrf
                <div class="row mt-3">
                    <div class="col-md-2">
                        <label>Name </label>
                        <input type="text" id='column_name' required name="column_name" class="form-control"
                               value="{{$data['column_name']}}">
                        @error('column_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <label>Type </label>
                        <select class="form-control" name="column_type">

                            <optgroup label="Numbers">
                                <option value="bigint" {{$data['column_type'] == 'bigint' ? "selected": ''}}>
                                    BIGINT
                                </option>
                                <option value="decimal" {{$data['column_type'] == 'decimal' ? "selected": ''}}>
                                    DECIMAL
                                </option>
                                <option value="double" {{$data['column_type'] == 'double' ? "selected": ''}}>
                                    DOUBLE
                                </option>
                                <option value="float" {{$data['column_type'] == 'float' ? "selected": ''}}>
                                    FLOAT
                                </option>
                                <option value="integer" {{$data['column_type'] == 'integer' ? "selected": ''}}>
                                    INTEGER
                                </option>
                                <option value="mediumint" {{$data['column_type'] == 'mediumint' ? "selected": ''}}>
                                    MEDIUMINT
                                </option>
                                <option value="smallint" {{$data['column_type'] == 'smallint' ? "selected": ''}}>
                                    SMALLINT
                                </option>
                                <option value="tinyint" {{$data['column_type'] == 'tinyint' ? "selected": ''}}>
                                    TINYINT
                                </option>
                            </optgroup>
                            <optgroup label="Strings">
                                <option value="char" {{$data['column_type'] == 'char' ? "selected": ''}}>
                                    CHAR
                                </option>
                                <option value="longtext" {{$data['column_type'] == 'longtext' ? "selected": ''}}>
                                    LONGTEXT
                                </option>
                                <option value="mediumtext" {{$data['column_type'] == 'mediumtext' ? "selected": ''}}>
                                    MEDIUMTEXT
                                </option>
                                <option value="text" {{$data['column_type'] == 'text' ? "selected": ''}}>
                                    TEXT
                                </option>
                                <option value="tinytext" {{$data['column_type'] == 'tinytext' ? "selected": ''}}>
                                    TINYTEXT
                                </option>
                                <option value="varchar" {{$data['column_type'] == 'varchar' ? "selected": ''}}>
                                    VARCHAR
                                </option>
                            </optgroup>
                            <optgroup label="Date and Time">
                                <option value="date" {{$data['column_type'] == 'date' ? "selected": ''}}>
                                    DATE
                                </option>
                                <option value="datetime" {{$data['column_type'] == 'datetime' ? "selected": ''}}>
                                    DATETIME
                                </option>
                                <option value="time" {{$data['column_type'] == 'time' ? "selected": ''}}>
                                    TIME
                                </option>
                                <option value="timestamp" {{$data['column_type'] == 'timestamp' ? "selected": ''}}>
                                    TIMESTAMP
                                </option>
                                <option value="year" {{$data['column_type'] == 'year' ? "selected": ''}}>
                                    YEAR
                                </option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label>Length </label>
                        <input type="number" id='column_length' min="0" required name="column_length"
                               class="form-control"
                               value="{{$data['column_length']}}">
                        @error('table_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="col-md-1">
                        <label>Not Null </label>
                        <input type="checkbox" id='column_not_null' name="column_not_null" class="form-control"
                               {{($data['column_not_null'] === 1) ? 'checked': ''}}
                               value="{{$data['column_not_null']}}">
                        @error('table_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-1">
                        <label style="text-align: center">Auto In. </label>
                        <input type="checkbox" id='column_auto_increment' name="column_auto_increment"
                               {{($data['column_auto_increment'] === 1) ? 'checked': ''}}
                               class="form-control"
                               value="{{$data['column_auto_increment']}}">
                        @error('table_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <label>Index </label>
                        <select class="form-control" name="column_index">
                            <option value="" {{$data['column_index'] == '' ? "selected": ''}}></option>
                            <option value="INDEX" {{$data['column_index'] == 'INDEX' ? "selected": ''}}>INDEX</option>
                            <option value="UNIQUE" {{$data['column_index'] == 'UNIQUE' ? "selected": ''}}>UNIQUE
                            </option>
                            <option value="PRIMARY" {{$data['column_index'] == 'PRIMARY' ? "selected": ''}}>PRIMARY
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Default </label>
                        <input type="text" id='column_default' name="column_default" class="form-control"
                               value="{{$data['column_default']}}">
                        @error('table_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="col-md-2 mt-5">
                        <label>Field Type </label>
                        <select class="form-control" name="field_type">
                            <option value="text-field" {{$data['field_type'] == 'text-field' ? "selected": ''}}>Text
                                Fields
                            </option>
                            <option value="drop-down" {{$data['field_type'] == 'drop-down' ? "selected": ''}}>Drop
                                Down
                            </option>
                            <option value="checkbox" {{$data['field_type'] == 'checkbox' ? "selected": ''}}>Check Box
                            </option>
                            <option value="radio-button" {{$data['field_type'] == 'radio-button' ? "selected": ''}}>
                                Radio Button
                            </option>
                            <option value="File" {{$data['field_type'] == 'File' ? "selected": ''}}>File</option>
                            <option value="date" {{$data['field_type'] == 'date' ? "selected": ''}}>Date</option>
                        </select>
                    </div>
                    <div class="col-md-6 mt-5">
                        <label>Field Value </label>
                        <input class="form-control" type="text" name="field_value" value="{{$data['field_value']}}" data-role="tagsinput"/>
                    </div>
                    <input type="hidden" value="{{$data['column_id']}}" name="col_id">
                    @if($data['column_id'] > 0)
                        <div class="col-md-1">
                            <label style="display: block">Action</label>
                            <button type="submit" name="submit" id="Submit" class="btn btn-info btn-outline">
                                <i class="ti-pencil-alt"></i>
                            </button>
                        </div>
                    @else
                        <div class="col-md-1">
                            <label style="display: block">Action</label>
                            <input type="submit" name="submit" id="Submit" value="+" class="btn btn-success">
                        </div>
                    @endif

                </div>
            </form>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Id</th>
                                <th>Field Type</th>
                                <th>Field Value</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Length</th>
                                <th>Not Null</th>
                                <th>Auto Increment</th>
                                <th>Index</th>
                                <th>Default</th>
                                {{-- <th>Client Id</th>
                                 <th>Sub Institute Id</th>--}}
                                {{--<th>Status</th>--}}
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php
                                $j=1;
                            @endphp
                            @foreach($data['data']['columns'] as $key => $col)
                                <tr>
                                    <td>{{$col->id}}</td>
                                    <td>{{$col->field_type}}</td>
                                    <td>{{$col->field_value}}</td>
                                    <td>{{$col->column_name}}</td>
                                    <td>{{$col->type}}</td>
                                    <td>{{$col->length}}</td>
                                    <td>{{$col->not_null}}</td>
                                    <td>{{$col->auto_increment}}</td>
                                    <td>{{$col->index}}</td>
                                    <td>{{$col->default}}</td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ url('custom-module/table-column-create/'.$data['data']['id'].'/column/'.$col->id)}}"
                                               class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                        </div>
                                        <form class="d-inline"
                                              action="{{ route('custom_module_table_column.delete', [$data['data']['id'],$col->id])}}"
                                              method="post">
                                            @csrf
                                            @method('DELETE')
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
                        <center class="mb-2">
                            <a href="{{url('/custom-module/create-db-table/'.$data['data']['id'])}}" value="Save"
                               class="btn btn-success">Save Changes</a>
                        </center>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.min.js"></script>

<script>
    $(document).ready(function () {
        $('#example').DataTable();
    });

</script>
@include('includes.footer')
