<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="/resources/demos/style.css">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        #sortable1, #sortable2 {
            border: 1px solid #eee;
            width: 142px;
            min-height: 20px;
            list-style-type: none;
            margin: 0;
            padding: 5px 0 0 0;
            float: left;
            margin-right: 10px;
        }

        #sortable1 li, #sortable2 li {
            margin: 0 5px 5px 5px;
            padding: 5px;
            font-size: 1.2em;
            width: 120px;
        }
    </style>
</head>
<body>
<div id="app">
    <div class="mt-5">
        <div class="row">
            <div class="col-md-6">
                <div class="row">
                    <div class="col-md-9">
                        <h2 class="text-center pb-3 pt-1"><input type="checkbox" value="1" name="is_checked"
                                                                 id="is_checked">
                            Step :1 Match Fields</h2>
                    </div>
                    <div class="col-md-3 d-none" id="skip_field">
                        <select name="skip" id="skip">
                            <option value="1">Skip</option>
                            <option value="2">OverWrite</option>
                        </select>
                    </div>

                </div>
                <div class="row d-none" id="match_field">
                    <div class="col-md-6 p-3 ml-2">
                        <p>All Fields List</p>
                        <ul class="list-group shadow-lg connectedSortable" id="sortable1" style="width: 300px">
                            @if(!empty($table_fields) && $table_fields->count())
                                @foreach($table_fields as $key=>$value)
                                    <li class="list-group-item" style="width: 290px"
                                        item-id="{{$value->field}}">{{ $value->display_field }}</li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                    <div class="col-md-5 p-3">
                        <p>Match Fields List</p>
                        <ul class="list-group  connectedSortable" id="sortable2" style="width: 300px">
                            @if(!empty($completeItem) && $completeItem->count())
                                @foreach($completeItem as $key=>$value)
                                    <li class="list-group-item " style="width: 290px"
                                        item-id="{{ $value->id }}">{{ $value->title }}</li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel panel-default">
                    <h2 class="text-center pb-3 pt-1">
                        Step:2 Select Fields</h2>

                    <div class="panel-body">
                        <form class="form-horizontal" method="POST" action="{{ route('import_process') }}">
                            @csrf
                            <input type="hidden" name="csv_data_file_id" id="csv_data_file_id"
                                   value="{{ $csv_data_id }}"/>
                            <input type="hidden" name="table_name" id="table_name"
                                   value="{{ $table_name }}"/>

                            <table class="table">
                                <div class="col-md-3">

                                    @foreach ($csv_data as $key=> $row)
                                        <tr>
                                            @if (isset($csv_header_fields))
                                                <th>{{ $csv_header_fields[$key] }}</th>
                                            @endif
                                            <td>{{ $row }}</td>
                                            <td>
                                                <select name="fields[{{ $key }}]">
                                                    <option value="0">---select---</option>
                                                    @foreach ($table_fields as $db_field)
                                                        <option
                                                            value="{{ $db_field->field}}">{{ $db_field->display_field }} {{$db_field->is_required ? '*' :''}}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="custom_text[{{$key}}]">
                                            </td>

                                        </tr>
                                    @endforeach
                                </div>
                            </table>

                            <button type="submit" class="btn btn-primary">
                                Import Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">

        </div>
    </div>
</div>

<!-- Scripts -->
<script src="{{ asset('js/app.js') }}"></script>

<script src="https://code.jquery.com/jquery-3.6.0.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
<script>

    $(function () {
        var csv_file_id = $('#csv_data_file_id').val();
        $('#is_checked').click(function () {
            if ($('#is_checked').is(':checked')) {
                var is_checked = $('#is_checked').val();
                $.ajax({
                    url: "{{ route('update.match_fields') }}",
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {customize_is_checked: is_checked, csv_file_id: csv_file_id},
                    success: function (data) {
                        console.log('success');
                    }
                });
                $('#match_field').removeClass('d-none');
                $('#skip_field').removeClass('d-none');
            } else {
                $('#match_field').addClass('d-none');
                $('#skip_field').addClass('d-none');
            }
        })

        $('#skip').change(function () {
            var skipVal = $('#skip').val();
            $.ajax({
                url: "{{ route('update.match_fields') }}",
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {skip_val: skipVal, csv_file_id: csv_file_id},
                success: function (data) {
                    console.log('success');
                }
            });
        })

        $("#sortable1, #sortable2").sortable({
            connectWith: ".connectedSortable"
        }).disableSelection();
    });

    $(".connectedSortable").on("sortupdate", function (event, ui) {
        var panddingArr = [];
        var completeArr = [];
        var csv_file_id = $('#csv_data_file_id').val();
        $("#sortable1 li").each(function (index) {
            panddingArr[index] = $(this).attr('item-id');
        });

        $("#sortable2 li").each(function (index) {
            completeArr[index] = $(this).attr('item-id');
        });
        console.log(completeArr);
        console.log(panddingArr);
        $.ajax({
            url: "{{ route('update.match_fields') }}",
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {panddingArr: panddingArr, completeArr: completeArr, csv_file_id: csv_file_id},
            success: function (data) {
                console.log('success');
            }
        });

    });
</script>
</body>
</html>



